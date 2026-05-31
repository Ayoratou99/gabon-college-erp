@extends('layouts.admin')

@section('title', 'Candidats')
@section('page-title', 'Candidats')

@section('page-actions')
    <div class="btn-group" data-export-group>
        <a href="{{ route('admin.pages.concours.candidats.export', ['format' => 'xlsx']) }}"
           data-export="xlsx" class="btn btn-success btn-sm">
            <i class="far fa-file-excel me-2"></i>Excel
        </a>
        <a href="{{ route('admin.pages.concours.candidats.export', ['format' => 'csv']) }}"
           data-export="csv" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-csv me-2"></i>CSV
        </a>
        <a href="{{ route('admin.pages.concours.candidats.export', ['format' => 'pdf']) }}"
           data-export="pdf" class="btn btn-danger btn-sm">
            <i class="far fa-file-pdf me-2"></i>PDF
        </a>
    </div>
@endsection

@section('content')
    @if($session)
        <p class="text-muted small mb-3">Session <strong>{{ $session->libelle }}</strong></p>
    @endif

    <div x-data="{
            statut: '', centre_id: '', section_id: '', serie_bac_id: '',
            deja_bac: '', sexe: '', admis_only: false, paye: '',
            table: null,
            filters() {
                return {
                    statut: this.statut, centre_id: this.centre_id, section_id: this.section_id,
                    serie_bac_id: this.serie_bac_id, deja_bac: this.deja_bac, sexe: this.sexe,
                    admis_only: this.admis_only ? 1 : 0,
                    paye: this.paye,
                };
            },
            init() {
                this.table = window.cukDataTable('#candidats-table', {
                    url: '{{ route('admin.pages.concours.candidats.data') }}',
                    order: [[5, 'desc']],
                    filters: () => this.filters(),
                    columns: [
                        { data: 'matricule_public', render: (d) => `<code>${d}</code>` },
                        { data: 'nom' },
                        { data: 'centre' },
                        { data: 'premier_choix' },
                        { data: 'statut',      orderable: true,  className: 'text-nowrap' },
                        { data: 'created_at',  orderable: true,  className: 'text-end text-muted small' },
                        { data: 'actions',     orderable: false, searchable: false, className: 'text-end' },
                    ],
                });
                ['statut','centre_id','section_id','serie_bac_id','deja_bac','sexe','admis_only','paye']
                    .forEach(k => this.$watch(k, () => { this.syncExports(); this.table.draw(); }));
                this.syncExports();
            },
            syncExports() {
                const f = this.filters();
                document.querySelectorAll('[data-export-group] [data-export]').forEach(a => {
                    const u = new URL(a.dataset.baseHref ?? a.href, window.location.origin);
                    if (!a.dataset.baseHref) a.dataset.baseHref = a.getAttribute('href');
                    const q = u.searchParams;
                    Object.keys(f).forEach(k => q.delete(k));
                    Object.entries(f).forEach(([k, v]) => { if (v !== '' && v !== 0) q.set(k, v); });
                    a.href = u.pathname + (q.toString() ? '?' + q.toString() : '');
                });
            },
            reset() {
                this.statut=''; this.centre_id=''; this.section_id='';
                this.serie_bac_id=''; this.deja_bac=''; this.sexe=''; this.admis_only=false; this.paye='';
            },
         }">

        <div class="card mb-3">
            <div class="card-body row g-3 align-items-end">
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
                    <label class="form-label small mb-1">Section (premier choix)</label>
                    <select x-model="section_id" class="form-select form-select-sm">
                        <option value="">— Toutes —</option>
                        @foreach($sections as $s)
                            <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Statut</label>
                    <select x-model="statut" class="form-select form-select-sm">
                        <option value="">— Tous —</option>
                        @foreach($statuses as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Série du Bac</label>
                    <select x-model="serie_bac_id" class="form-select form-select-sm">
                        <option value="">— Toutes —</option>
                        @foreach($series as $s)
                            <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Bac obtenu</label>
                    <select x-model="deja_bac" class="form-select form-select-sm">
                        <option value="">— Indifférent —</option>
                        <option value="oui">Avec le BAC</option>
                        <option value="non">Sans le BAC</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Sexe</label>
                    <select x-model="sexe" class="form-select form-select-sm">
                        <option value="">— Tous —</option>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Paiement</label>
                    <select x-model="paye" class="form-select form-select-sm">
                        <option value="">— Tous —</option>
                        <option value="oui">Payé (au moins un paiement confirmé)</option>
                        <option value="attente">Accepté à payer, non payé</option>
                        <option value="non">Sans paiement requis</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="filterAdmis" x-model="admis_only">
                        <label class="form-check-label small" for="filterAdmis">Admis au concours uniquement</label>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-end">
                    <button @click="reset()" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-rotate-left me-2"></i>Réinitialiser
                    </button>
                </div>
            </div>
            <div class="card-footer bg-white small text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Astuce : cliquez sur l'en-tête « Reçu le » pour trier par date d'inscription, ou utilisez le sort sur « Nom » pour un tri alphabétique. Le tri par âge est disponible via la colonne « Date de naissance » en exportant.
            </div>
        </div>

        <div class="card">
            <x-admin.datatable id="candidats-table"
                :headings="['Matricule', 'Nom &amp; prénom', 'Centre', 'Premier choix', 'Statut', 'Reçu le', '']" />
        </div>
    </div>
@endsection
