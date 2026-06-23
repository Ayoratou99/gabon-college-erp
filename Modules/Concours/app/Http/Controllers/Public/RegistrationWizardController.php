<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\DTOs\RegisterCandidatDto;
use Modules\Concours\Exceptions\InscriptionsClosedException;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Services\CandidatRegistrationService;
use Modules\Concours\Services\Public\InscriptionDraft;
use Modules\Concours\Services\Public\InscriptionStagedDocuments;
use Modules\Concours\Support\PhoneNumber;
use Modules\Referentiels\Models\DocumentRequis;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;

/**
 * Public inscription wizard.
 *
 * Five steps, each with its own validation, persisted across requests via
 * a session draft (see InscriptionDraft). Files (photo, documents) are NOT
 * persisted between steps — they're uploaded in one shot on the final
 * `documents` step. The final POST re-validates the entire payload through
 * the existing CandidatRegistrationService so the backend never trusts the
 * draft alone.
 *
 *   GET  /inscription                    → redirect to current (or first) step
 *   GET  /inscription/{step}             → show step form (pre-filled from draft)
 *   POST /inscription/{step}             → validate step, merge into draft, advance
 *   POST /inscription/{step}/back        → previous step (draft preserved)
 *   POST /inscription/reset              → wipe draft + restart from step 1
 *   GET  /inscription/succes/{matricule} → terminal success page
 *   GET  /inscription/inscriptions-fermees → "session closed" page
 *
 * Order of steps is the single source of truth for next/prev navigation —
 * change the array and the wizard reconfigures.
 */
final class RegistrationWizardController extends Controller
{
    /** @var list<string> */
    private const STEPS = ['identite', 'contact', 'bac', 'choix', 'documents'];

    public function __construct(
        private readonly CandidatRegistrationService $registration,
        private readonly InscriptionDraft $draft,
        private readonly InscriptionStagedDocuments $staged,
    ) {}

    /** GET /inscription → redirect to the step the visitor is on (or step 1). */
    public function entry(): RedirectResponse
    {
        $session = ConcoursSession::publicCurrent();
        if ($session === null || ! $session->isInscriptionOpen()) {
            return redirect()->route('concours.inscriptions.fermees');
        }
        $step = $this->draft->currentStep() ?? self::STEPS[0];
        return redirect()->route('concours.inscription.wizard.show', ['step' => $step]);
    }

    public function show(string $step): View|RedirectResponse
    {
        $session = ConcoursSession::publicCurrent();
        if ($session === null || ! $session->isInscriptionOpen()) {
            return redirect()->route('concours.inscriptions.fermees');
        }

        if (! in_array($step, self::STEPS, true)) {
            return redirect()->route('concours.inscription.wizard.entry');
        }

        // Gate direct deep-links: you can't jump to step 4 without filling
        // steps 1-3 first. We allow re-visiting any step you've already
        // reached + the next-to-fill one.
        if (! $this->draft->hasReached($step, self::STEPS)) {
            return redirect()->route('concours.inscription.wizard.show', [
                'step' => $this->draft->currentStep() ?? self::STEPS[0],
            ]);
        }

        return view("concours::public.registration.wizard", $this->viewData($step, $session));
    }

