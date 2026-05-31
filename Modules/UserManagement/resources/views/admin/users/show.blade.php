@extends('layouts.admin')

@section('title', $user->prenom . ' ' . $user->nom)
@section('page-title', $user->prenom . ' ' . $user->nom)

@section('content')

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show">
        {!! session('status') !!}
        @if(session('temp_password'))
            <div class="mt-2">
                Mot de passe temporaire&nbsp;:
                <code class="fs-5 user-select-all" style="background:#fff3cd; padding:.2em .6em; border-radius:.25em;">{{ session('temp_password') }}</code>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                        onclick="navigator.clipboard.writeText('{{ session('temp_password') }}'); this.innerHTML='Copié ✓';">
                    <i class="far fa-copy"></i> Copier
                </button>
                <div class="small text-muted mt-1">
                    Ne sera plus affiché. Communiquez-le par un canal sûr&nbsp;; l'utilisateur devra le changer à sa prochaine connexion.
                </div>
            </div>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($user->isBlocked())
    <div class="alert alert-danger d-flex align-items-start gap-3">
        <i class="fas fa-circle-xmark fs-3"></i>
        <div>
            <strong>Compte bloqué</strong>
            depuis le {{ $user->blocked_at->format('d/m/Y H:i') }}.
            @if($user->blocked_reason)
                <br><span class="small">Motif&nbsp;: {{ $user->blocked_reason }}</span>
            @endif
        </div>
    </div>
@endif

<div class="row g-3">
    {{-- Profile + 2FA + password actions --}}
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0"><i class="fas fa-id-card text-primary me-2"></i>Identité</h2>
            </div>
            <table class="table mb-0">
                <tbody>
                    <tr><th class="w-25 text-muted">Nom</th><td>{{ $user->nom }}</td></tr>
                    <tr><th class="text-muted">Prénom</th><td>{{ $user->prenom }}</td></tr>
                    <tr><th class="text-muted">Email</th><td>{{ $user->email ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Téléphone</th><td><code>{{ $user->telephone ?? '—' }}</code></td></tr>
                    <tr><th class="text-muted">Dernière connexion</th><td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                    <tr><th class="text-muted">IP</th><td><code>{{ $user->last_login_ip ?? '—' }}</code></td></tr>
                </tbody>
            </table>
        </div>

        @if($canEdit)
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0"><i class="fas fa-shield-halved text-primary me-2"></i>Sécurité</h2>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    2FA :
                    @if($user->google2fa_confirmed_at)
                        <span class="badge bg-success">Activée</span>
                        <span class="text-muted">depuis le {{ $user->google2fa_confirmed_at->format('d/m/Y') }}</span>
                    @else
                        <span class="badge bg-warning text-dark">Non activée</span>
                    @endif
                </p>

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.pages.users.reset2fa', $user) }}"
                          onsubmit="return confirm('Réinitialiser la 2FA de {{ $user->prenom }} {{ $user->nom }} ? Il devra réenrôler à sa prochaine connexion.');">
                        @csrf
                        <button class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-shield-halved me-2"></i>Réinitialiser la 2FA
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.pages.users.resetPassword', $user) }}"
                          onsubmit="return confirm('Générer un mot de passe temporaire pour {{ $user->prenom }} {{ $user->nom }} ?');">
                        @csrf
                        <input type="hidden" name="mode" value="temp_password">
                        <button class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-key me-2"></i>MDP temporaire
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.pages.users.resetPassword', $user) }}"
                          onsubmit="return confirm('Invalider le mot de passe et forcer une réactivation par email + téléphone ?');">
                        @csrf
                        <input type="hidden" name="mode" value="activation">
                        <button class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-rotate-left me-2"></i>Réactivation complète
                        </button>
                    </form>
                </div>

                @if($user->password_legacy)
                    <p class="small text-muted mt-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Cet utilisateur a encore un mot de passe SHA1 (legacy). Il sera automatiquement
                        rehashé en bcrypt à sa prochaine connexion réussie.
                    </p>
                @endif
            </div>
        </div>

        {{-- Block / Unblock --}}
        <div class="card mb-3 {{ $user->isBlocked() ? 'border-danger' : '' }}">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">
                    <i class="fas fa-{{ $user->isBlocked() ? 'lock-open' : 'ban' }} {{ $user->isBlocked() ? 'text-success' : 'text-danger' }} me-2"></i>
                    {{ $user->isBlocked() ? 'Débloquer' : 'Bloquer' }} le compte
                </h2>
            </div>
            <form method="POST" action="{{ route('admin.pages.users.toggleBlock', $user) }}"
                  class="card-body"
                  onsubmit="return confirm('{{ $user->isBlocked() ? 'Réactiver' : 'Bloquer' }} ce compte ?');">
                @csrf
                @if(! $user->isBlocked())
                    <label class="form-label small">Motif (optionnel, visible dans l'audit)</label>
                    <textarea name="reason" rows="2" maxlength="500" class="form-control mb-2"
                              placeholder="Ex&nbsp;: fin de mission, soupçon d'usurpation, départ de l'organisation…"></textarea>
                @endif
                <button class="btn btn-{{ $user->isBlocked() ? 'success' : 'danger' }} btn-sm w-100">
                    <i class="fas fa-{{ $user->isBlocked() ? 'lock-open' : 'ban' }} me-2"></i>
                    {{ $user->isBlocked() ? 'Débloquer le compte' : 'Bloquer le compte' }}
                </button>
                @if($errors->any())
                    <div class="text-danger small mt-2">{{ $errors->first('blocked_at') }}</div>
                @endif
            </form>
        </div>
        @endif
    </div>

    {{-- Roles + login attempts --}}
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0"><i class="fas fa-user-tag text-primary me-2"></i>Rôles attribués</h2>
            </div>
            @if($canEdit)
            <form method="POST" action="{{ route('admin.pages.users.roles', $user) }}" class="card-body">
                @csrf
                <div class="row g-2">
                    @foreach($allRoles as $r)
                        <div class="col-md-6">
                            <label class="form-check d-flex align-items-start gap-2 p-2 border rounded">
                                <input class="form-check-input mt-1" type="checkbox" name="role_ids[]" value="{{ $r->id }}"
                                       @checked(in_array($r->id, $assignedRoles, true))>
                                <span>
                                    <span class="fw-semibold d-block">{{ $r->name }}</span>
                                    <span class="text-muted small">{{ $r->description }}</span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-2"></i>Enregistrer les rôles
                    </button>
                </div>
            </form>
            @else
                <div class="card-body">
                    @forelse($user->roles as $r)
                        <span class="badge bg-primary me-1">{{ $r->name }}</span>
                    @empty
                        <span class="text-muted">Aucun rôle attribué.</span>
                    @endforelse
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i>20 dernières tentatives</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Quand</th>
                            <th>IP</th>
                            <th>Issue</th>
                            <th>Motif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loginAttempts as $a)
                            <tr>
                                <td class="small">{{ \Carbon\Carbon::parse($a->attempted_at)->format('d/m/Y H:i:s') }}</td>
                                <td><code>{{ $a->ip_address }}</code></td>
                                <td>
                                    @if($a->succeeded)
                                        <span class="badge bg-success-subtle text-success-emphasis"><i class="fas fa-check me-1"></i>ok</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger-emphasis"><i class="fas fa-xmark me-1"></i>échec</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $a->failure_reason ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Aucune tentative récente.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
