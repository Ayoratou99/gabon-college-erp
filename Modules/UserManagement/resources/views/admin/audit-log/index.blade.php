@extends('layouts.admin')

@section('title', 'Journal d\'audit')
@section('page-title', 'Journal d\'audit')

@section('page-actions')
    <a href="#" id="audit-export-link" class="btn btn-outline-success btn-sm">
        <i class="fas fa-file-csv me-2"></i>Exporter (CSV)
    </a>
@endsection

@section('content')
<section x-data="{
        source: '',
        event_type: '',
        from: '',
        to: '',
        actor_user_id: '',
        actor_search: '',
        ip: '',
        table: null,
        filters() {
            return {
                source: this.source,
                event_type: this.event_type,
                from: this.from,
                to: this.to,
                actor_user_id: this.actor_user_id,
                actor_search: this.actor_search,
                ip: this.ip,
            };
        },
        init() {
            this.table = window.cukDataTable('#audit-log-table', {
                url: '{{ route('admin.pages.audit-log.data') }}',
                pageLength: 50,
                order: [],
                filters: () => this.filters(),
                columns: [
                    { data: 'at',         className: 'small text-nowrap', orderable: false },
                    { data: 'source',     orderable: false },
                    { data: 'event_type', orderable: false },
                    { data: 'actor',      orderable: false },
                    { data: 'target',     orderable: false },
                    { data: 'field',      orderable: false, searchable: false },
                    { data: 'change',     orderable: false, searchable: false },
                    { data: 'ip',         orderable: false, searchable: false },
                ],
            });
            // Keep the export link in sync with current filters.
            const refreshExport = () => {
                const u = new URL('{{ route('admin.pages.audit-log.export') }}', window.location.origin);
                const f = this.filters();
                Object.entries(f).forEach(([k, v]) => { if (v) u.searchParams.set(k, v); });
                document.getElementById('audit-export-link').href = u.toString();
            };
            ['source','event_type','from','to','actor_user_id','actor_search','ip']
                .forEach(k => this.$watch(k, () => { refreshExport(); this.table.draw(); }));
            refreshExport();
        },
        reset() {
            this.source = this.event_type = this.from = this.to = this.actor_user_id = this.actor_search = this.ip = '';
        },
    }">

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Source</label>
                <select x-model="source" class="form-select form-select-sm">
                    <option value="">— Toutes —</option>
                    @foreach($sources as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Type d'évènement</label>
                <select x-model="event_type" class="form-select form-select-sm">
                    <option value="">— Tous —</option>
                    @foreach($eventTypes as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Du</label>
                <input x-model="from" type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Au</label>
                <input x-model="to" type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Acteur (par compte)</label>
                <select x-model="actor_user_id" class="form-select form-select-sm">
                    <option value="">— Tous —</option>
                    @foreach($actorUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->nom }} {{ $u->prenom }} ({{ $u->email ?: '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small mb-1">Recherche acteur (nom / email)</label>
                <input x-model.debounce.300ms="actor_search" type="text" class="form-control form-control-sm" placeholder="Ex : nom partiel, prenom, email...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">IP (contient)</label>
                <input x-model.debounce.300ms="ip" type="text" class="form-control form-control-sm" placeholder="Ex : 192.168.">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button @click="reset()" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-rotate-left me-2"></i>Réinitialiser
                </button>
            </div>
            <div class="col-md-3 small text-muted">
                <i class="fas fa-circle-info me-1"></i>
                Couvre les modifications de dossier, les changements de paramètres
                et les tentatives de connexion.
            </div>
        </div>
    </div>

    <div class="card">
        <x-admin.datatable id="audit-log-table"
            :headings="['Quand', 'Source', 'Évènement', 'Acteur', 'Cible', 'Champ', 'Ancien → Nouveau', 'IP']" />
    </div>
</section>
@endsection
