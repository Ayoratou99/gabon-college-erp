<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <span class="text-muted small px-2">
                    @if(\Modules\Concours\Models\ConcoursSession::active())
                        Session active&nbsp;:
                        <strong>{{ \Modules\Concours\Models\ConcoursSession::active()->code }}</strong>
                    @endif
                </span>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
            @php
                $user = auth()->user();
                $primaryRole = $user?->roles->first();
            @endphp

            @if($primaryRole)
                <li class="nav-item d-none d-md-block me-3">
                    <span class="user-badge-pill">{{ $primaryRole->name }}</span>
                </li>
            @endif

            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                    <i class="far fa-user-circle fs-5 me-2"></i>
                    <span class="d-none d-md-inline small fw-semibold">
                        {{ $user?->prenom }} {{ $user?->nom }}
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2 small text-muted">
                        {{ $user?->email ?: $user?->telephone }}
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-sign-out-alt me-2"></i> Se déconnecter
                            </button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
