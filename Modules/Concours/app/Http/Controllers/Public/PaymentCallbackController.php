<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Payment;
use Modules\Concours\Services\Ebilling\EbillingService;

/**
 * eBilling → notre serveur. Verifies the HMAC signature on the raw body,
 * looks up the Payment by external_reference, and marks it paid. The
 * candidate's statut is flipped to 'valid' inside markPaid().
 *
 * Audit / forensics: even if the signature fails, we persist a row in
 * payments.payload + callback_ip with signature_verified=false so we can
 * investigate. Returns 400 on failure but never reveals why.
 */
final class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly EbillingService $ebilling,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $signature = $request->header('X-Ebilling-Signature');

        $isValid = $this->ebilling->verifyCallback($rawBody, is_string($signature) ? $signature : null);

        $reference = (string) $request->input('reference', $request->input('external_reference', ''));

        $payment = $reference === ''
            ? null
            : Payment::query()->where('external_reference', $reference)->first();

        if ($payment === null) {
            return response()->json(['error' => 'unknown reference'], 404);
        }

        // Capture every callback (even failed sigs) for forensics.
        $payment->forceFill([
            'payload'            => json_decode($rawBody, true) ?: ['_raw' => mb_substr($rawBody, 0, 4000)],
            'callback_ip'        => $request->ip(),
            'signature_verified' => $isValid,
        ])->save();

        if (! $isValid) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        if ($payment->isPaid()) {
            return response()->json(['status' => 'already_paid']);
        }

        $payment = $this->ebilling->markPaid($payment, $request->all(), (string) $request->ip());

        return response()->json([
            'status'             => 'ok',
            'payment_id'         => $payment->getKey(),
            'external_reference' => $payment->external_reference,
        ]);
    }
}
