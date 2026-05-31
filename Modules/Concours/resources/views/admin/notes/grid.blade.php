@extends('layouts.admin')

@section('title', 'Notes — ' . $epreuve->libelle)
@section('page-title', 'Notes — ' . $epreuve->libelle)

@section('page-actions')
    <a href="{{ route('admin.pages.concours.notes.picker') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Changer d'épreuve
    </a>
@endsection

@section('content')
@php
    // Pre-computed array — passing a multi-line PHP literal directly into
    // @json() inside an Alpine x-data attribute trips Blade's regex on
    // PHP 8.4 ("Unclosed [ does not match )"). Pull it out instead.
    $gridData = [
        'epreuveId' => $epreuve->id,
        'noteMax'   => (float) $epreuve->note_max,
        'candidats' => $candidats,
    ];
@endphp
<div x-data='notesGrid(@json($gridData))'>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div>
                <span class="badge bg-light text-dark">{{ $epreuve->code }}</span>
                <strong class="ms-2">{{ $epreuve->libelle }}</strong>
                <span class="text-muted small ms-2">
                    Coef {{ $epreuve->coefficient }} · sur {{ $epreuve->note_max }} · {{ $epreuve->duree_minutes }} min
                </span>
            </div>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span x-show="statusMsg" x-text="statusMsg" class="small text-success"></span>
                <span class="text-muted small">
                    <span x-text="dirtyCount"></span> modifié(s)
                </span>
                <div class="form-check form-switch mb-0">
                    <input type="checkbox" x-model="lock" class="form-check-input" id="lock-toggle">
                    <label class="form-check-label small" for="lock-toggle">Verrouiller après envoi</label>
                </div>
                <button @click="save()" :disabled="saving || dirtyCount === 0 || hasErrors" class="btn btn-primary">
                    <span x-show="!saving"><i class="fas fa-save me-2"></i>Enregistrer</span>
                    <span x-show="saving"><i class="fas fa-spinner fa-spin me-2"></i>Envoi…</span>
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom &amp; prénom</th>
                        <th style="width: 12rem">Note</th>
                        <th style="width: 6rem" class="text-center">Absent</th>
                        <th>Commentaire</th>
                        <th style="width: 6rem" class="text-center">État</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in rows" :key="row.id">
                        <tr :class="{ 'table-warning': row.dirty }">
                            <td><code x-text="row.matricule_public"></code></td>
                            <td><span x-text="row.nom + ' ' + row.prenom"></span></td>
                            <td>
                                <input type="number" step="0.25" min="0" :max="noteMax"
                                       class="form-control form-control-sm"
                                       :class="{ 'is-invalid': errors[row.id] }"
                                       x-model="row.valeur"
                                       :disabled="row.absent || row.locked"
                                       @input="markDirty(row)">
                                <div class="invalid-feedback small" x-text="errors[row.id]"></div>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input"
                                       x-model="row.absent"
                                       :disabled="row.locked"
                                       @change="markDirty(row); if (row.absent) row.valeur = null">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm"
                                       x-model="row.commentaire"
                                       :disabled="row.locked"
                                       @input="markDirty(row)">
                            </td>
                            <td class="text-center">
                                <i x-show="row.locked" class="fas fa-lock text-muted" data-bs-toggle="tooltip" title="Verrouillé"></i>
                                <i x-show="!row.locked && row.dirty" class="fas fa-pen text-warning"></i>
                                <i x-show="!row.locked && !row.dirty && (row.valeur !== null || row.absent)" class="fas fa-check text-success"></i>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0">
                        <td colspan="6" class="text-center text-muted py-4">Aucun candidat éligible à cette épreuve.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
