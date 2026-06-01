@extends('layouts.admin')

@section('title', 'Sessions du concours')
@section('page-title', 'Sessions du concours')

@section('content')
<div x-data="{
        showCreate: false,
        confirmOpen: false, confirmAction: '', confirmLabel: '',
        editOpen: false,
        editData: { updateUrl: '', annee_academique_id: '', code: '', libelle: '', date_ouverture_inscriptions: '', date_fermeture_inscriptions: '', date_concours: '', frais_inscription_override: '' },
        openEdit(data) { this.editData = Object.assign({}, this.editData, data); this.editOpen = true; }
     }">

    @if($canEdit)
        <div class="d-flex justify-content-end mb-3">
            <button @click="showCreate = !showCreate" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouvelle session
            </button>
        </div>

        <div class="card mb-4" x-show="showCreate" x-transition x-cloak>
            <div class="card-header bg-white">
                <h2 class="h6 mb-0"><i class="fas fa-circle-plus text-primary me-2"></i>Créer une session</h2>
            </div>
            <form method="POST" action="{{ route('admin.pages.concours.sessions.store') }}" class="card-body">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Année académique <span class="text-danger">*</span></label>
                        <select name="annee_academique_id" class="form-select" required>
                            <option value="">— sélectionner —</option>
                            @foreach($annees as $a)
                                <option value="{{ $a->id }}">{{ $a->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required placeholder="CONCOURS-2026-2027">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Libellé <span class="text-danger">*</span></label>
                        <input type="text" name="libelle" class="form-control" required placeholder="Concours d'entrée — session 2026-2027">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Ouverture des inscriptions <span class="text-danger">*</span></label>
                        <input type="date" name="date_ouverture_inscriptions" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Fermeture des inscriptions <span class="text-danger">*</span></label>
                        <input type="date" name="date_fermeture_inscriptions" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Date du concours <span class="text-danger">*</span></label>
                        <input type="date" name="date_concours" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Frais d'inscription (FCFA, optionnel)</label>
                        <input type="number" name="frais_inscription_override" class="form-control" min="0" placeholder="10 300">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activate_now" value="1" id="act-now">
                            <label class="form-check-label" for="act-now">Activer immédiatement</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" @click="showCreate = false">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0"><i class="fas fa-calendar-check text-primary me-2"></i>Toutes les sessions</h2>
            <small class="text-muted">{{ $sessions->count() }} session(s)</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Année</th>
                        <th>Date concours</th>
                        <th class="text-end">Candidats</th>
                        <th>Statut</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $s)
                        @php
                            $badge      = $s->lifecycleBadge();
                            $isArchived = $badge['key'] === 'archived';
                            // Only highlight a row in green when it's BOTH the
                            // selected session AND still in-flight. A legacy
                            // session that's still flagged est_active=true (so
                            // the dashboard has data to show) shouldn't masquerade
                            // as live.
                            $rowCls = ($s->est_active && ! $isArchived) ? 'table-success' : '';
                            // Prefilled payload for the shared « Modifier » modal.
                            // Dates as Y-m-d so <input type="date"> binds cleanly.
                            $editPayload = [
                                'updateUrl'                   => route('admin.pages.concours.sessions.update', $s),
                                'annee_academique_id'         => $s->annee_academique_id,
                                'code'                        => $s->code,
                                'libelle'                     => $s->libelle,
                                'date_ouverture_inscriptions' => optional($s->date_ouverture_inscriptions)->format('Y-m-d'),
                                'date_fermeture_inscriptions' => optional($s->date_fermeture_inscriptions)->format('Y-m-d'),
                                'date_concours'               => optional($s->date_concours)->format('Y-m-d'),
                                'frais_inscription_override'  => $s->frais_inscription_override,
                            ];
                        @endphp
                        <tr class="{{ $rowCls }}">
                            <td><code>{{ $s->code }}</code></td>
                            <td class="fw-semibold">{{ $s->libelle }}</td>
                            <td>{{ $s->anneeAcademique?->code ?? '—' }}</td>
                            <td>{{ optional($s->date_concours)->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format($s->candidats_count, 0, ',', ' ') }}</td>
                            <td>
                                <span class="badge bg-{{ $badge['css'] }}">
                                    <i class="fas {{ $badge['icon'] }} me-1"></i>{{ $badge['label'] }}
                                </span>
                                @if($s->est_active)
                                    <span class="badge bg-primary-subtle text-primary-emphasis ms-1"
                                          title="Session par défaut pour les tableaux de bord et exports">
                                        <i class="fas fa-thumbtack me-1"></i>Sélectionnée
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('dashboard', ['session' => $s->code]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-chart-line me-1"></i>Tableau de bord
                                </a>
                                @if($canEdit && ! $s->est_active)
                                    <button type="button" class="btn btn-sm btn-success ms-1"
                                            @click="confirmAction='{{ route('admin.pages.concours.sessions.activate', $s) }}'; confirmLabel=@js($s->libelle); confirmOpen = true">
                                        <i class="fas fa-bolt me-1"></i>Sélectionner
                                    </button>
                                @endif
                                @if($canEdit && ! $isArchived)
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                            @click="openEdit(@js($editPayload))">
                                        <i class="fas fa-pen me-1"></i>Modifier
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune session.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Confirm "activate this session" modal — replaces the native confirm()
         dialog. Driven by the page-level Alpine state set by each row's
         « Sélectionner » button (confirmAction / confirmLabel). --}}
    {{-- Uses Bootstrap's .modal (display:none by default) toggled via
         :class="{ 'd-block': confirmOpen }" — NOT x-show, because Bootstrap's
         .d-flex carries `!important` and would override Alpine's inline
         display:none, leaving the modal stuck open + unclosable. --}}
    <div class="modal" tabindex="-1" x-cloak
         :class="{ 'd-block': confirmOpen }"
         style="background: rgba(15,23,42,.55);"
         @click="confirmOpen = false"
         @keydown.escape.window="confirmOpen = false">
        <div class="modal-dialog modal-dialog-centered" @click.stop>
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:48px;height:48px;">
                            <i class="fas fa-eye fa-lg"></i>
                        </div>
                        <div>
                            <h3 class="h5 mb-1">Afficher cette session dans le back-office ?</h3>
                            <p class="text-muted mb-0">
                                Les tableaux de bord, rapports et listes basculeront sur
                                <strong x-text="confirmLabel"></strong>. Cela n'affecte pas les
                                inscriptions publiques (le public voit toujours la dernière session).
                            </p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary"
                                @click="confirmOpen = false">Annuler</button>
                        <form method="POST" :action="confirmAction" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye me-1"></i>Afficher cette session
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit a non-archived session. Shared single instance; each row's
         « Modifier » button calls openEdit() with the prefilled payload.
         Uses .modal + :class="{ 'd-block': editOpen }" (NOT x-show) for the
         same reason as the confirm modal — Bootstrap's .d-flex !important would
         override Alpine's inline display:none and leave it stuck open. --}}
    <div class="modal" tabindex="-1" x-cloak
         :class="{ 'd-block': editOpen }"
         style="background: rgba(15,23,42,.55);"
         @click="editOpen = false"
         @keydown.escape.window="editOpen = false">
        <div class="modal-dialog modal-dialog-centered modal-lg" @click.stop>
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" :action="editData.updateUrl">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h3 class="h5 mb-0"><i class="fas fa-pen text-primary me-2"></i>Modifier la session</h3>
                        <button type="button" class="btn-close" @click="editOpen = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Année académique <span class="text-danger">*</span></label>
                                <select name="annee_academique_id" class="form-select" x-model="editData.annee_academique_id" required>
                                    <option value="">— sélectionner —</option>
                                    @foreach($annees as $a)
                                        <option value="{{ $a->id }}">{{ $a->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" x-model="editData.code" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Libellé <span class="text-danger">*</span></label>
                                <input type="text" name="libelle" class="form-control" x-model="editData.libelle" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Ouverture des inscriptions <span class="text-danger">*</span></label>
                                <input type="date" name="date_ouverture_inscriptions" class="form-control" x-model="editData.date_ouverture_inscriptions" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Fermeture des inscriptions <span class="text-danger">*</span></label>
                                <input type="date" name="date_fermeture_inscriptions" class="form-control" x-model="editData.date_fermeture_inscriptions" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Date du concours <span class="text-danger">*</span></label>
                                <input type="date" name="date_concours" class="form-control" x-model="editData.date_concours" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Frais d'inscription (FCFA, optionnel)</label>
                                <input type="number" name="frais_inscription_override" class="form-control" min="0" x-model="editData.frais_inscription_override" placeholder="10 300">
                            </div>
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
</div>
@endsection
