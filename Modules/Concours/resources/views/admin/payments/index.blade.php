@extends('layouts.admin')

@section('title', 'Paiements')
@section('page-title', 'Paiements')

@section('page-actions')
    <div class="text-muted small">
        <i class="fas fa-eye me-1"></i> Lecture seule — aucun ajustement n'est possible depuis ce module.
    </div>
@endsection

@section('content')
    <div x-data="{
            status: '', centre_id: '', concours_session_id: '', paid_only: false,
            date_from: '', date_to: '', matricule: '',
            table: null,
            filters() {
                return {
                    status:              this.status,
                    centre_id:           this.centre_id,
                    concours_session_id: this.concours_session_id,
                    paid_only:           this.paid_only ? 1 : 0,
                    date_from:           this.date_from,
                    date_to:             this.date_to,
                    matricule:           this.matricule,
                };
            },
            init() {
                this.table = window.cukDataTable('#payments-table', {
                    url: '{{ route('admin.pages.concours.payments.data') }}',
                    order: [[0, 'desc']],
                    filters: () => this.filters(),
                    columns: [
                        { data: 'created_at',         className: 'text-muted small text-nowrap' },
                        { data: 'matricule',          orderable: false },
                        { data: 'candidat',           orderable: false },
                        { data: 'centre',             orderable: false },
                        { data: 'session',            orderable: false },
                        { data: 'amount',             className: 'text-end text-nowrap' },
                        { data: 'status',             className: 'text-nowrap' },
                        { data: 'paid_at',            className: 'text-muted small text-nowrap' },
                        { data: 'external_reference', orderable: false, searchable: false },
                        { data: 'actions',            orderable: false, searchable: false, className: 'text-end' },
                    ],
                });
                ['status','centre_id','concours_session_id','paid_only','date_from','date_to','matricule']
                    .forEach(k => this.$watch(k, () => this.table.draw()));
            },
            reset() {
                this.status=''; this.centre_id=''; this.concours_session_id='';
                this.paid_only=false; this.date_from=''; this.date_to=''; this.matricule='';
            },
        }">

        <div class="card mb-3">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Statut</label>
                    <select x-model="status" class="form-select form-select-sm">
                        <option value="">— Tous —</option>
                        @foreach($statuses as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Centre</label>
                    <select x-model="centre_id" class="form-select form-select-sm">
                        <option value="">— Tous —</option>
                        @foreach($centres as $c)
                            <option value="{{ $c->id }}">{{ $c->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Session</label>
                    <select x-model="concours_session_id" class="form-select form-select-sm">
                        <option value="">— Toutes —</option>
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Candidat (matricule / nom / email)</label>
                    <input x-model.debounce.300ms="matricule" type="text" class="form-control form-control-sm"
                           placeholder="Ex: CUK-…, nom, email">
                </div>

                <div class="col-md-3">
                    <label class="form-label small mb-1">Date d'initiation (du)</label>
                    <input x-model="date_from" type="date" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Date d'initiation (au)</label>
                    <input x-model="date_to" type="date" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="filterPaidOnly" x-model="paid_only">
                        <label class="form-check-label small" for="filterPaidOnly">Paiements confirmés uniquement</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex justify-content-end">
                    <button @click="reset()" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-rotate-left me-2"></i>Réinitialiser
                    </button>
                </div>
            </div>
            <div class="card-footer bg-white small text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Toutes les colonnes triables le sont via leur en-tête. La référence est tronquée &mdash; survolez pour la voir complète, ou cliquez sur « Détail ».
            </div>
        </div>

        <div class="card">
            <x-admin.datatable id="payments-table"
                :headings="['Initié le', 'Matricule', 'Candidat', 'Centre', 'Session', 'Montant', 'Statut', 'Payé le', 'Référence', '']" />
        </div>
    </div>
@endsection
