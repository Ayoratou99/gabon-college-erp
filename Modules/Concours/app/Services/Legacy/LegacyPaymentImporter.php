<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Payment;
use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Imports `payments` → new `payments`.
 *
 * The legacy enum statuses (INIT/PENDING/PAID/FAILED) map 1:1 to our
 * constants. `signature_verified` defaults to false for historical rows —
 * we have no way to retroactively prove the original HMAC.
 */
final class LegacyPaymentImporter
{
    public function import(
        LegacyDumpParser $parser,
        LegacyImportContext $context,
        LegacyImportReport $report,
        bool $dryRun,
    ): void {
        foreach ($parser->rowsOf('payments') as $row) {
            $legacyId    = (int) ($row['id'] ?? 0);
            $legacyEtuId = (int) ($row['id_etu'] ?? 0);
            $reference   = (string) ($row['external_reference'] ?? '');

            if ($legacyId === 0 || $legacyEtuId === 0 || $reference === '') {
                $report->skippedOne('payments');
                continue;
            }

            try {
                $existing = Payment::query()->where('legacy_id', $legacyId)->first();
                if ($existing !== null) {
                    $report->skippedOne('payments');
                    continue;
                }
                if (Payment::query()->where('external_reference', $reference)->exists()) {
                    $report->skippedOne('payments');
                    continue;
                }

                $candidatId = $context->candidatByLegacyId[$legacyEtuId] ?? null;
                if ($candidatId === null) {
                    if (! isset($context->legacyEtudiantIds[$legacyEtuId])) {
                        // Payment row points at an etudiant that no longer
                        // exists in the source dump — orphan, not an error.
                        $report->skippedOne('payments');
                    } else {
                        $report->failedOne('payments', (string) $legacyId, 'Candidat non importé.');
                    }
                    continue;
                }

                $candidat = Candidat::query()->find($candidatId);
                if ($candidat === null) {
                    $report->failedOne('payments', (string) $legacyId, 'Candidat introuvable.');
                    continue;
                }

                $status = $this->mapStatus((string) ($row['status'] ?? 'INIT'));

                $payment = new Payment([
                    'candidat_id'         => $candidatId,
                    'concours_session_id' => $candidat->concours_session_id,
                    'amount'              => (int) ($row['amount'] ?? 0),
                    'currency'            => 'FCFA',
                    'ebilling_id'         => $row['ebilling_id'] ?: null,
                    'external_reference'  => $reference,
                    'status'              => $status,
                    'payload'             => $this->decodePayload($row['payload'] ?? null),
                    'paid_at'             => $status === Payment::STATUS_PAID ? ($row['created_at'] ?? now()) : null,
                    'signature_verified'  => false,
                ]);
                $payment->forceFill(['legacy_id' => $legacyId]);

                if (! $dryRun) {
                    $payment->save();
                }
                $report->importedOne('payments');
            } catch (\Throwable $e) {
                $report->failedOne('payments', (string) $legacyId, $e->getMessage());
            }
        }
    }

    private function mapStatus(string $legacy): string
    {
        return match ($legacy) {
            'PAID'    => Payment::STATUS_PAID,
            'PENDING' => Payment::STATUS_PENDING,
            'FAILED'  => Payment::STATUS_FAILED,
            default   => Payment::STATUS_INIT,
        };
    }

    private function decodePayload(mixed $raw): ?array
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return ['_raw' => mb_substr($raw, 0, 4000)];
        }
    }
}
