@extends('layouts.public')

@section('title', 'Mon espace étudiant — ' . ($candidat?->matricule_public ?? config('app.name')))

@section('content')
<section class="container py-5" style="max-width: 980px;">

    {{-- Welcome hero --}}
    <div class="card mb-4 border-0" style="background: linear-gradient(135deg, color-mix(in srgb, var(--cuk-primary) 92%, transparent), color-mix(in srgb, var(--cuk-accent) 88%, transparent)); color:#fff;">
        <div class="card-body py-4 px-4 d-flex flex-wrap gap-3 align-items-center">
            <div class="success-check" style="background: rgba(255,255,255,.18); color: #fff; width: 4rem; height: 4rem; font-size:1.8rem;">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="flex-grow-1">
                <h1 class="h3 mb-1">Bonjour {{ $user->prenom }} {{ $user->nom }}</h1>
                <p class="mb-0" style="opacity:.95;">
                    Bienvenue dans votre espace étudiant CUK.
                </p>
            </div>
            <div class="text-end small d-none d-md-block" style="opacity:.9;">
                Connecté en tant que <strong>Étudiant</strong>
                @php $user->loadMissing('roles'); @endphp
                @if($user->roles->count() > 1)
                    · <a href="{{ route('role.switch') }}" class="text-white text-decoration-underline">Changer de rôle</a>
                @endif
            </div>
        </div>
    </div>

    @if($candidat === null)
        <div class="alert alert-warning">
            <h2 class="h5 mb-2"><i class="fas fa-circle-exclamation me-2"></i>Aucun dossier d'admission lié à votre compte.</h2>
            <p class="mb-0 small">
                Votre compte étudiant n'est pas relié à un dossier de candidat dans notre base.
                Si vous pensez qu'il s'agit d'une erreur, contactez le service scolarité.
            </p>
        </div>
    @else

        {{-- Dossier summary --}}
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0"><i class="fas fa-id-card text-primary me-2"></i>Mon dossier d'admission</h2>
                @php $sb = $candidat->statutBadge(); @endphp
                <span class="badge bg-{{ $sb['css'] }} fs-6"><i class="fas {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Matricule</dt>
                    <dd class="col-sm-8"><code class="fs-5">{{ $candidat->matricule_public }}</code></dd>

                    <dt class="col-sm-4">Session du concours</dt>
                    <dd class="col-sm-8">{{ $candidat->session?->libelle ?? '—' }}</dd>

                    @if($isAdmis && $candidat->sectionOrientation)
                        <dt class="col-sm-4">Orientation</dt>
                        <dd class="col-sm-8">
                            <strong>{{ $candidat->sectionOrientation->code }} — {{ $candidat->sectionOrientation->nom }}</strong>
                            @if($candidat->rang)
                                · Rang&nbsp;: <strong>{{ $candidat->rang }}</strong>
                            @endif
                            @if($candidat->moyenne)
                                · Moyenne&nbsp;: <strong>{{ number_format((float) $candidat->moyenne, 2, ',', ' ') }}</strong>
                            @endif
                        </dd>
                    @endif

                    <dt class="col-sm-4">Centre d'examen</dt>
                    <dd class="col-sm-8">{{ $candidat->centre?->nom }}{{ $candidat->centre?->ville ? ' — ' . $candidat->centre->ville : '' }}</dd>

                    @if($candidat->session?->date_concours)
                        <dt class="col-sm-4">Date du concours</dt>
                        <dd class="col-sm-8">{{ $candidat->session->date_concours->format('d/m/Y') }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Quick actions: download fiche + emploi du temps (if admis) --}}
        @if($isAdmis)
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0"><i class="far fa-folder-open text-primary me-2"></i>Documents officiels</h2>
                </div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary"
                       href="{{ route('concours.public.candidat.pdf', ['matricule' => $candidat->matricule_public, 'document' => 'fiche']) }}">
                        <i class="far fa-file-pdf me-2"></i>Fiche d'inscription
                    </a>
                    <a class="btn btn-outline-primary"
                       href="{{ route('concours.public.candidat.pdf', ['matricule' => $candidat->matricule_public, 'document' => 'emploi-du-temps']) }}">
                        <i class="far fa-calendar-alt me-2"></i>Emploi du temps
                    </a>
                </div>
                <div class="card-footer bg-white small text-muted">
                    <i class="fas fa-circle-info me-1"></i>
                    L'attestation officielle d'admission est en cours d'élaboration —
                    elle sera disponible ici dès que possible.
                </div>
            </div>
        @endif
    @endif

    {{-- Account management --}}
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0"><i class="fas fa-shield-halved text-primary me-2"></i>Mon compte</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-3">
                <dt class="col-sm-4">Email</dt>
                <dd class="col-sm-8">{{ $user->email }}</dd>
                <dt class="col-sm-4">Téléphone</dt>
                <dd class="col-sm-8"><code>{{ $user->telephone ?? '—' }}</code></dd>
                <dt class="col-sm-4">Double authentification</dt>
                <dd class="col-sm-8">
                    @if($user->google2fa_confirmed_at)
                        <span class="badge bg-success"><i class="fas fa-shield me-1"></i>Activée</span>
                        <span class="text-muted small ms-2">depuis le {{ $user->google2fa_confirmed_at->format('d/m/Y') }}</span>
                    @else
                        <span class="badge bg-warning text-dark"><i class="fas fa-shield-halved me-1"></i>Non activée</span>
                    @endif
                </dd>
            </dl>
            <p class="small text-muted mb-3">
                Vous pouvez modifier votre profil et gérer la double authentification depuis votre fiche utilisateur.
            </p>
            <a href="{{ route('admin.pages.users.show', $user->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="far fa-user me-2"></i>Modifier mon profil
            </a>
            <form method="POST" action="{{ route('logout') }}" class="d-inline-block ms-2">
                @csrf
                <button type="submit" class="btn btn-link text-muted btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Se déconnecter
                </button>
            </form>
        </div>
    </div>

</section>
@endsection
