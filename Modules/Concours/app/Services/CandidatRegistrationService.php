<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Notification;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Concours\DTOs\RegisterCandidatDto;
use Modules\Concours\Exceptions\InscriptionsClosedException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Notifications\InscriptionConfirmedNotification;
use Modules\Referentiels\Models\DocumentRequis;

/**
 * Orchestrates the public candidate registration pipeline:
 *
 *   1. Verify the session is open to inscriptions today.
 *   2. Create the Candidat row (status = 'non') in a transaction.
 *   3. Store the photo + the declared document files keyed by code.
 *   4. Return the persisted Candidat (with matricule_public set).
 *
 * The matricule is short, urlsafe, and unique — used in the verification
 * URL the candidate gets at the end of the flow.
 */
final class CandidatRegistrationService
{
    public function __construct(
        private readonly CandidatDocumentService $documents,
        private readonly ConnectionInterface $db,
    ) {}

    public function register(RegisterCandidatDto $dto): Candidat
    {
        $session = ConcoursSession::query()->findOrFail($dto->concoursSessionId);

        if (! $session->isInscriptionOpen()) {
            throw InscriptionsClosedException::outsideDateRange();
        }

        $documentRequisByCode = DocumentRequis::query()
            ->where('active', true)
            ->whereIn('code', array_keys($dto->documents))
            ->get()
            ->keyBy('code');

        $candidat = $this->db->transaction(function () use ($dto, $session): Candidat {
            // Matricule generation happens inside the transaction so the
            // advisory lock taken by generateMatricule() is released only
            // after the row commits — that's what makes concurrent
            // registrations safely serialise on the per-session counter.
            return Candidat::query()->create([
                'concours_session_id'      => $session->getKey(),
                'centre_id'                => $dto->centreId,
                'nom'                      => mb_strtoupper(trim($dto->nom)),
                'prenom'                   => $this->ucWords(trim($dto->prenom)),
                'date_naissance'           => $dto->dateNaissance,
                'lieu_naissance'           => trim($dto->lieuNaissance),
                'sexe'                     => $dto->sexe,
                'nationalite_id'           => $dto->nationaliteId,
                'email'                    => mb_strtolower(trim($dto->email)),
                'telephone'                => $this->normalizePhone($dto->telephone),
                'deja_bac'                 => $dto->dejaBac,
                'annee_bac'                => $dto->dejaBac ? $dto->anneeBac : null,
                'serie_bac_id'             => $dto->serieBacId,
                'bac_libelle_libre'        => $dto->bacLibelleLibre,
                'etablissement_frequente'  => trim($dto->etablissementFrequente),
                'section_premier_choix_id' => $dto->sectionPremierChoixId,
                'section_second_choix_id'  => $dto->sectionSecondChoixId,
                'statut'                   => Candidat::STATUS_NON,
                'matricule_public'         => $this->generateMatricule($session),
            ]);
        });

        // File storage happens OUTSIDE the transaction — Postgres can't roll
        // back a file write, and we'd rather have an orphan file than a row
        // referencing a non-existent path.
        $anneeCode = $session->anneeAcademique?->code ?? date('Y');

        $this->documents->storePhoto($candidat, $dto->photo, $anneeCode);

        foreach ($dto->documents as $code => $file) {
            $required = $documentRequisByCode->get($code);
            if ($required === null) {
                continue; // unknown code → silently ignore (validation layer should have caught it)
            }
            $this->documents->storeDocument($candidat, $required, $file, $anneeCode);
        }

        $candidat = $candidat->fresh();

        // Fire-and-forget confirmation email. Queued via the ShouldQueue
        // marker on the notification, so the response isn't blocked by SMTP.
        // Legacy candidats imported without an email address (matched by the
        // `legacy-*@cuk.local` synthetic) silently skip — they were already
        // notified out-of-band when they enrolled the first time.
        if ($candidat !== null
            && $candidat->email !== null
            && $candidat->email !== ''
            && ! str_ends_with($candidat->email, '@cuk.local')
        ) {
            Notification::route('mail', $candidat->email)
                ->notify(new InscriptionConfirmedNotification(
                    candidat: $candidat,
                    feeAmount: (int) ($session->fraisInscription() ?? 10300),
                    currency: 'FCFA',
                ));
        }

        return $candidat;
    }

    /**
     * Per-session sequential matricule: `CUK-{YYYY}-{NNNNN}`.
     *
     *   - YYYY is the year of `date_concours` (the exam date the session
     *     was created for). Stable for the lifetime of the session even if
     *     the dates are nudged.
     *   - NNNNN is a 5-digit, zero-padded, monotonically-increasing
     *     counter per session, starting at 1. We compute it as
     *     MAX(existing_suffix) + 1 (not COUNT(*) + 1) so a soft-deleted
     *     row never causes a duplicate.
     *
     * Concurrency: two simultaneous registrations would race on the
     * counter. We take a Postgres transaction-scoped advisory lock keyed by
     * a hash of the session UUID, which makes generation single-writer
     * per session. The lock is released automatically when the outer
     * transaction (in register()) commits, so we never leak it.
     *
     * Legacy `CUK-[A-Z0-9]{12}` matricules that came in via the legacy
     * importer are intentionally ignored when computing the next number —
     * they don't follow the per-session scheme and we don't want them
     * polluting the counter.
     */
    private function generateMatricule(ConcoursSession $session): string
    {
        $year   = optional($session->date_concours)->format('Y') ?? date('Y');
        $prefix = "CUK-{$year}-";

        // 32-bit hash fits comfortably in Postgres' bigint advisory lock arg.
        $lockKey = crc32((string) $session->getKey());
        $this->db->statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        // Highest existing suffix for THIS session in the new format.
        // ORDER BY DESC on the matricule string works because all entries
        // share the same prefix length and the 5-digit number is
        // zero-padded, so lexicographic order matches numeric order.
        $last = Candidat::query()
            ->where('concours_session_id', $session->getKey())
            ->where('matricule_public', 'like', $prefix . '%')
            ->orderByDesc('matricule_public')
            ->value('matricule_public');

        $next = $last === null
            ? 1
            : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function normalizePhone(string $raw): string
    {
        $trimmed = preg_replace('/\s+/', '', trim($raw)) ?? $raw;
        return mb_substr($trimmed, 0, 30);
    }

    private function ucWords(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
