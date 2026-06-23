@extends('layouts.admin')

@section('title', 'Rôles &amp; permissions')
@section('page-title', 'Rôles &amp; permissions')

@section('content')

<p class="text-muted small">
    Baseline gérée depuis le code ({{ '`RoleSeeder`' }}) — {{ $roles->count() }} rôles, {{ $totalPermissions }} permissions au catalogue.
    @if($canEdit)
        Vous pouvez ajuster les permissions de chaque rôle ci-dessous.
        <span class="text-warning"><i class="fas fa-triangle-exclamation me-1"></i>Un nouveau déploiement réexécutant le seeder réinitialise ces réglages.</span>
    @else
        Cette page sert d'audit (lecture seule).
    @endif
</p>

<div class="row g-3">
    @foreach($roles as $role)
        @php
            $assigned = $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->all();
            $editable = $canEdit && $role->code !== 'super-admin';
        @endphp
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="h5 mb-1"><i class="fas fa-user-tag text-primary me-2"></i>{{ $role->name }}</h2>
                        <code class="small">{{ $role->code }}</code>
                    </div>
                    <span class="badge bg-primary">{{ $role->users_count }} utilisateurs</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted">{{ $role->description }}</p>

                    @if($editable)
                        <form method="POST" action="{{ route('admin.pages.roles.permissions.update', $role) }}">
                            @csrf
                            @method('PUT')
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="h6 mb-0">Permissions <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $role->permissions->count() }}</span></h3>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer
                                </button>
                            </div>
                            @foreach($allPermissions->groupBy('module') as $module => $perms)
                                <details class="w-100 border rounded p-2 mb-2">
                                    <summary class="fw-semibold small">
                                        <i class="fas fa-cube me-2 text-muted"></i>{{ $module }}
                                        <span class="badge bg-secondary-subtle ms-2">{{ $perms->whereIn('id', $assigned)->count() }} / {{ $perms->count() }}</span>
                                    </summary>
                                    <div class="mt-2">
                                        @foreach($perms as $p)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="permissions[]" value="{{ $p->id }}"
                                                       id="perm-{{ $role->id }}-{{ $p->id }}"
                                                       @checked(in_array((string) $p->id, $assigned, true))>
                                                <label class="form-check-label small" for="perm-{{ $role->id }}-{{ $p->id }}">
                                                    <code>{{ $p->pattern }}</code>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                            <button type="submit" class="btn btn-sm btn-primary mt-1">
                                <i class="fas fa-save me-1"></i>Enregistrer les permissions
                            </button>
                        </form>
                    @else
                        <h3 class="h6 mt-3">
                            {{ $role->permissions->count() }} permissions
                            @if($role->code === 'super-admin')
                                <span class="badge bg-dark ms-1"><i class="fas fa-lock me-1"></i>protégé</span>
                            @endif
                        </h3>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($role->permissions->groupBy('module') as $module => $perms)
                                <details class="w-100 border rounded p-2 mb-2">
                                    <summary class="fw-semibold small">
                                        <i class="fas fa-cube me-2 text-muted"></i>{{ $module }}
                                        <span class="badge bg-secondary-subtle ms-2">{{ $perms->count() }}</span>
                                    </summary>
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @foreach($perms as $p)
                                            <code class="small bg-light px-2 py-1 rounded">{{ $p->pattern }}</code>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

@endsection
