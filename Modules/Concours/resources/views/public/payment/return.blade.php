@extends('layouts.public')
@section('title', 'Retour du paiement')

@push('styles')
<style>
    .pay-status {
        max-width: 640px; margin: 3rem auto;
        background: #fff; border-radius: 1.25rem;
        box-shadow: 0 16px 40px rgba(15,23,42,.08);
        overflow: hidden;
    }
    .pay-status__hero {
        padding: 2rem 1.5rem 1.25rem; text-align: center; color: #fff;
    }
    .pay-status__hero i { font-size: 3rem; margin-bottom: .75rem; }
    .pay-status__hero h1 { font-size: 1.5rem; font-weight: 800; margin: 0; }
    .pay-status__body { padding: 1.75rem 2rem; }

    .pay-status--paid    .pay-status__hero { background: linear-gradient(135deg, #16a34a, #22c55e); }
    .pay-status--pending .pay-status__hero { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
    .pay-status--failed  .pay-status__hero { background: linear-gradient(135deg, #dc2626, #ef4444); }

    .pay-status table { width: 100%; font-size: .9rem; }
    .pay-status table th { width: 38%; color: #64748b; font-weight: 600; padding: .5rem 0; }
    .pay-status table td { padding: .5rem 0; }
</style>
@endpush

@section('content')
<section class="container">
    @php
        $klass = $isPaid
            ? 'paid'
            : (($payment?->status ?? 'INIT') === \Modules\Concours\Models\Payment::STATUS_FAILED
                ? 'failed'
                : 'pending');
        $copy = match ($klass) {
            'paid'    => ['icon' => 'fa-circle-check',   'title' => 'Paiement confirmé', 'sub' => 'Votre inscription est validée.'],
            'failed'  => ['icon' => 'fa-circle-xmark',   'title' => 'Paiement échoué',   'sub' => 'Vous pouvez réessayer.'],
            default   => ['icon' => 'fa-hourglass-half', 'title' => 'Paiement en cours', 'sub' => 'Nous attendons la confirmation d\'eBilling.'],
        };
    @endphp

    <div class="pay-status pay-status--{{ $klass }}">
        <div class="pay-status__hero">
            <i class="fas {{ $copy['icon'] }}"></i>
            <h1>{{ $copy['title'] }}</h1>
            <p class="mb-0 opacity-75">{{ $copy['sub'] }}</p>
        </div>
        <div class="pay-status__body">
            <table class="mb-3">
                <tr><th>Candidat</th>   <td>{{ $candidat->prenom }} {{ $candidat->nom }}</td></tr>
                <tr><th>Matricule</th>  <td><code>{{ $candidat->matricule_public }}</code></td></tr>
                @if($payment)
                    <tr><th>Montant</th>    <td>{{ number_format($payment->amount, 0, ',', ' ') }} {{ $payment->currency ?? 'FCFA' }}</td></tr>
                    <tr><th>Référence</th>  <td><code>{{ \Illuminate\Support\Str::limit($payment->external_reference, 32, '…') }}</code></td></tr>
                    <tr><th>État</th>       <td><span class="badge bg-{{ $isPaid ? 'success' : ($klass === 'failed' ? 'danger' : 'warning text-dark') }}">{{ $payment->status }}</span></td></tr>
                @endif
            </table>

            @if($isPaid)
                <div class="alert alert-success small mb-3">
                    <i class="fas fa-shield-halved me-1"></i>
                    Le paiement a été confirmé par eBilling. Vous pouvez désormais télécharger votre fiche d'inscription et votre planning.
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-success" href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}">
                        <i class="fas fa-arrow-right me-2"></i>Mon espace candidat
                    </a>
                    <a class="btn btn-outline-primary"
                       href="{{ route('concours.public.candidat.pdf', ['matricule' => $candidat->matricule_public, 'document' => 'fiche']) }}">
                        <i class="far fa-file-pdf me-2"></i>Fiche d'inscription
                    </a>
                </div>
            @elseif($klass === 'failed')
                <div class="alert alert-danger small mb-3">
                    <i class="fas fa-circle-exclamation me-1"></i>
                    Le paiement n'a pas abouti. Vérifiez votre solde mobile-money et réessayez. Si le problème persiste, contactez le support.
                </div>
                <form method="POST" action="{{ route('concours.public.payment.start', $candidat->matricule_public) }}">
                    @csrf
                    <button class="btn btn-primary">
                        <i class="fas fa-rotate me-2"></i>Réessayer le paiement
                    </button>
                </form>
            @else
                <div class="alert alert-warning small mb-3" id="pending-notice">
                    <i class="fas fa-circle-info me-1"></i>
                    La confirmation peut prendre <strong>jusqu'à 2 minutes</strong> côté eBilling.
                    Cette page se met à jour automatiquement.
                </div>
                <a class="btn btn-outline-primary" href="{{ url()->current() }}">
                    <i class="fas fa-rotate me-2"></i>Rafraîchir maintenant
                </a>
            @endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
@if(! $isPaid && ($klass ?? '') === 'pending')
<script>
    // Quietly refresh every 8 seconds for up to 2 minutes, then stop so we
    // don't spin forever on a confirmed failure that never callbacks.
    let attempts = 0;
    const interval = setInterval(() => {
        if (++attempts > 15) { clearInterval(interval); return; }
        window.location.reload();
    }, 8000);
</script>
@endif
@endpush
