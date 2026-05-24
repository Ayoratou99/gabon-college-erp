@extends('layouts.admin')

@section('title', 'Épreuves')
@section('page-title', 'Épreuves')

@section('content')
<div x-data="{
    showForm: false,
    form: { code:'', libelle:'', type_epreuve_id:'', scope_type:'cycle', scope_id:'', coefficient:1.0, duree_minutes:120, note_max:20, ordre:0 },
    loading: false,
    message: '',
    async save() {
        this.loading = true; this.message = '';
        try {
            await window.axios.post('/api/admin/concours/epreuves', {
                ...this.form,
                concours_session_id: '{{ $session?->id }}',
            });
            window.location.reload();
        } catch (e) {
            this.message = e.response?.data?.message ?? 'Erreur lors de la création.';
        } finally { this.loading = false; }
    },
    async destroy(id) {
        if (!confirm('Supprimer cette épreuve ?')) return;
        await window.axios.delete('/api/admin/concours/epreuves/' + id);
        window.location.reload();
    }
}">

    @if($canManage)
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <p class="text-muted small mb-0">Session <strong>{{ $session?->libelle ?? 'aucune' }}</strong></p>
            <button @click="showForm = !showForm" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-2"></i>Nouvelle épreuve
            </button>
        </div>

        <div class="card mb-3" x-show="showForm" x-transition>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2"><label class="form-label small">Code *</label><input x-model="form.code" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small">Libellé *</label><input x-model="form.libelle" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label small">Type *</label>
                        <select x-model="form.type_epreuve_id" class="form-select">
                            <option value="">—</option>
                            @foreach($types as $t)<option value="{{ $t->id }}">{{ $t->libelle }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label small">Portée *</label>
                        <div class="input-group">
                            <select x-model="form.scope_type" class="form-select" style="max-width:110px">
                                <option value="cycle">Cycle</option>
                                <option value="section">Section</option>
                            </select>
                            <select x-model="form.scope_id" class="form-select">
                                <option value="">—</option>
                                <template x-if="form.scope_type === 'cycle'">
                                    <optgroup label="Cycles">
                                        @foreach($cycles as $c)<option value="{{ $c->id }}">{{ $c->nom }}</option>@endforeach
                                    </optgroup>
                                </template>
                                <template x-if="form.scope_type === 'section'">
                                    <optgroup label="Sections">
                                        @foreach($sections as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->nom }}</option>@endforeach
                                    </optgroup>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2"><label class="form-label small">Coef *</label><input type="number" step="0.1" x-model="form.coefficient" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label small">Durée (min) *</label><input type="number" x-model="form.duree_minutes" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label small">Note max</label><input type="number" step="0.5" x-model="form.note_max" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label small">Ordre</label><input type="number" x-model="form.ordre" class="form-control"></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <span x-show="message" x-text="message" class="text-danger small align-self-center"></span>
                    <button @click="showForm = false" class="btn btn-outline-secondary">Annuler</button>
                    <button @click="save()" :disabled="loading" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Type</th>
                        <th>Portée</th>
                        <th class="text-end">Coef</th>
                        <th class="text-end">Durée</th>
                        <th class="text-end">Planifiée</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($epreuves as $e)
                        <tr>
                            <td><code>{{ $e->code }}</code></td>
                            <td>{{ $e->libelle }}</td>
                            <td>{{ $e->typeEpreuve?->libelle }}</td>
                            <td><small class="text-muted">{{ $e->scope_type }}</small></td>
                            <td class="text-end">{{ $e->coefficient }}</td>
                            <td class="text-end">{{ $e->duree_minutes }} min</td>
                            <td class="text-end">{{ $e->plannings->count() }} centre(s)</td>
                            <td class="text-end">
                                <a href="{{ route('admin.pages.concours.notes.grid', $e) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-marker"></i> Notes
                                </a>
                                @if($canManage)
                                    <button @click="destroy('{{ $e->id }}')" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Aucune épreuve.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
