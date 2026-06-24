<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Exceptions\EbillingException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Payment;
use Modules\Concours\Services\Ebilling\EbillingService;
use Modules\Concours\Services\Ebilling\PaymentReferenceCipher;

/**
 * Three-leg public payment flow against eBilling.
 *
 *   1. POST /candidat/{matricule}/payer  →  start()
 *        - We persist a Payment row first (gives us a UUID we can bind into
 *          the reference). We then encrypt {payment_id, candidat_id, nonce}
 *          with AES-256-GCM (PaymentReferenceCipher) and use the resulting
 *          base64url blob as the eBilling `external_reference`.
 *        - createInvoice() posts that to eBilling's REST API and returns the
 *          bill_id.
 *        - We auto-submit a form to the eBilling **portal_url** so the
 *          candidat lands on the hosted payment page (mobile-money happens
 *          there; we never touch the user's payment details).
 *
 *   2. eBilling → POST /payment/ebilling/callback (PaymentCallbackController)
 *        - Async server-to-server webhook. eBilling does NOT sign the body,
 *          so the only authenticity proof is that the `external_reference`
 *          they echo back round-trips through our AES key. If decryption
 *          fails → 400 with zero DB lookup.
 *        - On a valid reference we flip Payment.status = PAID and
 *          Candidat.statut = valid, log to candidat_modifications, and send
 *          the PaymentConfirmedNotification.
 *
 *   3. GET  /candidat/{matricule}/payment/retour?ref=…  →  return()
 *        - User-facing return URL after the eBilling portal. We re-read from
 *          our own DB (the callback may or may not have landed yet) and show
 *          a status card. We never trust query-string params for state
 *          transitions; this is display-only.
 *
 * The flow mirrors the legacy `payment.php` + `validation-ebilling-….php`
 * pair but adds: cryptographic verification (legacy was wide open), real
 * idempotency, and a proper status page.
 */
final class PaymentController extends Controller
{
    public function __construct(
        private readonly EbillingService $ebilling,
        private readonly PaymentReferenceCipher $cipher,
    ) {}

