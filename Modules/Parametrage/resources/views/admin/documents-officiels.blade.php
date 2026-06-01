@extends('layouts.admin')

@section('title', 'Documents officiels')
@section('page-title', 'Documents officiels')

@section('content')
<div x-data="{
        showCreate: false,
        editOpen: false,
        editData: { updateUrl: '', title: '', active: true },
        openEdit(d) { this.editData = Object.assign({}, this.editData, d); this.editOpen = true; }
     }">

    <p class="text-muted small">
        <a href="{{ route('admin.pages.parametrage.index') }}" class="text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Paramétrage</a>
        &middot; Documents (procès-verbaux, règlements, présentations…) affichés sur la page publique <code>/documents-officiels</code> et dans le pied de page.
    </p>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    @if($canEdit)
        <div class="d-flex justify-content-end mb-3">
            <button @click="showCreate = !showCreate" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Ajouter un document</button>
        </div>

        <div class="card mb-4" x-show="showCreate" x-transition x-cloak>
            <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="fas fa-circle-plus text-primary me-2"></i>Nouveau document</h2></div>
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.pages.parametrage.documents.store') }}" class="card-body">
                @csrf
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label small">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Ex : Règlement du concours 2026">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">Fichier (PDF ou image) <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept="application/pdf,image/*" required>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" @click="showCreate = false">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0"><i class="far fa-file-lines text-primary me-2"></i>Documents</h2>
            <small class="text-muted">{{ $documents->count() }} document(s)</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Taille</th>
                        <th>Statut</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        <tr>
                            <td class="fw-semibold">{{ $doc->title }}</td>
                            <td>
                                <span class="badge {{ $doc->isPdf() ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                                    <i class="far {{ $doc->isPdf() ? 'fa-file-pdf' : 'fa-file-image' }} me-1"></i>{{ $doc->isPdf() ? 'PDF' : 'Image' }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $doc->size_bytes ? number_format($doc->size_bytes / 1024, 0, ',', ' ') . ' Ko' : '—' }}</td>
                            <td>
                                @if($doc->active)
                                    <span class="badge bg-success-subtle text-success-emphasis"><i class="fas fa-eye me-1"></i>Publié</span>
                                @else
                                    <span class="badge bg-light text-muted border"><i class="fas fa-eye-slash me-1"></i>Masqué</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('documents.officiels.view', $doc) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Consulter"><i class="far fa-eye"></i></a>
                                <a href="{{ route('documents.officiels.download', $doc) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger"><i class="fas fa-download"></i></a>
                                @if($canEdit)
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            @click="openEdit(@js(['updateUrl' => route('admin.pages.parametrage.documents.update', $doc), 'title' => $doc->title, 'active' => (bool) $doc->active]))"
                                            title="Modifier"><i class="fas fa-pen"></i></button>
                                    <form method="POST" action="{{ route('admin.pages.parametrage.documents.destroy', $doc) }}" class="d-inline" onsubmit="return confirm('Supprimer définitivement ce document ?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun document officiel.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Edit modal --}}
    <div class="modal" tabindex="-1" x-cloak :class="{ 'd-block': editOpen }"
         style="background: rgba(15,23,42,.55);" @keydown.escape.window="editOpen = false">
        <div class="modal-dialog modal-dialog-centered" @click.stop>
            <form method="POST" enctype="multipart/form-data" :action="editData.updateUrl" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-pen text-primary me-2"></i>Modifier le document</h5>
                    <button type="button" class="btn-close" @click="editOpen = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" x-model="editData.title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Remplacer le fichier (optionnel)</label>
                        <input type="file" name="file" class="form-control" accept="application/pdf,image/*">
                        <div class="form-text small">Laissez vide pour conserver le fichier actuel.</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="doc-active" x-model="editData.active">
                        <label class="form-check-label" for="doc-active">Publié (visible sur le site public)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="editOpen = false">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