    public function submit(Request $request, string $step): RedirectResponse
    {
        $session = ConcoursSession::publicCurrent();
        if ($session === null || ! $session->isInscriptionOpen()) {
            return redirect()->route('concours.inscriptions.fermees');
        }

        if (! in_array($step, self::STEPS, true)) {
            return redirect()->route('concours.inscription.wizard.entry');
        }

        // Canonicalise the phone before per-step validation so separators are
        // stripped consistently and the value stored in the draft is clean.
        if ($request->has('telephone')) {
            $request->merge(['telephone' => PhoneNumber::normalize($request->input('telephone'))]);
        }

        // Each step has its own validation. The final step also fans out to
        // the full Service::register() pipeline below.
        $rules = $this->rulesForStep($step, $session, $request->all());
        $data  = Validator::make($request->all(), $rules, $this->messagesForStep($step))
            ->validate();

        if ($step !== 'documents') {
            // Intermediate step — merge text values into the draft and
            // advance to the next step.
            $this->draft->merge($data);
            $nextStep = $this->nextStep($step);
            $this->draft->setCurrentStep($nextStep);
            return redirect()->route('concours.inscription.wizard.show', ['step' => $nextStep]);
        }

        // ---- Final step ----
        // Files are NOT in $request anymore; each was staged via its own
        // AJAX call (stageDocument). We only need to verify here that the
        // staging area has everything required and replay the files as
        // UploadedFile instances for the existing pipeline.
        $missing = $this->missingStagedDocuments();
        if ($missing !== []) {
            return back()
                ->withErrors(['documents' => "Pièces manquantes&nbsp;: " . implode(', ', $missing) . '.'])
                ->withInput();
        }

        $photoFile = $this->staged->asUploadedFile(InscriptionStagedDocuments::PHOTO_CODE);
        if ($photoFile === null) {
            return back()->withErrors(['photo' => 'La photo d\'identité est requise.']);
        }

        $stagedDocs = [];
        foreach ($this->staged->all() as $code => $_meta) {
            if ($code === InscriptionStagedDocuments::PHOTO_CODE) {
                continue;
            }
            $replayed = $this->staged->asUploadedFile($code);
            if ($replayed !== null) {
                $stagedDocs[$code] = $replayed;
            }
        }

        $payload = $this->draft->all();
        // Re-run the consolidated text-only check. Files were already
        // validated at stage time per slot.
        $textRules = $this->rulesForFinalSubmit($session, includeFiles: false, input: $payload);
        $finalValidator = Validator::make($payload, $textRules);
        if ($finalValidator->fails()) {
            $firstError = array_key_first($finalValidator->errors()->toArray());
            $blameStep  = $this->stepForField((string) $firstError);
            return redirect()->route('concours.inscription.wizard.show', ['step' => $blameStep])
                ->withErrors($finalValidator)
                ->withInput();
        }

        try {
            $candidat = $this->registration->register(new RegisterCandidatDto(
                concoursSessionId:       (string) $session->getKey(),
                centreId:                (string) $payload['centre_id'],
                nom:                     (string) $payload['nom'],
                prenom:                  (string) $payload['prenom'],
                dateNaissance:           (string) $payload['date_naissance'],
                lieuNaissance:           (string) $payload['lieu_naissance'],
                sexe:                    (string) $payload['sexe'],
                nationaliteId:           (string) $payload['nationalite_id'],
                email:                   (string) $payload['email'],
                telephone:               (string) $payload['telephone'],
                dejaBac:                 (bool)   $payload['deja_bac'],
                anneeBac:                $payload['deja_bac'] ? (int) ($payload['annee_bac'] ?? 0) : null,
                serieBacId:              (string) $payload['serie_bac_id'],
                bacLibelleLibre:         $payload['bac_libelle_libre'] ?? null,
                etablissementFrequente:  (string) $payload['etablissement_frequente'],
                sectionPremierChoixId:   (string) $payload['section_premier_choix_id'],
                sectionSecondChoixId:    $payload['section_second_choix_id'] ?? null,
                photo:                   $photoFile,
                documents:               $stagedDocs,
            ));
        } catch (InscriptionsClosedException $e) {
            return back()->withErrors(['inscription' => $e->getMessage()]);
        }

        // Wipe everything once the row is committed + files copied.
        $this->draft->reset();
        $this->staged->wipeAll();

        return redirect()->route('concours.inscription.success', ['matricule' => $candidat->matricule_public]);
    }

