@php
    $activeSession = \Modules\Concours\Models\ConcoursSession::active();
    $user = auth()->user();
    // Active role for THIS session — falls back to the first role for
    // single-role users (the EnsureActiveRole middleware also auto-pins
    // it, so the dropdown always reflects reality).
    $activeRole  = $user?->activeRole() ?? $user?->roles->first();
    $hasMultipleRoles = $user && $user->roles->count() > 1;
@endphp
<nav class="app-header navbar navbar-expand">
    <div class="container-fluid">

        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" title="Réduire le menu">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            @if($activeSession)
                <li class="nav-item d-none d-md-flex align-items-center ms-2 topbar-session">
                    <i class="fas fa-circle-check text-success me-2"></i>
                    <div class="lh-1">
                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.1em; font-size:.66rem;">Session active</div>
                        <div class="small fw-semibold">{{ $activeSession->libelle ?? $activeSession->code }}</div>
                    </div>
                </li>
            @endif
        </ul>

        <ul class="navbar-nav ms-auto align-items-center gap-2">

            <li class="nav-item d-none d-lg-block">
                <a href="{{ route('home') }}" target="_blank" class="topbar-icon" title="Site public">
                    <i class="fas fa-globe"></i>
                </a>
            </li>
            <li class="nav-item d-none d-lg-block">
                <a href="{{ route('admin.pages.concours.sessions.index') }}" class="topbar-icon" title="Gérer les sessions">
                    <i class="fas fa-calendar-check"></i>
                </a>
            </li>

            @if($activeRole)
                <li class="nav-item d-none d-md-block">
                    <span class="user-badge-pill" title="Rôle actif sur cette session">
                        <i class="fas fa-user-tag me-1"></i>{{ $activeRole->name }}
                    </span>
                </li>
            @endif

            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <span class="user-avatar">
                        {{ strtoupper(mb_substr($user?->prenom ?? '?', 0, 1) . mb_substr($user?->nom ?? '?', 0, 1)) }}
                    </span>
                    <span class="d-none d-md-inline lh-1">
                        <span class="d-block fw-semibold small">{{ $user?->prenom }} {{ $user?->nom }}</span>
                        <span class="d-block text-muted" style="font-size:.7rem;">{{ $user?->email ?: $user?->telephone }}</span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                    <li class="px-3 py-2">
                        <div class="fw-semibold">{{ $user?->prenom }} {{ $user?->nom }}</div>
                        <div class="small text-muted">{{ $user?->email ?: $user?->telephone }}</div>
                        @foreach(($user?->roles ?? []) as $r)
                            @if($activeRole && $r->id === $activeRole->id)
                                <span class="badge bg-primary me-1 mt-1" title="Rôle actif">
                                    <i class="fas fa-check me-1"></i>{{ $r->name }}
                                </span>
                            @else
                                <span class="badge bg-primary-subtle text-primary-emphasis me-1 mt-1">{{ $r->name }}</span>
                            @endif
                        @endforeach
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    @if($hasMultipleRoles)
                        <li><a class="dropdown-item text-primary" href="{{ route('role.switch') }}">
                            <i class="fas fa-arrows-rotate me-2"></i>Changer de rôle
                        </a></li>
                    @endif
                    @if($user)
                        <li><a class="dropdown-item" href="{{ route('admin.pages.users.show', $user->id) }}">
                            <i class="far fa-user me-2"></i>Mon profil
                        </a></li>
                    @endif
                    <li><a class="dropdown-item" href="{{ route('home') }}" target="_blank">
                        <i class="fas fa-globe me-2"></i>Site public
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Se déconnecter
                            </button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
