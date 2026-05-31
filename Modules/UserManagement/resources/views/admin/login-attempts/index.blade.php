@extends('layouts.admin')

@section('title', 'Tentatives de connexion')
@section('page-title', 'Tentatives de connexion')

@section('content')
<div x-data="{
    outcome: '',
    days: '7',
    table: null,
    init() {
        this.table = window.cukDataTable('#attempts-table', {
            url: '{{ route('admin.pages.login-attempts.data') }}',
            order: [[0, 'desc']],
            filters: () => ({ outcome: this.outcome, days: this.days }),
            columns: [
                { data: 'attempted_at' },
                { data: 'identifier' },
                { data: 'user',       orderable: false, searchable: false },
                { data: 'ip_address' },
                { data: 'succeeded',  orderable: true, searchable: false, className: 'text-center' },
                { data: 'reason',     orderable: false },
                { data: 'user_agent', orderable: false, searchable: false },
            ],
        });
        this.$watch('outcome', () => this.table.draw());
        this.$watch('days',    () => this.table.draw());
    },
}">

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Issue</label>
                <select x-model="outcome" class="form-select">
                    <option value="">— Toutes —</option>
                    <option value="succeeded">Réussies</option>
                    <option value="failed">Échouées</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Fenêtre</label>
                <select x-model="days" class="form-select">
                    <option value="">— Tout l'historique —</option>
                    <option value="1">Dernières 24 h</option>
                    <option value="7">7 derniers jours</option>
                    <option value="30">30 derniers jours</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <x-admin.datatable id="attempts-table"
            :headings="['Date', 'Identifiant', 'Utilisateur', 'IP', 'Issue', 'Motif', 'User-Agent']" />
    </div>
</div>
@endsection
