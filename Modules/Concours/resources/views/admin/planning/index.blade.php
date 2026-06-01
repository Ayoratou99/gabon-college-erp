@extends('layouts.admin')

@section('title', 'Emploi du temps des épreuves')
@section('page-title', 'Emploi du temps des épreuves')

@section('content')

@if(! $session)
    <div class="alert alert-warning">
        Aucune session de concours active. Activez une session depuis
        <a href="{{ route('admin.pages.concours.sessions.index') }}">Sessions</a> pour planifier les épreuves.
    </div>
@elseif($centres->isEmpty())
    <div class="alert alert-info">
        La session active <strong>{{ $session->libelle }}</strong> n'a pas encore de centre rattaché.
        Ajoutez d'abord des centres à la session.
    </div>
@else

    @if(! ($sessionEditable ?? true))
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-lock fa-lg"></i>
            <div><strong>Session archivée.</strong> Le planning de <em>{{ $session->libelle }}</em> est consultable mais non modifiable.</div>
        </div>
    @endif

    {{-- Centre selector + inherit --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-1 fw-semibold">Centre d'examen</label>
                    <select class="form-select form-select-lg" onchange="window.location='?centre=' + this.value">
                        @foreach($centres as $c)
                            <option value="{{ $c->id }}" @selected($selectedCentre && $selectedCentre->id === $c->id)>{{ $c->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7 text-md-end">
                    <small class="text-muted d-block">
                        Session : <strong>{{ $session->libelle }}</strong>
                        @if($session->date_concours) · épreuves vers le {{ $session->date_concours->format('d/m/Y') }} @endif
                    </small>
                    @if($canEdit && $otherCentres->isNotEmpty())
                        <div class="dropdown mt-2 d-inline-block">
                            <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-copy me-2"></i>Hériter d'un autre centre
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                <div class="px-3 py-2 small text-muted">Copie l'emploi du temps (épreuves + pauses) d'un autre centre vers celui-ci.</div>
                                @foreach($otherCentres as $oc)
                                    <form method="POST" action="{{ route('admin.pages.concours.planning.inherit') }}" class="px-2"
                                          onsubmit="return confirm('Importer l\'emploi du temps de {{ $oc->nom }} vers {{ $selectedCentre->nom }} ?');">
                                        @csrf
                                        <input type="hidden" name="source_session_centre_id" value="{{ $oc->pivot->id }}">
                                        <input type="hidden" name="target_session_centre_id" value="{{ $selectedCentre->pivot->id }}">
                                        <button class="dropdown-item rounded"><i class="fas fa-arrow-right me-2 text-muted"></i>{{ $oc->nom }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Per-section progress: which concours sections still lack a complete emploi du temps at this centre --}}
    @if(! empty($progress))
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small fw-semibold text-muted me-1"><i class="fas fa-list-check me-1"></i>Avancement par section&nbsp;:</span>
                    @foreach($progress as $p)
                        <span class="badge rounded-pill d-inline-flex align-items-center gap-1
                            {{ $p['complete'] ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}"
                            title="{{ $p['nom'] }}">
                            <i class="fas {{ $p['complete'] ? 'fa-circle-check' : 'fa-hourglass-half' }}"></i>
                            <code class="text-reset">{{ $p['code'] }}</code>
                            {{ $p['planned'] }}/{{ $p['total'] }}
                        </span>
                    @endforeach
                </div>
                @php $incomplete = collect($progress)->where('complete', false)->count(); @endphp
                <div class="small mt-2 {{ $incomplete ? 'text-warning-emphasis' : 'text-success-emphasis' }}">
                    @if($incomplete)
                        <i class="fas fa-triangle-exclamation me-1"></i>{{ $incomplete }} section(s) avec des épreuves non encore planifiées à <strong>{{ $selectedCentre->nom }}</strong>.
                    @else
                        <i class="fas fa-circle-check me-1"></i>Toutes les épreuves des sections concernées sont planifiées à <strong>{{ $selectedCentre->nom }}</strong>.
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div x-data="planningBoard()">
        <div class="row g-3">
            {{-- The board --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i>Emploi du temps — {{ $selectedCentre->nom }}</h2>
                        <small class="text-muted">{{ $slots->count() }} créneau(x)</small>
                    </div>
                    <div class="card-body">
                        @if($canEdit)
                            <p class="small text-muted"><i class="fas fa-grip-vertical me-1"></i>Glissez-déposez les lignes pour les réordonner — c'est cet ordre que verra le candidat.</p>
                        @endif

                        <div id="planning-board"
                             data-reorder-url="{{ route('admin.pages.concours.planning.reorder') }}"
                             data-csrf="{{ csrf_token() }}"
                             data-can-edit="{{ $canEdit ? '1' : '0' }}">
                            @forelse($slots as $slot)
                                @php
                                    $isBreak = $slot->epreuve_id === null;
                                    $editPayload = [
                                        'updateUrl'     => route('admin.pages.concours.planning.update', $slot),
                                        'isBreak'       => $isBreak,
                                        'date_epreuve'  => optional($slot->date_epreuve)->format('Y-m-d'),
                                        'heure_debut'   => substr((string) $slot->heure_debut, 0, 5),
                                        'heure_fin'     => substr((string) $slot->heure_fin, 0, 5),
                                        'consigne'      => $slot->consigne,
                                        'libelle_libre' => $slot->libelle_libre,
                                        'titre'         => $isBreak ? ($slot->libelle_libre ?: 'Ligne libre') : ($slot->epreuve?->libelle ?? '—'),
                                    ];
                                @endphp
                                <div class="planning-card {{ $isBreak ? 'planning-card--break' : '' }}" data-id="{{ $slot->id }}">
                                    @if($canEdit)<span class="planning-handle"><i class="fas fa-grip-vertical"></i></span>@endif
                                    <div class="flex-grow-1">
                                        @if($isBreak)
                                            <span class="badge bg-warning-subtle text-warning-emphasis mb-1"><i class="fas fa-mug-hot me-1"></i>Pause / ligne libre</span>
                                            <div class="fw-semibold">{{ $slot->libelle_libre ?: 'Ligne libre' }}</div>
                                        @else
                                            <div class="fw-semibold">{{ $slot->epreuve?->libelle }}
                                                <code class="ms-1">{{ $slot->epreuve?->code }}</code>
                                            </div>
                                            <div class="small text-muted mb-1">
                                                {{ $slot->epreuve?->typeEpreuve?->libelle }} · coef {{ $slot->epreuve?->coefficient }} · {{ $slot->epreuve?->duree_minutes }} min
                                            </div>
                                            <div class="mb-1">
                                                @foreach($slot->epreuve?->sections ?? [] as $sec)
                                                    <span class="badge bg-light text-dark border me-1">{{ $sec->code }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        <div class="small text-muted">
                                            <i class="far fa-calendar me-1"></i>{{ optional($slot->date_epreuve)->format('d/m/Y') ?? '—' }}
                                            · <span class="font-monospace">{{ substr((string) $slot->heure_debut, 0, 5) }}–{{ substr((string) $slot->heure_fin, 0, 5) }}</span>
                                        </div>
                                        @if($slot->consigne)<div class="small text-muted mt-1"><i class="fas fa-circle-info me-1"></i>{{ \Illuminate\Support\Str::limit($slot->consigne, 90) }}</div>@endif
                                    </div>
                                    @if($canEdit)
                                        <div class="d-flex flex-column gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" @click="openEdit(@js($editPayload))"><i class="fas fa-pen"></i></button>
                                            <form method="POST" action="{{ route('admin.pages.concours.planning.destroy', $slot) }}" onsubmit="return confirm('Supprimer ce créneau ?');">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">Aucun créneau. Ajoutez une épreuve ou une pause →</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Add panel --}}
            @if($canEdit)
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="far fa-calendar-plus text-success me-2"></i>Ajouter une épreuve</h3></div>
                    <div class="card-body">
                        @if($unplanned->isEmpty())
                            <p class="small text-muted mb-0">Toutes les épreuves sont déjà placées à ce centre.</p>
                        @else
                            <form method="POST" action="{{ route('admin.pages.concours.planning.store') }}">
                                @csrf
                                <input type="hidden" name="concours_session_centre_id" value="{{ $sessionCentreId }}">
                                <div class="mb-2">
                                    <label class="form-label small">Épreuve <span class="text-danger">*</span></label>
                                    <select name="epreuve_id" class="form-select form-select-sm" required>
                                        <option value="">—</option>
                                        @foreach($unplanned as $e)
                                            <option value="{{ $e->id }}">{{ $e->code }} — {{ $e->libelle }} [{{ $e->sections->pluck('code')->implode(', ') }}]</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12"><label class="form-label small">Date <span class="text-danger">*</span></label><input type="date" name="date_epreuve" class="form-control form-control-sm" required></div>
                                    <div class="col-6"><label class="form-label small">Début <span class="text-danger">*</span></label><input type="time" name="heure_debut" class="form-control form-control-sm" required></div>
                                    <div class="col-6"><label class="form-label small">Fin <span class="text-danger">*</span></label><input type="time" name="heure_fin" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small">Consigne (optionnel)</label><textarea name="consigne" rows="2" class="form-control form-control-sm" placeholder="Ex : se présenter 30 min avant…"></textarea></div>
                                </div>
                                <button class="btn btn-success btn-sm w-100 mt-2"><i class="fas fa-plus me-1"></i>Ajouter l'épreuve</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="fas fa-mug-hot text-warning me-2"></i>Ajouter une pause / ligne libre</h3></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.pages.concours.planning.break') }}">
                            @csrf
                            <input type="hidden" name="concours_session_centre_id" value="{{ $sessionCentreId }}">
                            <div class="mb-2">
                                <label class="form-label small">Intitulé <span class="text-danger">*</span></label>
                                <input type="text" name="libelle_libre" class="form-control form-control-sm" required placeholder="Pause déjeuner, Accueil des candidats…">
                            </div>
                            <div class="row g-2">
                                <div class="col-12"><label class="form-label small">Date <span class="text-danger">*</span></label><input type="date" name="date_epreuve" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small">Début <span class="text-danger">*</span></label><input type="time" name="heure_debut" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small">Fin <span class="text-danger">*</span></label><input type="time" name="heure_fin" class="form-control form-control-sm" required></div>
                            </div>
                            <button class="btn btn-warning btn-sm w-100 mt-2 text-dark"><i class="fas fa-plus me-1"></i>Ajouter la ligne</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="fas fa-note-sticky text-info me-2"></i>Note importante (bas du PDF)</h3></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.pages.concours.planning.note') }}">
                            @csrf
                            <textarea name="planning_note" rows="3" class="form-control form-control-sm"
                                      placeholder="Ex : se munir d'une pièce d'identité et de la fiche d'inscription…">{{ $planningNote }}</textarea>
                            <div class="form-text small">Affichée en bas de l'emploi du temps de tous les candidats (tous centres).</div>
                            <button class="btn btn-info btn-sm w-100 mt-2 text-white"><i class="fas fa-save me-1"></i>Enregistrer la note</button>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Edit modal (shared) --}}
        <div class="modal" tabindex="-1" x-cloak :class="{ 'd-block': editing !== null }"
             style="background: rgba(15,23,42,.55);" @keydown.escape.window="closeEdit()">
            <div class="modal-dialog modal-dialog-centered" @click.stop>
                <form method="POST" :action="editing?.updateUrl" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-pen text-primary me-2"></i>Modifier — <span x-text="editing?.titre"></span></h5>
                        <button type="button" class="btn-close" @click="closeEdit()"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <template x-if="editing?.isBreak">
                                <div class="col-12">
                                    <label class="form-label small">Intitulé <span class="text-danger">*</span></label>
                                    <input type="text" name="libelle_libre" x-model="editing.libelle_libre" class="form-control" required>
                                </div>
                            </template>
                            <div class="col-md-6"><label class="form-label small">Date <span class="text-danger">*</span></label><input type="date" name="date_epreuve" x-model="editing.date_epreuve" class="form-control" required></div>
                            <div class="col-md-3"><label class="form-label small">Début <span class="text-danger">*</span></label><input type="time" name="heure_debut" x-model="editing.heure_debut" class="form-control" required></div>
                            <div class="col-md-3"><label class="form-label small">Fin <span class="text-danger">*</span></label><input type="time" name="heure_fin" x-model="editing.heure_fin" class="form-control" required></div>
                            <template x-if="!editing?.isBreak">
                                <div class="col-12">
                                    <label class="form-label small">Consigne (affichée au candidat)</label>
                                    <textarea name="consigne" x-model="editing.consigne" rows="3" class="form-control"></textarea>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" @click="closeEdit()">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endif
@endsection

@push('head')
<style>
    #planning-board { display: flex; flex-direction: column; gap: .5rem; }
    .planning-card {
        display: flex; align-items: flex-start; gap: .75rem;
        border: 1px solid #e2e8f0; border-left: 4px solid var(--cuk-primary, #1d4ed8);
        border-radius: 10px; padding: .75rem .9rem; background: #fff;
        transition: box-shadow .15s, transform .15s;
    }
    .planning-card:hover { box-shadow: 0 4px 14px rgba(15,23,42,.08); }
    .planning-card--break { border-left-color: var(--cuk-warning, #f59e0b); background: #fffdf6; }
    .planning-handle { cursor: grab; color: #94a3b8; padding-top: .15rem; }
    .planning-handle:active { cursor: grabbing; }
    .planning-ghost { opacity: .45; }
    .planning-chosen { box-shadow: 0 8px 24px rgba(15,23,42,.16); }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.3/Sortable.min.js"></script>
<script>
    function planningBoard() {
        return {
            editing: null,
            openEdit(slot) { this.editing = Object.assign({ consigne: '', libelle_libre: '' }, slot); },
            closeEdit() { this.editing = null; },
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        const board = document.getElementById('planning-board');
        if (!board || board.dataset.canEdit !== '1' || typeof Sortable === 'undefined') return;

        Sortable.create(board, {
            handle: '.planning-handle',
            animation: 150,
            ghostClass: 'planning-ghost',
            chosenClass: 'planning-chosen',
            onEnd: function () {
                const order = Array.from(board.querySelectorAll('.planning-card')).map(el => el.dataset.id);
                fetch(board.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': board.dataset.csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ order }),
                }).catch(() => { /* non-blocking — order re-syncs on next load */ });
            },
        });
    });
</script>
@endpush
