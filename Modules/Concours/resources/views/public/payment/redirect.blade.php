@extends('layouts.public')
@section('title', 'Redirection vers eBilling…')

@push('styles')
<style>
    .pay-handoff {
        max-width: 540px; margin: 4rem auto;
        background: #fff; border-radius: 1.25rem;
        box-shadow: 0 16px 40px rgba(15, 23, 42, .08);
        padding: 2.25rem 2rem; text-align: center;
    }
    .pay-handoff .spinner-border { color: var(--cuk-primary, #1d4ed8); }
    .pay-handoff h1 { font-size: 1.3rem; font-weight: 800; margin: 1rem 0 .25rem; }
    .pay-handoff p  { color: #64748b; }
    .pay-handoff .pay-meta {
        background: #f1f5f9; border-radius: .65rem; padding: .85rem 1rem;
        margin-top: 1.25rem; font-size: .85rem; color: #334155; text-align: left;
    }
    .pay-handoff .pay-meta strong { color: #0f172a; }
</style>
@endpush

@section('content')
<section class="container">
    <div class="pay-handoff">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <h1>Redirection vers la plateforme de paiement…</h1>
        <p class="mb-0">Si la page ne s'ouvre pas dans 3 secondes, cliquez sur le bouton ci-dessous.</p>

        <div class="pay-meta">
            <strong>Candidat&nbsp;:</strong> {{ $candidat->prenom }} {{ $candidat->nom }}<br>
            <strong>Matricule&nbsp;:</strong> <code>{{ $candidat->matricule_public }}</code><br>
            <strong>Montant&nbsp;:</strong> {{ number_format($amountFcfa, 0, ',', ' ') }} FCFA<br>
            <strong>Référence&nbsp;:</strong> <code>{{ \Illuminate\Support\Str::limit($invoiceId, 24, '…') }}</code>
        </div>

        {{-- Auto-submitted hand-off form: invoice_number + eb_callbackurl
             match the field names the eBilling portal expects. The user
             never sees this form — JS submits it instantly. --}}
        <form id="eb-handoff" method="POST" action="{{ $portalUrl }}" class="mt-4">
            <input type="hidden" name="invoice_number" value="{{ $invoiceId }}">
            <input type="hidden" name="eb_callbackurl" value="{{ $returnUrl }}">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-credit-card me-2"></i>Continuer vers eBilling
            </button>
        </form>

        <p class="small text-muted mt-3 mb-0">
            <a href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}">
                <i class="fas fa-arrow-left me-1"></i>Annuler et retourner à mon dossier
            </a>
        </p>
    </div>
</section>
@endsection

@push('scripts')
<script>
    // Tiny defer so the visible spinner has a paint cycle before the page
    // navigates away. Users on shaky connections see a real "redirecting…"
    // beat instead of a blank flash.
    setTimeout(() => document.getElementById('eb-handoff').submit(), 600);
</script>
@endpush
