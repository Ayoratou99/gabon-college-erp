@extends('layouts.admin')

@section('title', 'Utilisateurs')
@section('page-title', 'Utilisateurs')

@section('content')
<div x-data="{
    role: '',
    status: '',
    table: null,
    init() {
        this.table = window.cukDataTable('#users-table', {
            url: '{{ route('admin.pages.users.data') }}',
            order: [[4, 'desc']],
            filters: () => ({ role: this.role, status: this.status }),
            columns: [
                { data: 'nom' },
                { data: 'email' },
                { data: 'telephone',     orderable: true },
                { data: 'roles',         orderable: false, searchable: false },
                { data: 'twofa',         orderable: false, searchable: false, className: 'text-center' },
                { data: 'last_login_at', orderable: true,  searchable: false },
                { data: 'actions',       orderable: false, searchable: false, className: 'text-end' },
            ],
        });
        this.$watch('role',   () => this.table.draw());
        this.$watch('status', () => this.table.draw());
    },
}">

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Rôle</label>
                <select x-model="role" class="form-select">
                    <option value="">— Tous les rôles —</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->code }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Filtre</label>
                <select x-model="status" class="form-select">
                    <option value="">— Tous —</option>
                    <option value="legacy">Mots de passe SHA1 (legacy)</option>
                    <option value="must_set">En attente d'activation</option>
                    <option value="2fa_off">2FA non activée</option>
                </select>
            </div>
            <div class="col-md-4 d-flex justify-content-end">
                <button @click="role=''; status=''" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-rotate-left me-2"></i>Réinitialiser
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <x-admin.datatable id="users-table"
            :headings="['Nom &amp; prénom', 'Email', 'Téléphone', 'Rôles', '2FA', 'Dernière connexion', '']" />
    </div>
</div>
@endsection
