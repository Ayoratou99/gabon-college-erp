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
use Modules\Concours\DTOs\UpdateCandidatDto;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Services\CandidatModificationService;
use Modules\Concours\Services\Public\InscriptionStagedDocuments;
use Modules\Concours\Services\Public\ModificationDraft;
use Modules\Concours\Support\PhoneNumber;
use Modules\Referentiels\Models\DocumentRequis;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;

/**
 * Post-rejection modification wizard.
 *
 * Lives alongside the inscription wizard and uses the same layout +
 * step partials. The differences from the inscription flow are:
 *
 *   - Draft is pre-filled from the existing Candidat row (not blank).
 *   - Files are OPTIONAL replacements: an existing photo or document
 *     stays in place unless the candidat uploads a new one for that slot.
 *   - The route is gated on a one-shot session token (matching what
 *     `CandidatLookupController::submitLookup` already plants).
 *   - The final submit calls CandidatModificationService::apply with
 *     channel=public, which also resets the dossier to status=non so
 *     the back-office re-validates it.
 *
 * Reuses InscriptionStagedDocuments for files (same disk folder) and a
 * dedicated ModificationDraft for text values (separate session keys so
 * an in-progress inscription draft isn't clobbered).
 */
final class CandidatModificationWizardController extends Controller
{
    /** @var list<string> */
    private const STEPS = ['identite', 'contact', 'bac', 'choix', 'documents'];

    public function __construct(
        private readonly CandidatModificationService $modifications,
        private readonly ModificationDraft $draft,
        private readonly InscriptionStagedDocuments $staged,
    ) {}

