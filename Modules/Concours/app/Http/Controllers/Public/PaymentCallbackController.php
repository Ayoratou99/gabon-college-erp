<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Payment;
use Modules\Concours\Services\Ebilling\EbillingService;
use Modules\Concours\Services\Ebilling\PaymentReferenceCipher;

/**
 * eBilling → notre serveur (async server-to-server callback).
 *
 * eBilling does NOT sign the request body or send any header secret. The
 * only authenticity guarantee is that the `external_reference` they echo
 * back round-trips through our PaymentReferenceCipher (AES-256-GCM keyed
 * by EBILLING_REFERENCE_KEY in .env). If decryption fails, the call is
 * forged — we 400 immediately with NO database lookup and NO log noise.
 *
 * The decoded payload contains {p: payment_uuid, c: candidat_uuid}. We
 * verify the Payment row exists, its candidat_id matches the embedded one
 * (defence against an attacker who somehow stole *another* valid reference
 * for a different candidat), and only then mark it paid. The candidat
 * statut flip and audit row are written by EbillingService::markPaid.
 *
 * Idempotency: a callback arriving twice for the same reference returns
 * `already_paid` (200) so eBilling stops retrying.
 */
final class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly EbillingService $ebilling,
        private readonly PaymentReferenceCipher $cipher,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // eBilling nests the real payload under `notification_params` (with the
        // encrypted reference + a `state`). Some flows / our own tests POST a
        // flat shape instead — accept both.
        $reference = (string) (
            $request->input('notification_params.reference')
            ?: $request->input('reference')
            ?: $request->input('external_reference')
            ?: ''
        );
        if ($reference === '') {
            return response()->json(['error' => 'missing reference'], 400);
        }

        $state = mb_strtolower((string) (
            $request->input('notification_params.state')
            ?: $request->input('state')
            ?: ''
        ));

        $payload = $this->cipher->decode($reference);
        if ($payload === null) {
            // Decryption failed → forged or corrupted. No DB lookup, no
            // audit row, no log noise — that's the whole point.
            return response()->json(['error' => 'invalid reference'], 400);
        }

        $payment = Payment::query()
            ->where('external_reference', $reference)
            ->first();

        if ($payment === null) {
            // The reference decrypts cleanly but doesn't exist in our DB.
            // That's *technically* impossible unless the row was deleted
            // — treat it as forged and don't echo state.
            return response()->json(['error' => 'unknown reference'], 404);
        }

        // Belt-and-suspenders: the payload's candidat_id must match the
        // row's. Mismatch means someone replayed a valid reference against
        // a different Payment — refuse and don't write anything.
        if ((string) $payment->candidat_id !== $payload['c']) {
            return response()->json(['error' => 'reference mismatch'], 400);
        }

        // Snapshot the raw body for forensics regardless of paid state, so
        // every callback we accept leaves a trace in payments.payload.
        $rawBody = (string) $request->getContent();
        $payment->forceFill([
            'payload'            => json_decode($rawBody, true) ?: ['_raw' => mb_substr($rawBody, 0, 4000)],
            'callback_ip'        => $request->ip(),
            // The reference cleared the cipher check — that's our equivalent
            // of "signature verified". We keep the column name for backwards
            // compatibility with the existing schema.
            'signature_verified' => true,
        ])->save();

        if ($payment->isPaid()) {
            return response()->json([
                'status'             => 'already_paid',
                'payment_id'         => $payment->getKey(),
                'external_reference' => $payment->external_reference,
            ]);
        }

        // Only a "paid" state validates the dossier. A non-paid callback
        // (failed, cancelled, pending…) is recorded above but does NOT flip the
        // candidat to valid. An empty state (flat / test payloads) is treated as
        // paid for backward compatibility.
        if ($state !== '' && $state !== 'paid') {
            return response()->json([
                'status'             => 'ignored',
                'state'              => $state,
                'external_reference' => $payment->external_reference,
            ]);
        }

        $payment = $this->ebilling->markPaid($payment, $request->all(), (string) $request->ip());

        return response()->json([
            'status'             => 'ok',
            'payment_id'         => $payment->getKey(),
            'external_reference' => $payment->external_reference,
        ]);
    }
}
