@extends('layouts.admin')

@section('title', 'Chefs de centre')
@section('page-title', 'Chefs de centre')

@section('page-actions')
    <a href="{{ route('admin.pages.concours.centres.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Centres
    </a>
@endsection

@section('content')
<section x-data="{ showCreate: {{ $errors->any() && old('nom') ? 'true' : 'false' }} }">

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {!! session('status') !!}
            @if(session('temp_password'))
                <div class="mt-2">
                    <code class="fs-5 user-select-all" style="background:#fff3cd; padding:.2em .6em; border-radius:.25em;">{{ session('temp_password') }}</code>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                            onclick="navigator.clipboard.writeText('{{ session('temp_password') }}'); this.innerHTML='Copié ✓';">
                        <i class="far fa-copy"></i> Copier
                    </button>
                    <div class="small text-muted mt-1">
                        Ne sera plus affiché — communiquez-le par téléphone / Signal. L'utilisateur devra le changer à sa prochaine connexion.
                    </div>
                </div>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Session picker --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div class="flex-grow-1" style="min-width:280px;">
                <label class="form-label small mb-1">Session</label>
                <form method="GET" class="d-flex gap-2">
                    <select name="session" class="form-select" onchange="this.form.submit()">
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}" {{ $session?->id === $s->id ? 'selected' : '' }}>
                                {{ $s->code }} — {{ $s->libelle }}{{ $s->est_active ? ' (active)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>
                Un chef peut être affecté à plusieurs centres (suppléances),
                et un centre peut avoir plusieurs chefs.
            </div>
        </div>
    </div>

    @if($eligibleUsers->isEmpty())
        <div class="alert alert-warning">
            <strong>Aucun utilisateur n'a le rôle « chef-centre ».</strong>
            Créez un compte avec ce rôle dans
            <a href="{{ route('admin.pages.users.index') }}">Utilisateurs</a> avant d'assigner.
        </div>
    @endif

    @if($session === null)
        <div class="alert alert-info">Aucune session sélectionnée.</div>
    @else

        @if(! $sessionEditable)
            <div class="alert alert-warning d-flex align-items-center gap-2">
                <i class="fas fa-lock fa-lg"></i>
                <div>
                    <strong>Session archivée.</strong>
                    Les affectations de la session
                    <em>{{ $session->libelle }}</em> sont consultables mais ne sont plus modifiables
                    (aucun ajout, retrait ou bascule titulaire/suppléant).
                </div>
            </div>
        @endif

        {{-- "Create new chef" inline form (collapsible). Sits above the centre
             grid because the new user is session-scoped, not centre-scoped:
             we pick the target centre inside the form. --}}
        @if($sessionEditable)
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-outline-primary" @click="showCreate = !showCreate">
                    <i class="fas fa-user-plus me-2"></i>
                    <span x-show="!showCreate">Créer un nouveau chef de centre</span>
                    <span x-show="showCreate">Annuler la création</span>
                </button>
            </div>
        @endif

        <div class="card mb-4 border-primary" x-show="{{ $sessionEditable ? 'showCreate' : 'false' }}" x-transition x-cloak>
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">
                    <i class="fas fa-user-plus text-primary me-2"></i>
                    Nouveau compte chef de centre
                </h2>
                <p class="small text-muted mb-0 mt-1">
                    Crée un utilisateur avec le rôle <strong>chef-centre</strong>, l'affecte au centre choisi
                    et génère un mot de passe temporaire à transmettre une seule fois.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.pages.concours.chef_centres.create_and_assign') }}" class="card-body">
                @csrf
                <input type="hidden" name="concours_session_id" value="{{ $session->id }}">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="nom" class="form-control" required maxlength="100" value="{{ old('nom') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Prénom <span class="text-danger">*</span></label>
                        <input type="text" name="prenom" class="form-control" required maxlength="100" value="{{ old('prenom') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="191" value="{{ old('email') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Téléphone (optionnel)</label>
                        <input type="text" name="telephone" class="form-control" maxlength="30" value="{{ old('telephone') }}" placeholder="+241 6X XX XX XX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Centre affecté <span class="text-danger">*</span></label>
                        <select name="centre_id" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                            @foreach($centres as $c)
                                <option value="{{ $c->id }}" {{ old('centre_id') === $c->id ? 'selected' : '' }}>
                                    {{ $c->code }} — {{ $c->nom }}{{ $c->ville ? ' (' . $c->ville . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Type</label>
                        <select name="est_principal" class="form-select">
                            <option value="1">Titulaire</option>
                            <option value="0">Suppléant</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" @click="showCreate = false">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Créer + Affecter
                    </button>
                </div>
            </form>
        </div>

        <div class="row g-3">
            @forelse($centres as $centre)
                @php
                    $rows = $assignments->get($centre->id, collect());
                @endphp
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h6 mb-0">
                                    <i class="fas fa-building-columns text-primary me-2"></i>
                                    {{ $centre->nom }}
                                </h2>
                                <small class="text-muted">
                                    <code>{{ $centre->code }}</code>
                                    @if($centre->ville)
                                        &middot; {{ $centre->ville }}
                                    @endif
                                </small>
                            </div>
                            <span class="badge bg-{{ $rows->isEmpty() ? 'warning text-dark' : 'info' }}">
                                {{ $rows->count() }} chef(s)
                            </span>
                        </div>

                        <ul class="list-group list-group-flush">
                            @forelse($rows as $a)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $a->user?->nom }} {{ $a->user?->prenom }}</strong>
                                        @if($a->est_principal)
                                            <span class="badge bg-primary ms-2">Titulaire</span>
                                        @else
                                            <span class="badge bg-secondary ms-2">Suppléant</span>
                                        @endif
                                        <br>
                                        <span class="small text-muted">
                                            <i class="far fa-envelope me-1"></i>{{ $a->user?->email ?: '—' }}
                                            &nbsp;&middot;&nbsp; affecté le {{ $a->assigned_at?->format('d/m/Y') }}
                                        </span>
                                    </div>
                                    @if($sessionEditable)
                                        <div class="d-flex gap-1">
                                            <form method="POST" action="{{ route('admin.pages.concours.chef_centres.toggle_principal', $a) }}"
                                                  onsubmit="return confirm('Basculer titulaire ↔ suppléant ?');">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary" title="Basculer titulaire / suppléant">
                                                    <i class="fas fa-arrows-rotate"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.pages.concours.chef_centres.destroy', $a) }}"
                                                  onsubmit="return confirm('Retirer cette affectation ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Retirer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="badge bg-light text-muted border" title="Session archivée">
                                            <i class="fas fa-lock me-1"></i>Verrouillé
                                        </span>
                                    @endif
                                </li>
                            @empty
                                <li class="list-group-item text-center text-muted py-3">
                                    <i class="fas fa-user-slash me-2"></i>Aucun chef assigné.
                                </li>
                            @endforelse
                        </ul>

                        @if($eligibleUsers->isNotEmpty() && $sessionEditable)
                            <form method="POST" action="{{ route('admin.pages.concours.chef_centres.assign') }}"
                                  class="card-footer bg-white">
                                @csrf
                                <input type="hidden" name="concours_session_id" value="{{ $session->id }}">
                                <input type="hidden" name="centre_id" value="{{ $centre->id }}">
                                <div class="row g-2 align-items-end">
                                    <div class="col-sm-7">
                                        <label class="form-label small mb-1">Ajouter un chef</label>
                                        <select name="user_id" class="form-select form-select-sm" required>
                                            <option value="">— Sélectionner —</option>
                                            @foreach($eligibleUsers as $u)
                                                <option value="{{ $u->id }}">{{ $u->nom }} {{ $u->prenom }} ({{ $u->email ?: 'sans email' }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Type</label>
                                        <select name="est_principal" class="form-select form-select-sm">
                                            <option value="1">Titulaire</option>
                                            <option value="0">Suppléant</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-2 d-grid">
                                        <button class="btn btn-sm btn-success">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-warning">
                        Aucun centre actif. Activez au moins un centre dans
                        <a href="{{ route('admin.pages.concours.centres.index') }}">Centres</a>.
                    </div>
                </div>
            @endforelse
        </div>
    @endif

</section>
@endsection