    public function entry(Request $request, string $token): RedirectResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            return $candidat;
        }
        $step = $this->draft->currentStep() ?? self::STEPS[0];
        return redirect()->route('concours.public.modify.wizard.show', [
            'token' => $token,
            'step'  => $step,
        ]);
    }

    public function show(Request $request, string $token, string $step): View|RedirectResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            return $candidat;
        }
        if (! ($candidat->session?->isInscriptionOpen() ?? false)) {
            return redirect()->route('concours.public.status.form')
                ->withErrors(['matricule' => 'Les inscriptions de cette session sont closes — modification impossible.']);
        }
        if (! in_array($step, self::STEPS, true)) {
            return redirect()->route('concours.public.modify.wizard.entry', ['token' => $token]);
        }

        return view('concours::public.registration.wizard', $this->viewData($candidat, $token, $step));
    }

    public function submit(Request $request, string $token, string $step): RedirectResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            return $candidat;
        }
        if (! ($candidat->session?->isInscriptionOpen() ?? false)) {
            return redirect()->route('concours.public.status.form')
                ->withErrors(['matricule' => 'Les inscriptions de cette session sont closes — modification impossible.']);
        }
        if (! in_array($step, self::STEPS, true)) {
            return redirect()->route('concours.public.modify.wizard.entry', ['token' => $token]);
        }

        // Canonicalise the phone before validation (strip separators), matching
        // the inscription flow so stored / looked-up numbers stay consistent.
        if ($request->has('telephone')) {
            $request->merge(['telephone' => PhoneNumber::normalize($request->input('telephone'))]);
        }

        $rules = $this->rulesForStep($step, $candidat);
        $data  = Validator::make($request->all(), $rules, [
            'accept_conditions.accepted' => 'Vous devez accepter les conditions d\'utilisation et la politique de confidentialité pour soumettre votre dossier.',
        ])->validate();

        if ($step !== 'documents') {
            $this->draft->merge($data);
            $next = $this->nextStep($step);
            $this->draft->setCurrentStep($next);
            return redirect()->route('concours.public.modify.wizard.show', ['token' => $token, 'step' => $next]);
        }

        // -------- Final submission --------
        // Photo + documents are OPTIONAL replacements. We only pass to the
        // service the slots that were re-staged; existing files are
        // preserved by CandidatModificationService::apply (it only writes
        // the ones present in the DTO).
        $photoFile = $this->staged->asUploadedFile(InscriptionStagedDocuments::PHOTO_CODE);
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

        // Merge candidat baseline + draft → the DTO uses the merged values
        // so an untouched field still ships its current value and the diff
        // engine simply emits no audit row for it.
        $merged = array_replace($this->candidatAsDraft($candidat), $this->draft->all());

        $this->modifications->apply(new UpdateCandidatDto(
            candidatId:             (string) $candidat->getKey(),
            channel:                CandidatModification::CHANNEL_PUBLIC,
            userId:                 null,
            ipAddress:              (string) $request->ip(),
            reason:                 'Modification publique après rejet',
            nom:                    (string) ($merged['nom']                       ?? $candidat->nom),
            prenom:                 (string) ($merged['prenom']                    ?? $candidat->prenom),
            dateNaissance:          (string) ($merged['date_naissance']            ?? optional($candidat->date_naissance)->format('Y-m-d')),
            lieuNaissance:          (string) ($merged['lieu_naissance']            ?? $candidat->lieu_naissance),
            sexe:                   (string) ($merged['sexe']                      ?? $candidat->sexe),
            nationaliteId:          (string) ($merged['nationalite_id']            ?? $candidat->nationalite_id),
            email:                  (string) ($merged['email']                     ?? $candidat->email),
            telephone:              (string) ($merged['telephone']                 ?? $candidat->telephone),
            dejaBac:                (bool)   ($merged['deja_bac']                  ?? $candidat->deja_bac),
            anneeBac:               isset($merged['annee_bac']) ? (int) $merged['annee_bac'] : $candidat->annee_bac,
            serieBacId:             (string) ($merged['serie_bac_id']              ?? $candidat->serie_bac_id),
            bacLibelleLibre:        $merged['bac_libelle_libre']                   ?? $candidat->bac_libelle_libre,
            etablissementFrequente: (string) ($merged['etablissement_frequente']   ?? $candidat->etablissement_frequente),
            sectionPremierChoixId:  (string) ($merged['section_premier_choix_id']  ?? $candidat->section_premier_choix_id),
            sectionSecondChoixId:   $merged['section_second_choix_id']             ?? $candidat->section_second_choix_id,
            centreId:               (string) ($merged['centre_id']                 ?? $candidat->centre_id),
            photo:                  $photoFile,
            documents:              $stagedDocs ?: null,
        ));

        // One-shot token + draft + staging — burn everything once the
        // dossier has been re-submitted.
        $this->forgetToken($request);
        $this->draft->reset();
        $this->staged->wipeAll();

        return redirect()->route('concours.public.modify.success', $candidat->matricule_public);
    }

    public function back(Request $request, string $token, string $step): RedirectResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            return $candidat;
        }
        $prev = $this->prevStep($step);
        $this->draft->setCurrentStep($prev);
        return redirect()->route('concours.public.modify.wizard.show', ['token' => $token, 'step' => $prev]);
    }

    public function reset(Request $request, string $token): RedirectResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            return $candidat;
        }
        $this->draft->reset();
        $this->staged->wipeAll();
        return redirect()->route('concours.public.modify.wizard.entry', ['token' => $token])
            ->with('status', 'Brouillon supprimé — vos valeurs ont été restaurées à celles de votre dossier.');
    }

    /**
     * AJAX stage endpoint mirror — same payload format as inscription,
     * routed under the token so it's session-bound and unique per modify
     * round.
     */
    public function stageDocument(Request $request, string $token): JsonResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            // Token expired or wrong — surface that to the JS client.
            return response()->json(['ok' => false, 'error' => 'Session expirée. Recommencez la récupération de dossier.'], 419);
        }

        $data = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:60'],
            'file' => ['required', 'file', 'max:10240'],
        ])->validate();

        $code = (string) $data['code'];
        $file = $request->file('file');

        if ($code === InscriptionStagedDocuments::PHOTO_CODE) {
            $perSlot = Validator::make(['file' => $file],
                ['file' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']],
            );
        } else {
            $required = DocumentRequis::query()->where('active', true)->where('code', $code)->first();
            if ($required === null) {
                return response()->json(['ok' => false, 'error' => "Code de pièce inconnu : {$code}."], 422);
            }
            $maxKo = (int) ($required->taille_max_ko ?? 10240);
            $formats = (array) ($required->formats_acceptes ?? ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
            $perSlot = Validator::make(['file' => $file],
                ['file' => ['file', 'mimes:' . implode(',', $formats), 'max:' . $maxKo]],
            );
        }
        if ($perSlot->fails()) {
            return response()->json(['ok' => false, 'error' => $perSlot->errors()->first('file') ?: 'Fichier rejeté.'], 422);
        }

        $meta = $this->staged->stage($code, $file);
        return response()->json([
            'ok'            => true,
            'code'          => $meta['code'],
            'original_name' => $meta['original_name'],
            'size_kb'       => (int) round($meta['size_bytes'] / 1024),
        ]);
    }

    public function unstageDocument(Request $request, string $token, string $code): JsonResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            return response()->json(['ok' => false, 'error' => 'Session expirée.'], 419);
        }
        $this->staged->remove($code);
        return response()->json(['ok' => true, 'code' => $code]);
    }

    // ----------------------------------------------------- helpers

    /** @return array<string, mixed> */
    private function viewData(Candidat $candidat, string $token, string $step): array
    {
        // Pre-fill draft on the very first show so subsequent steps see the
        // candidat's existing values without needing to read them via
        // fallbacks everywhere.
        if ($this->draft->currentStep() === null && $this->draft->all() === []) {
            $this->draft->merge($this->candidatAsDraft($candidat));
            $this->draft->setCurrentStep(self::STEPS[0]);
        }

        $session = $candidat->session;

        return [
            // Hero overrides — title + colour cues that this is the
            // modification flow, not a fresh inscription.
            'heroTitle'    => 'Modifier mon dossier',
            'heroIcon'     => 'fas fa-folder-open',
            'heroSubtitle' => sprintf(
                'Dossier <strong>%s</strong> &middot; session <strong>%s</strong>',
                e($candidat->matricule_public),
                e($session?->libelle ?? '?'),
            ),
            'submitLabel'  => 'Renvoyer mon dossier corrigé',
            'submitRoute'  => 'concours.public.modify.wizard.submit',
            'backRoute'    => 'concours.public.modify.wizard.back',
            'resetRoute'   => 'concours.public.modify.wizard.reset',
            'routeParams'  => ['token' => $token],

            // Standard wizard shape.
            'session'      => $session,
            'currentStep'  => $step,
            'steps'        => self::STEPS,
            'stepLabels'   => $this->stepLabels(),
            'stepIndex'    => array_search($step, self::STEPS, true),
            'totalSteps'   => count(self::STEPS),
            'prevStep'     => $this->prevStep($step),
            'nextStep'     => $this->nextStep($step),
            'isFirst'      => $step === self::STEPS[0],
            'isLast'       => $step === self::STEPS[count(self::STEPS) - 1],
            'draft'        => $this->draft->all(),

            // Centres offered to the candidat: those ASSIGNED to this session
            // (concours_session_centres pivot). A session with no centres
            // assigned would otherwise render an empty dropdown that dead-ends
            // the « Choix & centre » step — so we fall back to every active
            // centre. centre_id is validated against the global centres table
            // either way, and the candidat is free to change his centre here.
            'centres'      => $session
                && ($sc = $session->centres()->wherePivot('active', true)->orderBy('nom')->get())->isNotEmpty()
                    ? $sc
                    : \Modules\Concours\Models\Centre::query()->where('active', true)->orderBy('nom')->get(),
            'sections'     => Section::query()->where('ouvert_au_concours', true)->where('active', true)->orderBy('nom')->get(),
            'nationalites' => Nationalite::query()->where('active', true)->orderBy('display_order')->orderBy('nom')->get(),
            'series'       => SerieBac::query()->where('active', true)->ordered()->get(),
            // Section-aware document list. We read the section from the
            // draft first (the candidat may be in the process of changing
            // it inside this wizard), falling back to the candidat row's
            // current value if no draft override exists yet.
            'documents'    => DocumentRequis::query()
                ->where('active', true)
                ->ordered()
                ->forSection((string) ($this->draft->get('section_premier_choix_id') ?? $candidat->section_premier_choix_id))
                ->get(),

            // Documents-step specific.
            'stagedFiles'  => $this->staged->all(),
            'photoCode'    => InscriptionStagedDocuments::PHOTO_CODE,
            'stageUrl'     => route('concours.public.modify.wizard.stage', ['token' => $token]),
            'unstageUrl'   => url('/modifier-dossier/' . $token . '/documents/stage'),
            'existingDocuments' => $this->existingDocumentsMap($candidat),
        ];
    }

    /**
     * Project the candidat's columns into the same key set the draft uses,
     * so the step views (which read from `$draft[...]`) work uniformly
     * whether the value came from the draft or from the row.
     *
     * @return array<string, mixed>
     */
    private function candidatAsDraft(Candidat $candidat): array
    {
        return [
            'nom'                      => (string) $candidat->nom,
            'prenom'                   => (string) $candidat->prenom,
            'date_naissance'           => optional($candidat->date_naissance)->format('Y-m-d') ?? '',
            'lieu_naissance'           => (string) $candidat->lieu_naissance,
            'sexe'                     => (string) $candidat->sexe,
            'nationalite_id'           => (string) $candidat->nationalite_id,
            'email'                    => (string) $candidat->email,
            'telephone'                => (string) $candidat->telephone,
            'deja_bac'                 => (bool) $candidat->deja_bac,
            'annee_bac'                => $candidat->annee_bac,
            'serie_bac_id'             => (string) $candidat->serie_bac_id,
            'bac_libelle_libre'        => (string) ($candidat->bac_libelle_libre ?? ''),
            'etablissement_frequente'  => (string) $candidat->etablissement_frequente,
            'section_premier_choix_id' => (string) $candidat->section_premier_choix_id,
            'section_second_choix_id'  => (string) ($candidat->section_second_choix_id ?? ''),
            'centre_id'                => (string) $candidat->centre_id,
        ];
    }

    /**
     * Map of currently-on-file documents, keyed by documents_requis.code.
     * Used by the documents step view to show "existing version: foo.pdf"
     * next to each slot, make replacements optional, and (when the slot
     * was flagged `a_refaire` by chef-centre) prominently highlight it
     * with the rejection comment so the candidat knows exactly what to
     * replace.
     *
     * @return array<string, array{name: string, review_status: string, review_comment: ?string}>
     */
    private function existingDocumentsMap(Candidat $candidat): array
    {
        $candidat->loadMissing('documents.documentRequis:id,code,libelle');
        $map = [];

        // The photo doesn't go through the per-doc review workflow (no
        // candidat_documents row for it), so we surface it without a
        // review state — the modify step still lets the candidat swap it.
        if ($candidat->photo_path) {
            $map[InscriptionStagedDocuments::PHOTO_CODE] = [
                'name'           => basename($candidat->photo_path),
                'review_status'  => 'en_attente',
                'review_comment' => null,
            ];
        }

        foreach ($candidat->documents as $doc) {
            $code = $doc->documentRequis?->code;
            if ($code !== null) {
                $map[$code] = [
                    'name'           => $doc->original_name ?: basename((string) $doc->file_path),
                    'review_status'  => (string) ($doc->review_status ?? 'en_attente'),
                    'review_comment' => $doc->review_comment,
                ];
            }
        }
        return $map;
    }

    /** @return array<string, list<string>> */
    private function rulesForStep(string $step, Candidat $candidat): array
    {
        $candidatId = (string) $candidat->getKey();
        $sessionId  = (string) $candidat->concours_session_id;

        return match ($step) {
            'identite' => [
                'nom'            => ['required', 'string', 'max:100'],
                'prenom'         => ['required', 'string', 'max:100'],
                'date_naissance' => ['required', 'date', 'before:today'],
                'lieu_naissance' => ['required', 'string', 'max:100'],
                'sexe'           => ['required', 'in:M,F'],
                'nationalite_id' => ['required', 'uuid', 'exists:nationalites,id'],
            ],
            'contact' => [
                'email'     => [
                    'required', 'email:rfc', 'max:191',
                    "unique:candidats,email,{$candidatId},id,concours_session_id,{$sessionId},deleted_at,NULL",
                ],
                'telephone' => [
                    'required', 'string', 'regex:' . PhoneNumber::REGEX,
                    "unique:candidats,telephone,{$candidatId},id,concours_session_id,{$sessionId},deleted_at,NULL",
                ],
            ],
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
            // Files are staged out-of-band; the only inline rule is the
            // mandatory consent tick submitted with this final step.
            'documents' => ['accept_conditions' => ['accepted']],
            default     => [],
        };
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

    private function nextStep(string $current): string
    {
        $idx = array_search($current, self::STEPS, true);
        return self::STEPS[min(($idx ?: 0) + 1, count(self::STEPS) - 1)];
    }

    private function prevStep(string $current): string
    {
        $idx = array_search($current, self::STEPS, true);
        return self::STEPS[max(($idx ?: 0) - 1, 0)];
    }

    /**
     * Resolve the token-gated candidat. Mirrors the existing
     * CandidatLookupController::resolveTokenized so the wizard plugs in
     * without needing the lookup controller to change.
     */
    private function resolveTokenized(Request $request, string $token): Candidat|RedirectResponse
    {
        $keys       = (array) config('concours.public_lookup.session_keys');
        $stored     = $request->session()->get($keys['modification_token']);
        $candidatId = $request->session()->get($keys['modification_candidat']);
        $expires    = $request->session()->get($keys['modification_expires']);

        $expired = ! is_int($expires) || $expires < time();
        if (! is_string($stored) || $stored !== $token || $candidatId === null || $expired) {
            return redirect()->route('concours.public.lookup.form')
                ->withErrors(['email' => 'Votre session de modification a expiré. Veuillez recommencer.']);
        }

        $candidat = Candidat::query()->find($candidatId);
        return $candidat ?? redirect()->route('concours.public.lookup.form');
    }

    private function forgetToken(Request $request): void
    {
        $keys = (array) config('concours.public_lookup.session_keys');
        $request->session()->forget([
            $keys['modification_token'],
            $keys['modification_candidat'],
            $keys['modification_expires'],
        ]);
    }
}
