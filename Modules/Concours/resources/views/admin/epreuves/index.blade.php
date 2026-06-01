@extends('layouts.admin')

@section('title', 'Épreuves')
@section('page-title', 'Épreuves')

@section('content')
<div x-data="{
    showForm: false,
    editingId: null,
    form: { code:'', libelle:'', type_epreuve_id:'', sections:[], coefficient:1.0, duree_minutes:120, note_max:20, ordre:0 },
    loading: false,
    message: '',
    table: null,
    init() {
        this.table = window.cukDataTable('#epreuves-table', {
            url: '{{ route('admin.pages.concours.epreuves.data') }}',
            order: [[5, 'asc']],
            columns: [
                { data: 'code' },
                { data: 'libelle' },
                { data: 'type' },
                { data: 'scope',       orderable: false },
                { data: 'coefficient', className: 'text-end' },
                { data: 'duree',       className: 'text-end' },
                { data: 'centres',     orderable: false, searchable: false, className: 'text-end' },
                { data: 'actions',     orderable: false, searchable: false, className: 'text-end' },
            ],
        });
        document.getElementById('epreuves-table').addEventListener('click', (e) => {
            const del = e.target.closest('[data-delete]')?.dataset.delete;
            if (del) { this.destroy(del); return; }
            const edit = e.target.closest('[data-edit]')?.dataset.edit;
            if (edit) this.startEdit(JSON.parse(edit));
        });
    },
    blankForm() { return { code:'', libelle:'', type_epreuve_id:'', sections:[], coefficient:1.0, duree_minutes:120, note_max:20, ordre:0 }; },
    openCreate() { this.editingId = null; this.form = this.blankForm(); this.message = ''; this.showForm = true; },
    startEdit(p) {
        this.editingId = p.id;
        this.form = { code:p.code, libelle:p.libelle, type_epreuve_id:p.type_epreuve_id, sections:p.sections || [], coefficient:p.coefficient, duree_minutes:p.duree_minutes, note_max:p.note_max, ordre:p.ordre };
        this.message = '';
        this.showForm = true;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    async save() {
        if (!this.form.sections.length) { this.message = 'Sélectionnez au moins une section concernée.'; return; }
        this.loading = true; this.message = '';
        const payload = { ...this.form, concours_session_id: '{{ $session?->id }}' };
        try {
            if (this.editingId) {
                await window.axios.put('/api/admin/concours/epreuves/' + this.editingId, payload);
            } else {
                await window.axios.post('/api/admin/concours/epreuves', payload);
            }
            this.showForm = false;
            this.editingId = null;
            this.form = this.blankForm();
            this.table.ajax.reload(null, false);
        } catch (e) {
            this.message = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.';
        } finally { this.loading = false; }
    },
    async destroy(id) {
        if (!confirm('Supprimer cette épreuve ?')) return;
        await window.axios.delete('/api/admin/concours/epreuves/' + id);
        this.table.ajax.reload(null, false);
    }
}">

    @if(! ($sessionEditable ?? true))
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="fas fa-lock fa-lg"></i>
            <div>
                <strong>Session archivée.</strong>
                Les épreuves de
                <em>{{ $session?->libelle ?? 'cette session' }}</em>
                sont consultables mais ne sont plus modifiables.
            </div>
        </div>
    @endif

    @if($canManage)
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <p class="text-muted small mb-0">Session <strong>{{ $session?->libelle ?? 'aucune' }}</strong></p>
            <button @click="openCreate()" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-2"></i>Nouvelle épreuve
            </button>
        </div>

        <div class="card mb-3" x-show="showForm" x-transition>
            <div class="card-body">
                <h3 class="h6 mb-3" x-text="editingId ? 'Modifier l\'épreuve' : 'Nouvelle épreuve'"></h3>
                <div class="row g-3">
                    <div class="col-md-2"><label class="form-label small">Code *</label><input x-model="form.code" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small">Libellé *</label><input x-model="form.libelle" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label small">Type *</label>
                        <select x-model="form.type_epreuve_id" class="form-select">
                            <option value="">—</option>
                            @foreach($types as $t)<option value="{{ $t->id }}">{{ $t->libelle }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Sections concernées <span class="text-danger">*</span>
                            <span class="text-muted">— une ou plusieurs</span></label>
                        <div class="d-flex flex-wrap gap-1 border rounded p-2" style="max-height:170px; overflow:auto;">
                            @forelse($sections as $s)
                                <label class="d-flex align-items-center gap-2 me-3 mb-1" style="min-width:14rem; cursor:pointer;">
                                    <input type="checkbox" class="form-check-input mt-0" value="{{ $s->id }}" x-model="form.sections">
                                    <span class="small"><code>{{ $s->code }}</code> {{ $s->nom }}</span>
                                </label>
                            @empty
                                <span class="text-muted small">Aucune section ouverte au concours — activez « Ouvert au concours » dans Référentiels &rsaquo; Formations.</span>
                            @endforelse
                        </div>
                        <div class="form-text small" x-show="form.sections.length">
                            <i class="fas fa-check text-success me-1"></i><span x-text="form.sections.length"></span> section(s) sélectionnée(s).
                        </div>
                    </div>
                    <div class="col-md-2"><label class="form-label small">Coef *</label><input type="number" step="0.1" x-model="form.coefficient" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label small">Durée (min) *</label><input type="number" x-model="form.duree_minutes" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label small">Note max</label><input type="number" step="0.5" x-model="form.note_max" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label small">Ordre</label><input type="number" x-model="form.ordre" class="form-control"></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <span x-show="message" x-text="message" class="text-danger small align-self-center"></span>
                    <button @click="showForm = false; editingId = null" class="btn btn-outline-secondary">Annuler</button>
                    <button @click="save()" :disabled="loading" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <x-admin.datatable id="epreuves-table"
            :headings="['Code', 'Libellé', 'Type', 'Sections', 'Coef', 'Durée', 'Planifiée', '']" />
    </div>
</div>
@endsection
