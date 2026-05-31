@extends('layouts.public')

@section('title', 'Dossier renvoyé — ' . $candidat->matricule_public)

@push('styles')
<style media="print">
    @page { margin: 1.5cm; }
    body { background: #fff !important; color: #000 !important; }
    .navbar, footer, .no-print, .btn { display: none !important; }
    .success-shell { box-shadow: none !important; border: 1px solid #ddd !important; }
    .success-matricule { color: #000 !important; }
</style>
@endpush

@section('content')
<section class="container py-5" style="max-width: 880px;">

    <div class="success-shell">

        <div class="text-center mb-4">
            <div class="success-check mb-3" style="background: rgba(13,110,253,.12); color: #0d6efd;">
                <i class="fas fa-arrow-rotate-right"></i>
            </div>
            <h1 class="h3 mb-1">
                Merci {{ $candidat->prenom }} {{ $candidat->nom }},
            </h1>
            <p class="lead mb-0 text-muted">votre dossier corrigé a bien été renvoyé.</p>
        </div>

        <div class="card border-primary mb-4" x-data="{ copied: false }">
            <div class="card-body text-center py-4">
                <p class="small text-muted mb-2 text-uppercase" style="letter-spacing:.1em;">
                    Votre matricule reste inchangé
                </p>
                <div class="success-matricule mb-3">{{ $candidat->matricule_public }}</div>
                <div class="d-flex justify-content-center gap-2 flex-wrap no-print">
                    <button type="button" class="btn btn-outline-primary"
                            @click="navigator.clipboard.writeText('{{ $candidat->matricule_public }}').then(() => { copied = true; setTimeout(() => copied = false, 2000); })">
                        <template x-if="!copied"><span><i class="far fa-copy me-2"></i>Copier</span></template>
                        <template x-if="copied"><span><i class="fas fa-check me-2"></i>Copié !</span></template>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Imprimer
                    </button>
                </div>
            </div>
        </div>

        @if($candidat->email && ! str_ends_with($candidat->email, '@cuk.local'))
            <p class="text-center small mb-4">
                <i class="far fa-envelope text-primary me-1"></i>
                Vous serez notifié(e) à
                <strong>{{ $candidat->email }}</strong> dès qu'une nouvelle décision aura été prise.
            </p>
        @endif

        <div class="card mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">
                    <i class="fas fa-route text-primary me-2"></i>Ce qui se passe maintenant
                </h2>
            </div>
            <ol class="list-group list-group-flush list-group-numbered">
                <li class="list-group-item">
                    <strong>Votre dossier repasse en file d'attente</strong> pour une nouvelle revue
                    par notre équipe pédagogique.
                    <div class="small text-muted">Statut actuel : <em>« En cours »</em> — comme à la première soumission.</div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <strong>Nouvelle notification par email</strong> dès la décision prise.
                        <div class="small text-muted">Acceptation et passage au paiement, ou nouveau rejet motivé.</div>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill">3 à 5 jours ouvrés</span>
                </li>
                @if($candidat->session?->date_concours)
                    <li class="list-group-item">
                        <strong>Épreuve prévue le
                            {{ $candidat->session->date_concours->format('d F Y') }}</strong> — assurez-vous
                        d'avoir terminé le paiement avant cette date.
                    </li>
                @endif
            </ol>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-center no-print">
            <a href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}"
               class="btn btn-primary">
                <i class="fas fa-folder-open me-2"></i>Suivre mon dossier
            </a>
            <a href="{{ route('concours.public.status.form') }}" class="btn btn-outline-secondary">
                <i class="fas fa-search me-2"></i>Vérifier mon statut
            </a>
            <a href="{{ route('home') }}" class="btn btn-link text-muted">Retour à l'accueil</a>
        </div>

    </div>
</section>
@endsection
