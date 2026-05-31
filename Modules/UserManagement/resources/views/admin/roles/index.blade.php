@extends('layouts.admin')

@section('title', 'Rôles &amp; permissions')
@section('page-title', 'Rôles &amp; permissions')

@section('content')

<p class="text-muted small">
    Les rôles sont gérés depuis le code ({{ '`RoleSeeder`' }}). Cette page sert d'audit : {{ $roles->count() }} rôles, {{ $totalPermissions }} permissions au catalogue.
</p>

<div class="row g-3">
    @foreach($roles as $role)
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

                    <h3 class="h6 mt-3">{{ $role->permissions->count() }} permissions</h3>
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
                </div>
            </div>
        </div>
    @endforeach
</div>

@endsection
