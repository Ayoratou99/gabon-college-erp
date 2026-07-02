@extends('layouts.public')

@section('title', 'Inscription enregistrée — ' . $candidat->matricule_public)

@push('styles')
{{-- A small print stylesheet: hides the navbar/footer/buttons + lays out
     the matricule big enough to keep as a paper backup. --}}
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

        {{-- Hero --}}
        <div class="text-center mb-4">
            <div class="success-check mb-3">
                <i class="fas fa-circle-check"></i>
            </div>
            <h1 class="h3 mb-1">
                Félicitations {{ $candidat->prenom }} {{ $candidat->nom }},
            </h1>
            <p class="lead mb-0 text-muted">votre inscription est enregistrée.</p>
        </div>

        {{-- Matricule + copy / print --}}
        <div class="card border-success mb-4" x-data="{ copied: false }">
            <div class="card-body text-center py-4">
                <p class="small text-muted mb-2 text-uppercase" style="letter-spacing:.1em;">
                    Votre matricule officiel
                </p>
                <div class="success-matricule mb-3" id="matricule-value">
                    {{ $candidat->matricule_public }}
                </div>
                <p class="small text-muted mb-3">
                    Gardez-le précieusement — vous en aurez besoin à chaque étape du concours.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap no-print">
                    <button type="button" class="btn btn-outline-success"
                            @click="navigator.clipboard.writeText('{{ $candidat->matricule_public }}').then(() => { copied = true; setTimeout(() => copied = false, 2000); })">
                        <template x-if="!copied">
                            <span><i class="far fa-copy me-2"></i>Copier</span>
                        </template>
                        <template x-if="copied">
                            <span><i class="fas fa-check me-2"></i>Copié !</span>
                        </template>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Imprimer
                    </button>
                </div>
            </div>
        </div>

        {{-- Email confirmation note --}}
        @if($candidat->email && ! str_ends_with($candidat->email, '@cuk.local'))
           <!--
            <p class="text-center small mb-4">
                <i class="far fa-envelope text-success me-1"></i>
                Une confirmation a été envoyée à
                <strong>{{ $candidat->email }}</strong>.
                Pensez à vérifier votre dossier <em>Spam</em> si vous ne la voyez pas.
            </p>
               -->
        @endif

        {{-- Next steps --}}
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">
                    <i class="fas fa-route text-primary me-2"></i>Ce qui se passe maintenant
                </h2>
            </div>
            <ol class="list-group list-group-flush list-group-numbered">
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <strong>Revue de votre dossier</strong> par notre équipe pédagogique.
                        <div class="small text-muted">Vérification des informations, des documents et de la photo.</div>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill">3 à 5 jours ouvrés</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <strong>Consultation régulière</strong> du résultat de la revue.
                        <div class="small text-muted">Acceptation, ou rejet motivé avec possibilité de correction.</div>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill">à la décision</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <strong>Paiement des frais d'inscription</strong> (si dossier accepté).
                        <div class="small text-muted">
                            <strong>{{ number_format((int) ($candidat->session?->fraisInscription() ?? 10300), 0, ',', ' ') }} FCFA</strong>
                            via eBilling (Mobile Money &amp; cartes).
                        </div>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill">7 jours</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <strong>Convocation à l'épreuve</strong>
                        @if($candidat->session?->date_concours)
                            du <strong>{{ $candidat->session->date_concours->format('d F Y') }}</strong>.
                        @endif
                        <div class="small text-muted">Envoyée environ une semaine avant la date.</div>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill">~7 j avant</span>
                </li>
            </ol>
        </div>

        {{-- Quick actions --}}
        <div class="d-flex flex-wrap gap-2 justify-content-center no-print">
            <a href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}"
               class="btn btn-primary">
                <i class="fas fa-folder-open me-2"></i>Suivre mon dossier
            </a>
            <a href="{{ route('concours.public.status.form') }}" class="btn btn-outline-secondary">
                <i class="fas fa-search me-2"></i>Vérifier mon statut
            </a>
            <a href="{{ route('home') }}" class="btn btn-link text-muted">
                Retour à l'accueil
            </a>
        </div>

        {{-- Quiet footer info --}}
        <p class="small text-muted text-center mt-4 mb-0">
            <i class="fas fa-shield-halved me-1"></i>
            En cas de rejet motivé, vous pourrez corriger votre dossier en ligne via la page
            <a href="{{ route('concours.public.lookup.form') }}">« Récupérer mon dossier »</a>.
        </p>
    </div>
</section>
@endsection
