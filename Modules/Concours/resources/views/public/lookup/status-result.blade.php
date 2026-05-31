@extends('layouts.public')
@section('title', 'Statut du dossier')
@section('content')
<section class="container py-5" style="max-width:760px">

    @if ($ambiguous)
        <div class="alert alert-warning d-flex gap-3 align-items-start">
            <i class="fas fa-circle-info fa-lg mt-1"></i>
            <div>
                <strong>Plusieurs dossiers correspondent à « {{ $term }} ».</strong>
                Précisez votre recherche en saisissant votre matricule, votre nom complet ou votre email.
            </div>
        </div>
        <a href="{{ route('concours.public.status.form') }}" class="btn btn-primary">
            <i class="fas fa-rotate-left me-2"></i>Affiner la recherche
        </a>

    @elseif ($candidat === null && $results->isEmpty())
        <div class="alert alert-warning">
            Aucun dossier trouvé pour <strong>{{ $term }}</strong> dans la session en cours.
        </div>
        <a href="{{ route('concours.public.status.form') }}" class="btn btn-primary">
            <i class="fas fa-rotate-left me-2"></i>Réessayer
        </a>

    @elseif ($candidat === null)
        {{-- Multi-result list — let the user pick which dossier is theirs. --}}
        <h2 class="h4 mb-3">{{ $results->count() }} dossiers correspondent à « {{ $term }} »</h2>
        <p class="text-muted">Choisissez le vôtre&nbsp;:</p>

        <div class="list-group">
            @foreach ($results as $c)
                <form method="POST" action="{{ route('concours.public.status') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    @csrf
                    <input type="hidden" name="q" value="{{ $c->matricule_public }}">
                    <div>
                        <div class="fw-semibold">{{ $c->nom }} {{ $c->prenom }}</div>
                        <small class="text-muted">
                            <code>{{ $c->matricule_public }}</code>
                            @if($c->centre) — {{ $c->centre->nom }} @endif
                            @if($c->premierChoix) — {{ $c->premierChoix->code }} @endif
                        </small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary">
                        Voir le statut <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </form>
            @endforeach
        </div>

    @else
        @php
            // Inscription window of the candidat's session — drives all
            // "post-session lockout" behaviour below (no payment CTA, no
            // rejection-modification link once the window has closed).
            $sessionOpen = $candidat->session?->isInscriptionOpen() ?? false;
        @endphp
        {{-- Single match — show full status. --}}
        <div class="card form-card">
            <div class="card-body">
                <h2 class="h4 mb-1">{{ $candidat->prenom }} {{ $candidat->nom }}</h2>
                <p class="text-muted small mb-3">
                    Matricule&nbsp;: <code>{{ $candidat->matricule_public }}</code>
                    @if($candidat->centre) · {{ $candidat->centre->nom }} @endif
                    @if($candidat->session) · session {{ $candidat->session->libelle ?? $candidat->session->code }} @endif
                </p>

                @if(! $sessionOpen)
                    <div class="alert alert-secondary border d-flex align-items-start gap-2 small">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>
                            Les inscriptions de cette session sont closes — consultation uniquement.
                            Plus aucune action (paiement, modification…) n'est possible.
                        </div>
                    </div>
                @endif

                @switch($candidat->statut)
                    @case('non')
                        <div class="alert alert-info">
                            <strong>Dossier en cours de traitement.</strong>
                            Revenez plus tard pour connaître la décision.
                        </div>
                        @break
                    @case('oui')
                        <div class="alert alert-warning">
                            <strong>Dossier accepté</strong>
                            @if($sessionOpen)
                                — en attente du paiement des frais d'inscription.
                            @else
                                — le paiement n'est plus possible, les inscriptions sont closes.
                            @endif
                        </div>
                        @if($sessionOpen && $candidat->matricule_public)
                            <p>Procédez au paiement pour finaliser votre inscription.</p>
                            <a class="btn btn-primary" href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}">
                                <i class="fas fa-credit-card me-2"></i>Voir mon dossier &amp; payer
                            </a>
                        @endif
                        @break
                    @case('valid')
                        <div class="alert alert-success">
                            <strong>Inscription validée.</strong> Vous serez convoqué(e) à l'épreuve —
                            téléchargez votre fiche et votre planning ci-dessous.
                        </div>
                        @if($candidat->matricule_public)
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-success" href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}">
                                    <i class="fas fa-arrow-right me-2"></i>Mon espace candidat
                                </a>
                                <a class="btn btn-outline-primary"
                                   href="{{ route('concours.public.candidat.pdf', ['matricule' => $candidat->matricule_public, 'document' => 'fiche']) }}">
                                    <i class="far fa-file-pdf me-2"></i>Fiche d'inscription
                                </a>
                                <a class="btn btn-outline-primary"
                                   href="{{ route('concours.public.candidat.pdf', ['matricule' => $candidat->matricule_public, 'document' => 'emploi-du-temps']) }}">
                                    <i class="far fa-calendar-alt me-2"></i>Emploi du temps des épreuves
                                </a>
                            </div>
                        @endif
                        @break
                    @case('admis')
                        <div class="alert alert-success">
                            <strong>Félicitations — vous êtes admis(e) !</strong>
                            Connectez-vous à votre espace pour les détails de l'inscription définitive.
                        </div>
                        <a class="btn btn-success" href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}">
                            <i class="fas fa-trophy me-2"></i>Voir mes résultats
                        </a>
                        @break
                    @case('rejete')
                        <div class="alert alert-danger">
                            <strong>Dossier rejeté.</strong>
                            @if($candidat->motifsRejet->isNotEmpty())
                                <ul class="mb-0 mt-2">
                                    @foreach ($candidat->motifsRejet as $m)
                                        <li>{{ $m->motif }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        @if($sessionOpen)
                            <a href="{{ route('concours.public.lookup.form') }}" class="btn btn-primary">
                                <i class="fas fa-pen me-2"></i>Modifier mon dossier
                            </a>
                            <p class="small text-muted mt-2 mb-0">
                                Après modification, votre dossier repassera au statut « en cours de traitement ».
                            </p>
                        @endif
                        @break
                @endswitch
            </div>
        </div>
        <p class="text-center mt-3">
            <a href="{{ route('concours.public.status.form') }}" class="small text-muted">
                <i class="fas fa-magnifying-glass me-1"></i>Nouvelle recherche
            </a>
        </p>
    @endif

</section>
@endsection