    /**
     * Build (or re-use) an e_bills invoice for this candidat and return
     * the HTML page that auto-submits the user to the eBilling portal.
     *
     *   POST /candidat/{matricule}/payer
     */
    public function start(Request $request, string $matricule): View|RedirectResponse
    {
        $candidat = Candidat::query()->where('matricule_public', $matricule)->first();
        if ($candidat === null) {
            return redirect()->route('concours.public.status.form')
                ->withErrors(['matricule' => 'Matricule inconnu.']);
        }

        // Business rules — refuse a new payment if any are violated.
        if ($candidat->statut === Candidat::STATUS_VALID) {
            return redirect()->route('concours.public.candidat.dashboard', $matricule)
                ->with('status', 'Votre paiement est déjà confirmé.');
        }
        if ($candidat->statut !== Candidat::STATUS_OUI) {
            return redirect()->route('concours.public.candidat.dashboard', $matricule)
                ->withErrors(['statut' => 'Le paiement n\'est possible qu\'après acceptation du dossier.']);
        }
        if (! ($candidat->session?->isInscriptionOpen() ?? false)) {
            return redirect()->route('concours.public.candidat.dashboard', $matricule)
                ->withErrors(['session' => 'Les inscriptions sont closes — paiement impossible.']);
        }

        // QA test candidate pays the reduced test fee (default 100 XAF) so the
        // real eBilling flow can be exercised end-to-end on prod cheaply.
        $amount = $candidat->isTest()
            ? (int) config('concours.test.fee', 100)
            : (int) ($candidat->session?->fraisInscription() ?? config('concours.payment.default_amount', 10300));

        // Re-use an in-flight Payment ONLY while its eBilling invoice is still
        // live (created within the grace window). The bill expires after
        // `invoice_grace_seconds`, so re-handing an older one would show the
        // candidat a dead invoice — and OVERWRITING its reference (what the old
        // code did) means a late callback for that previous invoice can no
        // longer find its row, so a real payment would be lost. Inside the
        // window we re-show the SAME invoice (idempotent refresh — no duplicate
        // bill). Outside it we mint a BRAND-NEW Payment (new ref + new bill) and
        // leave the previous row untouched, so every attempt stays on the record
        // for traceability and any of them can still be reconciled if paid late.
        $graceSeconds = (int) config('concours.ebilling.invoice_grace_seconds', 60);
        $payment = Payment::query()
            ->where('candidat_id', $candidat->id)
            ->whereIn('status', [Payment::STATUS_INIT, Payment::STATUS_PENDING])
            ->where('created_at', '>=', now()->subSeconds($graceSeconds))
            ->latest('created_at')
            ->first();

        try {
            // A still-live invoice already exists in the window → reuse it as-is.
            $invoiceIsLive = $payment !== null
                && $payment->status === Payment::STATUS_PENDING
                && $payment->ebilling_id !== null;

            if (! $invoiceIsLive) {
                // Either nothing in-flight (→ new Payment) or an INIT row whose
                // invoice creation had previously failed (→ retry on that row).
                // We never touch a row that already carries a real reference +
                // bill, so a paid-but-late invoice can always be reconciled.
                if ($payment === null) {
                    $payment = Payment::query()->create([
                        'candidat_id'         => $candidat->id,
                        'concours_session_id' => $candidat->concours_session_id,
                        'amount'              => $amount,
                        'currency'            => 'FCFA',
                        'ebilling_id'         => null,
                        // Placeholder unique value — overwritten once the invoice
                        // is created. external_reference is NOT NULL + UNIQUE so
                        // we can't leave it empty mid-flight.
                        'external_reference'  => 'pending:' . $candidat->id . ':' . microtime(true),
                        'status'              => Payment::STATUS_INIT,
                        'signature_verified'  => false,
                    ]);
                }

                $reference = $this->cipher->encode((string) $payment->getKey(), (string) $candidat->getKey());
                $billId    = $this->ebilling->createInvoice($candidat, $amount, $reference);
                $payment->forceFill([
                    'external_reference' => $reference,
                    'ebilling_id'        => $billId,
                    'status'             => Payment::STATUS_PENDING,
                ])->save();
            }
        } catch (EbillingException $e) {
            // The eBilling service refused (config manquante, montant invalide,
            // identifiants, réponse d'erreur de l'API…). Surface the REAL reason
            // — invoiceCreationFailed carries eBilling's own HTTP body — so the
            // candidat/admin knows exactly what to fix instead of a vague message.
            report($e);
            return redirect()->route('concours.public.candidat.dashboard', $matricule)
                ->withErrors(['ebilling' => 'Paiement impossible — ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            // Anything else (réseau injoignable, délai dépassé, chiffrement…) —
            // show it too rather than 500-ing the candidat out of the flow.
            report($e);
            return redirect()->route('concours.public.candidat.dashboard', $matricule)
                ->withErrors(['ebilling' => 'Paiement impossible — erreur technique : ' . $e->getMessage()]);
        }

        // Build the auto-submitted hand-off form. The eBilling portal
        // expects `invoice_number` + `eb_callbackurl` (the URL the user is
        // redirected to after finishing on their side).
        $portalUrl = rtrim((string) config('concours.ebilling.portal_url', 'https://lab.billing-easy.net'), '/');
        $returnUrl = route('concours.public.payment.return', [
            'matricule' => $candidat->matricule_public,
            'ref'       => $payment->external_reference,
        ]);

        return view('concours::public.payment.redirect', [
            'portalUrl'  => $portalUrl,
            'invoiceId'  => $payment->ebilling_id,
            'returnUrl'  => $returnUrl,
            'candidat'   => $candidat,
            'amountFcfa' => $amount,
        ]);
    }

    /**
     * Where the candidat lands after the eBilling portal. We read the live
     * Payment row from our own DB (which the async callback has — or hasn't
     * yet — flipped to PAID). We never trust query-string params from here
     * for state changes; this is a display-only endpoint.
     *
     *   GET /candidat/{matricule}/payment/retour?ref=…
     */
    public function return(Request $request, string $matricule): View|RedirectResponse
    {
        $candidat = Candidat::query()->where('matricule_public', $matricule)->first();
        if ($candidat === null) {
            return redirect()->route('concours.public.status.form');
        }

        $reference = (string) $request->query('ref', '');
        $payment   = $reference !== ''
            ? Payment::query()->where('external_reference', $reference)
                ->where('candidat_id', $candidat->id)
                ->first()
            : Payment::query()->where('candidat_id', $candidat->id)
                ->latest('created_at')->first();

        // Refresh from DB so we see the very latest write by the callback.
        $candidat->refresh();

        return view('concours::public.payment.return', [
            'candidat' => $candidat,
            'payment'  => $payment,
            'isPaid'   => $candidat->statut === Candidat::STATUS_VALID,
        ]);
    }
}
