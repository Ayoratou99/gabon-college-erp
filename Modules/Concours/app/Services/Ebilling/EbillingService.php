<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Ebilling;

use Illuminate\Http\Client\Factory as HttpClient;
use Modules\Concours\Exceptions\EbillingException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;
use Modules\Concours\Models\Payment;
use Modules\Concours\Notifications\PaymentConfirmedNotification;

/**
 * Thin wrapper over the eBilling REST API.
 *
 *   createInvoice($candidat, $amount, $reference) → eBilling bill_id
 *   markPaid($payment, $payload, $ip)              → Payment
 *
 * Configuration lives in .env / config('concours.ebilling.*'). These are
 * operational credentials — rotating them is a deploy concern (update .env
 * + rebuild) rather than something an admin clicks in the back office.
 *
 * Callback authenticity: eBilling does **not** sign their callback body or
 * send a header secret, so the legacy HMAC check has been removed. We
 * verify inbound callbacks by decrypting `external_reference` with
 * PaymentReferenceCipher (AES-256-GCM, key in EBILLING_REFERENCE_KEY).
 * That check lives in PaymentCallbackController; this service no longer
 * has a verifyCallback() method.
 */
final class EbillingService
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function createInvoice(Candidat $candidat, int $amount, string $externalReference): string
    {
        $baseUrl   = (string) config('concours.ebilling.base_url');
        $username  = (string) config('concours.ebilling.username');
        $sharedKey = (string) config('concours.ebilling.shared_key');

        if ($baseUrl === '' || $username === '' || $sharedKey === '') {
            throw EbillingException::configurationMissing('base_url|username|shared_key');
        }

        // Refuse to bill candidats whose contact info is a legacy import
        // synthetic placeholder. `LegacyCandidatImporter` writes
        // `legacy-{id}@cuk.local` / `LEGACY-{id}` when the source CSV is
        // missing one of those fields — eBilling would silently accept the
        // garbage and there'd be no way for the candidat to actually pay.
        // In practice these candidats are already 'valid'/'admis' so this
        // is defence in depth, but a clear error beats a phantom invoice.
        $email = (string) $candidat->email;
        $tel   = (string) $candidat->telephone;
        if (str_ends_with($email, '@cuk.local') || str_starts_with($tel, 'LEGACY-')) {
            throw EbillingException::configurationMissing('candidat.contact (legacy placeholder)');
        }

        $response = $this->http
            ->withBasicAuth($username, $sharedKey)
            ->asJson()
            ->timeout((int) config('concours.ebilling.http_timeout', 60))
            ->post(rtrim($baseUrl, '/') . '/api/v1/merchant/e_bills', [
                'payer_email'        => $email,
                'payer_msisdn'       => $tel,
                // French formal convention: NOM Prénom. eBilling renders
                // this on the receipt the candidat sees on the portal.
                'payer_name'         => trim($candidat->nom . ' ' . $candidat->prenom),
                'amount'             => $amount,
                'short_description'  => sprintf(
                    'Frais inscription concours %s — %s %s',
                    $candidat->session?->code ?? '',
                    $candidat->nom,
                    $candidat->prenom,
                ),
                'external_reference' => $externalReference,
                'expiry_period'      => 60,
            ]);

        $billId = $response->successful() ? $response->json('e_bill.bill_id') : null;

        if (! is_string($billId) || $billId === '') {
            // Surface the FULL eBilling response body — that's the real reason
            // (e.g. "amount must be greater than…", "invalid msisdn"). Fall back
            // to the HTTP reason phrase only when the body is genuinely empty.
            $body = trim((string) $response->body());
            if ($body === '') {
                $body = '(corps de réponse vide) ' . trim((string) $response->reason());
            }

            throw EbillingException::invoiceCreationFailed($response->status(), $body);
        }

        return $billId;
    }

    public function markPaid(Payment $payment, array $payload, string $ipAddress): Payment
    {
        $payment->forceFill([
            'status'             => Payment::STATUS_PAID,
            'paid_at'            => now(),
            'payload'            => $payload,
            'callback_ip'        => $ipAddress,
            'signature_verified' => true,
        ])->save();

        // Flip the candidate to "valid" — they're done. Log the transition
        // to the dossier audit trail so the back-office timeline shows that
        // the change came from the eBilling callback (not a human admin).
        $candidat = $payment->candidat;
        if ($candidat !== null && $candidat->statut !== Candidat::STATUS_VALID) {
            $oldStatut = $candidat->statut;
            $candidat->forceFill([
                'statut'    => Candidat::STATUS_VALID,
                'valide_at' => now(),
            ])->save();
            CandidatModification::query()->create([
                'candidat_id' => $candidat->getKey(),
                'user_id'     => null,
                'channel'     => CandidatModification::CHANNEL_SYSTEM,
                'field'       => 'statut',
                'old_value'   => $oldStatut,
                'new_value'   => Candidat::STATUS_VALID,
                'reason'      => 'Paiement confirmé (eBilling — ref ' . ($payment->external_reference ?? '?') . ')',
                'ip_address'  => $ipAddress,
                'changed_at'  => now(),
            ]);
        }

        $payment = $payment->refresh();

        if ($candidat !== null && $candidat->email !== null && $candidat->email !== '') {
            \App\Support\SafeNotifier::route('mail', $candidat->email, new PaymentConfirmedNotification($candidat, $payment));
        }

        return $payment;
    }
}
