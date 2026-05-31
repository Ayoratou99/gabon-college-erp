@extends('layouts.admin')

@section('title', 'Centres d\'examen')
@section('page-title', 'Centres d\'examen')

@section('page-actions')
    @if($canEdit)
        <a href="{{ route('admin.pages.concours.chef_centres.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-user-tie me-2"></i>Chefs de centre
        </a>
    @endif
@endsection

@section('content')
<section x-data="{
        showCreate: false,
        editingId: null,
        toggle(id) { this.editingId = (this.editingId === id ? null : id); },
    }">

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($activeSession)
        <p class="text-muted small mb-3">
            Session active&nbsp;: <strong>{{ $activeSession->libelle }}</strong>
            <code>{{ $activeSession->code }}</code>.
            Les compteurs « Chefs » et « Candidats » correspondent à cette session.
        </p>
    @else
        <div class="alert alert-warning small">
            Aucune session active &mdash; les compteurs ne s'afficheront pas tant qu'une session n'est pas activée.
        </div>
    @endif

    @if($canEdit)
        <div class="d-flex justify-content-end mb-3">
            <button @click="showCreate = !showCreate" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouveau centre
            </button>
        </div>

        <div class="card mb-4" x-show="showCreate" x-transition x-cloak>
            <div class="card-header bg-white">
                <h2 class="h6 mb-0"><i class="fas fa-circle-plus text-primary me-2"></i>Créer un centre</h2>
            </div>
            <form method="POST" action="{{ route('admin.pages.concours.centres.store') }}" class="card-body">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required maxlength="30" placeholder="LBV-01">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">Nom du centre <span class="text-danger">*</span></label>
                        <input type="text" name="nom" class="form-control" required maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Ville</label>
                        <input type="text" name="ville" class="form-control" maxlength="100">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small">Province</label>
                        <select name="province_id" class="form-select">
                            <option value="">— Aucune —</option>
                            @foreach($provinces as $p)
                                <option value="{{ $p->id }}">{{ $p->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Capacité par défaut</label>
                        <input type="number" name="capacite_par_defaut" class="form-control" min="1" max="100000" value="200">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Ordre</label>
                        <input type="number" name="display_order" class="form-control" min="0" max="999" value="0">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="create-active" checked>
                            <label class="form-check-label" for="create-active">Actif</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Adresse</label>
                        <textarea name="adresse" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" @click="showCreate = false">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Créer
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0"><i class="fas fa-building-columns text-primary me-2"></i>Tous les centres</h2>
            <small class="text-muted">{{ $centres->count() }} centre(s)</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Ville / Province</th>
                        <th class="text-end">Capacité</th>
                        <th class="text-end">Chefs</th>
                        <th class="text-end">Candidats</th>
                        <th>Statut</th>
                        @if($canEdit)
                            <th class="text-end"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($centres as $c)
                        <tr :class="editingId === '{{ $c->id }}' ? 'table-active' : ''">
                            <td><code>{{ $c->code }}</code></td>
                            <td class="fw-semibold">{{ $c->nom }}</td>
                            <td class="small text-muted">
                                {{ $c->ville ?: '—' }}
                                @if($c->province?->nom)
                                    <br><span class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>{{ $c->province->nom }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((int) $c->capacite_par_defaut, 0, ',', ' ') }}</td>
                            <td class="text-end">
                                @php $chefCount = $chefCounts[$c->id] ?? 0; @endphp
                                @if($chefCount === 0)
                                    <span class="badge bg-warning text-dark" title="Aucun chef assigné pour la session active">
                                        <i class="fas fa-triangle-exclamation me-1"></i>0
                                    </span>
                                @else
                                    <span class="badge bg-info">{{ $chefCount }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((int) ($candidatCounts[$c->id] ?? 0), 0, ',', ' ') }}</td>
                            <td>
                                @if($c->active)
                                    <span class="badge bg-success">Actif</span>
                                @else
                                    <span class="badge bg-secondary">Désactivé</span>
                                @endif
                            </td>
                            @if($canEdit)
                                <td class="text-end">
                                    <button @click="toggle('{{ $c->id }}')" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-pen me-1"></i>
                                        <span x-show="editingId !== '{{ $c->id }}'">Modifier</span>
                                        <span x-show="editingId === '{{ $c->id }}'">Fermer</span>
                                    </button>
                                </td>
                            @endif
                        </tr>

                        @if($canEdit)
                            <tr x-show="editingId === '{{ $c->id }}'" x-transition x-cloak>
                                <td colspan="8" class="p-0 bg-light">
                                    <form method="POST"
                                          action="{{ route('admin.pages.concours.centres.update', $c) }}"
                                          class="card-body py-4">
                                        @csrf
                                        @method('PATCH')
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label small">Code <span class="text-danger">*</span></label>
                                                <input type="text" name="code" class="form-control" required maxlength="30" value="{{ $c->code }}">
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label small">Nom <span class="text-danger">*</span></label>
                                                <input type="text" name="nom" class="form-control" required maxlength="100" value="{{ $c->nom }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">Ville</label>
                                                <input type="text" name="ville" class="form-control" maxlength="100" value="{{ $c->ville }}">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label small">Province</label>
                                                <select name="province_id" class="form-select">
                                                    <option value="">— Aucune —</option>
                                                    @foreach($provinces as $p)
                                                        <option value="{{ $p->id }}" {{ $c->province_id === $p->id ? 'selected' : '' }}>{{ $p->nom }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Capacité</label>
                                                <input type="number" name="capacite_par_defaut" class="form-control" min="1" max="100000" value="{{ $c->capacite_par_defaut }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Ordre</label>
                                                <input type="number" name="display_order" class="form-control" min="0" max="999" value="{{ $c->display_order }}">
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="active" value="1" id="edit-active-{{ $c->id }}" {{ $c->active ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="edit-active-{{ $c->id }}">Actif</label>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label small">Adresse</label>
                                                <textarea name="adresse" class="form-control" rows="2" maxlength="500">{{ $c->adresse }}</textarea>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-3">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-{{ $c->active ? 'danger' : 'success' }}"
                                                    onclick="document.getElementById('toggle-form-{{ $c->id }}').submit();">
                                                <i class="fas {{ $c->active ? 'fa-power-off' : 'fa-bolt' }} me-2"></i>
                                                {{ $c->active ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-secondary" @click="editingId = null">Annuler</button>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-save me-2"></i>Enregistrer
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    {{-- Inline form for the toggle button above. Outside the
                                         edit form so submitting it doesn't try to also send
                                         the edit payload. --}}
                                    <form id="toggle-form-{{ $c->id }}" method="POST"
                                          action="{{ route('admin.pages.concours.centres.toggle', $c) }}"
                                          class="d-none">
                                        @csrf
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="{{ $canEdit ? 8 : 7 }}" class="text-center text-muted py-4">Aucun centre enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
