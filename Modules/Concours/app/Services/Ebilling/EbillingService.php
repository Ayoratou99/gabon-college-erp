<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Ebilling;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Notification;
use Modules\Concours\Exceptions\EbillingException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Payment;
use Modules\Concours\Notifications\PaymentConfirmedNotification;
use Modules\Parametrage\Services\SettingsService;

/**
 * Thin wrapper over the eBilling REST API.
 *
 *   createInvoice($candidat, $amount, $reference) → eBilling bill_id
 *   verifyCallback($payload, $signatureHeader)    → bool
 *   markPaid($externalReference, $payload, $ip)   → Payment
 *
 * Configuration is read from Parametrage (admin-editable, hot-rotatable
 * via the back office) with .env values as the seeded defaults.
 *
 * Signature verification: HMAC-SHA256 over the **raw JSON body** keyed by
 * the shared HMAC secret, compared in constant time. The header expected
 * is `X-Ebilling-Signature: <hex>`.
 */
final class EbillingService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly SettingsService $settings,
    ) {}

    public function createInvoice(Candidat $candidat, int $amount, string $externalReference): string
    {
        $baseUrl = (string) $this->settings->get('ebilling.base_url');
        $username = (string) $this->settings->get('ebilling.username');
        $sharedKey = (string) $this->settings->get('ebilling.shared_key');

        if ($baseUrl === '' || $username === '' || $sharedKey === '') {
            throw EbillingException::configurationMissing('base_url|username|shared_key');
        }

        $response = $this->http
            ->withBasicAuth($username, $sharedKey)
            ->asJson()
            ->timeout((int) config('concours.ebilling.http_timeout', 8))
            ->post(rtrim($baseUrl, '/') . '/api/v1/merchant/e_bills', [
                'payer_email'        => $candidat->email,
                'payer_msisdn'       => $candidat->telephone,
                'payer_name'         => trim($candidat->prenom . ' ' . $candidat->nom),
                'amount'             => $amount,
                'short_description'  => sprintf(
                    'Frais inscription concours %s — %s',
                    $candidat->session?->code ?? '',
                    $candidat->matricule_public,
                ),
                'external_reference' => $externalReference,
                'expiry_period'      => 60,
            ]);

        if (! $response->successful()) {
            throw EbillingException::invoiceCreationFailed($response->status(), $response->body());
        }

        $billId = $response->json('e_bill.bill_id');
        if (! is_string($billId) || $billId === '') {
            throw EbillingException::invoiceCreationFailed($response->status(), $response->body());
        }

        return $billId;
    }

    /**
     * HMAC verification of an inbound callback. Returns false (no throw) so
     * the controller can log + return 400 without leaking why.
     */
    public function verifyCallback(string $rawBody, ?string $signatureHex): bool
    {
        $hmacSecret = (string) $this->settings->get('ebilling.hmac_secret');
        if ($hmacSecret === '' || ! is_string($signatureHex) || $signatureHex === '') {
            return false;
        }
        $computed = hash_hmac('sha256', $rawBody, $hmacSecret);
        return hash_equals($computed, mb_strtolower(trim($signatureHex)));
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

        // Flip the candidate to "valid" — they're done.
        $candidat = $payment->candidat;
        if ($candidat !== null && $candidat->statut !== Candidat::STATUS_VALID) {
            $candidat->forceFill([
                'statut'    => Candidat::STATUS_VALID,
                'valide_at' => now(),
            ])->save();
        }

        $payment = $payment->refresh();

        if ($candidat !== null && $candidat->email !== null && $candidat->email !== '') {
            Notification::route('mail', $candidat->email)
                ->notify(new PaymentConfirmedNotification($candidat, $payment));
        }

        return $payment;
    }
}