    /**
     * AJAX endpoint: receive ONE file (photo or a documents_requis.code)
     * and store it in the visitor's staging folder. Validation happens
     * per-slot here so each upload bounces fast on its own.
     *
     *   POST /inscription/documents/stage
     *     code=<slot> file=<single file>
     *
     * Returns: { ok, code, original_name, size_kb } or 422 with `error`.
     */
    public function stageDocument(Request $request): JsonResponse
    {
        $session = ConcoursSession::publicCurrent();
        if ($session === null || ! $session->isInscriptionOpen()) {
            return response()->json([
                'ok' => false,
                'error' => 'Les inscriptions sont closes.',
            ], 422);
        }

        // The candidat must have at least reached the documents step (i.e.
        // filled the earlier ones). Otherwise the staging folder fills with
        // files for sessions that may never complete.
        if (! $this->draft->hasReached('documents', self::STEPS)) {
            return response()->json([
                'ok' => false,
                'error' => 'Complétez les étapes précédentes avant d\'envoyer des pièces.',
            ], 422);
        }

        $data = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:60'],
            'file' => ['required', 'file', 'max:10240'],
        ])->validate();

        $code = (string) $data['code'];
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        // Per-slot constraints. Photo has its own (smaller) limit, every
        // other code maps to a documents_requis row whose taille_max_ko +
        // formats_acceptes we honour.
        if ($code === InscriptionStagedDocuments::PHOTO_CODE) {
            $perSlot = Validator::make(
                ['file' => $file],
                ['file' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']],
            );
        } else {
            $required = DocumentRequis::query()->where('active', true)->where('code', $code)->first();
            if ($required === null) {
                return response()->json([
                    'ok' => false,
                    'error' => "Code de pièce inconnu : {$code}.",
                ], 422);
            }
            $maxKo = (int) ($required->taille_max_ko ?? 10240);
            $formats = (array) ($required->formats_acceptes ?? ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
            $perSlot = Validator::make(
                ['file' => $file],
                ['file' => ['file', 'mimes:' . implode(',', $formats), 'max:' . $maxKo]],
            );
        }
        if ($perSlot->fails()) {
            return response()->json([
                'ok' => false,
                'error' => $perSlot->errors()->first('file') ?: 'Fichier rejeté.',
            ], 422);
        }

        $meta = $this->staged->stage($code, $file);

        return response()->json([
            'ok'            => true,
            'code'          => $meta['code'],
            'original_name' => $meta['original_name'],
            'size_kb'       => (int) round($meta['size_bytes'] / 1024),
        ]);
    }

    /**
     * AJAX endpoint: remove a single staged file.
     *
     *   DELETE /inscription/documents/stage/{code}
     */
    public function unstageDocument(string $code): JsonResponse
    {
        $this->staged->remove($code);
        return response()->json(['ok' => true, 'code' => $code]);
    }

    /** POST /inscription/{step}/back — go to the previous step without losing data. */
    public function back(string $step): RedirectResponse
    {
        $prev = $this->prevStep($step);
        $this->draft->setCurrentStep($prev);
        return redirect()->route('concours.inscription.wizard.show', ['step' => $prev]);
    }

    /** POST /inscription/reset — clear the draft and start over. */
    public function reset(): RedirectResponse
    {
        $this->draft->reset();
        $this->staged->wipeAll();
        return redirect()->route('concours.inscription.wizard.entry')
            ->with('status', 'Brouillon supprimé. Vous pouvez recommencer.');
    }

    // ----------------------------------------------------- internals

    /** @return array<string, mixed> */
    private function viewData(string $step, ConcoursSession $session): array
    {
        $draftValues = $this->draft->all();

        return [
            'session'        => $session,
            'currentStep'    => $step,
            'steps'          => self::STEPS,
            'stepLabels'     => $this->stepLabels(),
            'stepIndex'      => array_search($step, self::STEPS, true),
            'totalSteps'     => count(self::STEPS),
            'prevStep'       => $this->prevStep($step),
            'nextStep'       => $this->nextStep($step),
            'isFirst'        => $step === self::STEPS[0],
            'isLast'         => $step === self::STEPS[count(self::STEPS) - 1],
            'draft'          => $draftValues,
            // Reference data needed across multiple steps. We send them all
            // every render — they're small + cached.
            // Centres offered to the candidat: those ASSIGNED to this session
            // (concours_session_centres pivot). A freshly-created session has
            // none assigned yet, so we fall back to every active centre — an
            // empty dropdown would otherwise dead-end the inscription. centre_id
            // is validated against the global centres table either way, so the
            // fallback is safe.
            'centres'        => ($sc = $session->centres()->wherePivot('active', true)->orderBy('nom')->get())->isNotEmpty()
                                    ? $sc
                                    : \Modules\Concours\Models\Centre::query()->where('active', true)->orderBy('nom')->get(),
            'sections'       => Section::query()->where('ouvert_au_concours', true)->where('active', true)->orderBy('nom')->get(),
            'nationalites'   => Nationalite::query()->where('active', true)->orderBy('display_order')->orderBy('nom')->get(),
            'series'         => SerieBac::query()->where('active', true)->ordered()->get(),
            // Section-aware document list. The wizard's step ordering
            // guarantees the candidat has picked a section by the time they
            // reach the documents step, so we scope to "universal +
            // section-specific for their choice". Docs linked to OTHER
            // sections never appear.
            'documents'      => DocumentRequis::query()
                ->where('active', true)
                ->ordered()
                ->forSection((string) ($draftValues['section_premier_choix_id'] ?? ''))
                ->get(),
            // What's already staged — used by the documents step view to
            // render "✓ Uploaded" badges and a remove button per slot.
            'stagedFiles'    => $this->staged->all(),
            'photoCode'      => InscriptionStagedDocuments::PHOTO_CODE,
        ];
    }

    /** @return array<string, string> */
    private function stepLabels(): array
    {
        return [
            'identite'  => 'Identité',
            'contact'   => 'Contact',
            'bac'       => 'Baccalauréat',
            'choix'     => 'Choix & centre',
            'documents' => 'Documents',
        ];
    }

    /**
     * @param array<string, mixed> $input  current submission, used so the QA
     *                                      test candidate can re-register
     * @return array<string, mixed>
     */
    private function rulesForStep(string $step, ConcoursSession $session, array $input = []): array
    {
        $sessionId = (string) $session->getKey();

        return match ($step) {
            'identite' => [
                'nom'            => ['required', 'string', 'max:100'],
                'prenom'         => ['required', 'string', 'max:100'],
                'date_naissance' => ['required', 'date', 'before:today'],
                'lieu_naissance' => ['required', 'string', 'max:100'],
                'sexe'           => ['required', 'in:M,F'],
                'nationalite_id' => ['required', 'uuid', 'exists:nationalites,id'],
            ],
            'contact' => $this->contactRules($session, $input),
            'bac' => [
                'deja_bac'                => ['required', 'boolean'],
                'annee_bac'               => ['nullable', 'required_if:deja_bac,1', 'integer', 'min:1980', 'max:' . (int) date('Y')],
                'serie_bac_id'            => ['required', 'uuid', 'exists:series_bac,id'],
                'bac_libelle_libre'       => ['nullable', 'string', 'max:191'],
                'etablissement_frequente' => ['required', 'string', 'max:191'],
            ],
            'choix' => [
                'section_premier_choix_id' => ['required', 'uuid', 'exists:sections,id'],
                'section_second_choix_id'  => ['nullable', 'uuid', 'exists:sections,id', 'different:section_premier_choix_id'],
                'centre_id'                => ['required', 'uuid', 'exists:centres,id'],
            ],
            // Documents step has NO inline file rules — files are staged
            // out-of-band via /inscription/documents/stage. We instead
            // verify staging completeness inside submit() via
            // missingStagedDocuments(). Keeping the array entry (empty)
            // here just so the step name is still recognised.
            'documents' => [],
            default => [],
        };
    }

    /**
     * Holistic text-only check run at the very last moment, just before we
     * hand the payload to CandidatRegistrationService. Files were already
     * validated per-slot when they were staged.
     *
     * The `$includeFiles` parameter remains accepted for the now-removed
     * inline-file mode but defaults to false; it's a no-op left in place so
     * future callers don't break if someone wants to reintroduce a unified
     * submission path.
     *
     * @return array<string, mixed>
     */
    /**
     * Email + telephone are unique per session. The singleton QA test candidate
     * (config('concours.test.email')) re-registers repeatedly, so when the
     * submitted email is the test address we scope both unique checks to IGNORE
     * its own existing row (mirroring the modification flow) — otherwise the
     * second registration would be rejected as a duplicate.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function contactRules(ConcoursSession $session, array $input): array
    {
        $sessionId = (string) $session->getKey();

        $ignore = 'NULL';
        if (\Modules\Concours\Models\Candidat::isTestEmail($input['email'] ?? null)) {
            $existingId = \Modules\Concours\Models\Candidat::query()
                ->where('concours_session_id', $sessionId)
                ->where('is_test', true)
                ->value('id');
            if ($existingId !== null) {
                $ignore = (string) $existingId;
            }
        }

        return [
            'email' => [
                'required', 'email:rfc', 'max:191',
                "unique:candidats,email,{$ignore},id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],
            'telephone' => [
                'required', 'string', 'regex:' . PhoneNumber::REGEX,
                "unique:candidats,telephone,{$ignore},id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],
        ];
    }

    private function rulesForFinalSubmit(ConcoursSession $session, bool $includeFiles = false, array $input = []): array
    {
        $merged = array_merge(
            ...array_map(
                fn (string $s) => $this->rulesForStep($s, $session, $input),
                self::STEPS,
            ),
        );
        unset($merged['photo'], $merged['documents'], $merged['documents.*']);
        return $merged;
    }

    /**
     * Names of the documents (libellés) that are still missing from the
     * staging area. Photo is reported as "Photo d'identité" — every other
     * required documents_requis row is reported by its libellé.
     *
     * @return list<string>
     */
    private function missingStagedDocuments(): array
    {
        $missing = [];
        if (! $this->staged->has(InscriptionStagedDocuments::PHOTO_CODE)) {
            $missing[] = "Photo d'identité";
        }
        // Only require docs that actually apply to this candidat's chosen
        // section. Section-specific docs for OTHER sections are silently
        // ignored — they were never shown in the UI either.
        $sectionId = (string) $this->draft->get('section_premier_choix_id', '');
        $requiredCodes = DocumentRequis::query()
            ->where('active', true)
            ->where('obligatoire', true)
            ->forSection($sectionId)
            ->get(['id', 'code', 'libelle']);
        foreach ($requiredCodes as $req) {
            if (! $this->staged->has($req->code)) {
                $missing[] = $req->libelle;
            }
        }
        return $missing;
    }

    /** @return array<string, string> */
    private function messagesForStep(string $step): array
    {
        return [
            'date_naissance.before'             => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'annee_bac.required_if'             => 'Précisez l\'année d\'obtention de votre BAC.',
            'section_second_choix_id.different' => 'Le second choix doit être différent du premier.',
            'email.unique'                      => 'Un dossier existe déjà avec cet email pour ce concours.',
            'telephone.unique'                  => 'Un dossier existe déjà avec ce téléphone pour ce concours.',
        ];
    }

    private function nextStep(string $current): string
    {
        $idx = array_search($current, self::STEPS, true);
        if ($idx === false || $idx >= count(self::STEPS) - 1) {
            return self::STEPS[count(self::STEPS) - 1];
        }
        return self::STEPS[$idx + 1];
    }

    private function prevStep(string $current): string
    {
        $idx = array_search($current, self::STEPS, true);
        if ($idx === false || $idx <= 0) {
            return self::STEPS[0];
        }
        return self::STEPS[$idx - 1];
    }

    /**
     * Which step "owns" a given field name? Used to bounce the user back
     * to the right place if final-submit validation finds a stale value.
     */
    private function stepForField(string $field): string
    {
        // documents.0 / photo / etc. — strip the array suffix.
        $field = strtok($field, '.');
        return match ($field) {
            'nom', 'prenom', 'date_naissance', 'lieu_naissance', 'sexe', 'nationalite_id' => 'identite',
            'email', 'telephone'                                                          => 'contact',
            'deja_bac', 'annee_bac', 'serie_bac_id', 'bac_libelle_libre',
            'etablissement_frequente'                                                     => 'bac',
            'section_premier_choix_id', 'section_second_choix_id', 'centre_id'            => 'choix',
            'photo', 'documents'                                                          => 'documents',
            default                                                                       => 'identite',
        };
    }
}
