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
            <div>
                <strong>Session archivée.</strong>
                Le planning de
                <em>{{ $session->libelle }}</em>
                est consultable mais ne peut plus être modifié.
            </div>
        </div>
    @endif

    {{-- Centre selector + meta --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-1 fw-semibold">Centre d'examen</label>
                    <select class="form-select form-select-lg" onchange="window.location='?centre=' + this.value">
                        @foreach($centres as $c)
                            <option value="{{ $c->id }}" @selected($selectedCentre && $selectedCentre->id === $c->id)>
                                {{ $c->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7 text-md-end">
                    <small class="text-muted d-block">
                        Session active : <strong>{{ $session->libelle }}</strong>
                        @if($session->date_concours) · épreuve du {{ $session->date_concours->format('d/m/Y') }} @endif
                    </small>

                    @if($canEdit && $otherCentres->isNotEmpty())
                        <div class="dropdown mt-2 d-inline-block">
                            <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-copy me-2"></i>Hériter d'un autre centre
                            </button>
                            <div class="dropdown-menu shadow-sm">
                                <div class="px-3 py-2 small text-muted">
                                    Copie les dates &amp; horaires d'un centre existant vers ce centre.
                                    Les salles seront à renseigner localement.
                                </div>
                                @foreach($otherCentres as $oc)
                                    <form method="POST" action="{{ route('admin.pages.concours.planning.inherit') }}"
                                          class="px-2"
                                          onsubmit="return confirm('Importer le planning de {{ $oc->nom }} vers {{ $selectedCentre->nom }} ? Les créneaux existants seront écrasés.');">
                                        @csrf
                                        <input type="hidden" name="source_session_centre_id" value="{{ $oc->pivot->id }}">
                                        <input type="hidden" name="target_session_centre_id" value="{{ $selectedCentre->pivot->id }}">
                                        <button class="dropdown-item rounded">
                                            <i class="fas fa-arrow-right me-2 text-muted"></i>{{ $oc->nom }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Slots editor --}}
    <div x-data="{
        editing: null,
        startEdit(epreuveId, row) {
            this.editing = {
                epreuve_id: epreuveId,
                concours_session_centre_id: '{{ $selectedCentre->pivot->id }}',
                date_epreuve: row?.date_epreuve ?? '',
                heure_debut:  row?.heure_debut  ?? '',
                heure_fin:    row?.heure_fin    ?? '',
                salle_id:     row?.salle_id     ?? '',
                consigne:     row?.consigne     ?? '',
            };
        },
        close() { this.editing = null; },
    }">

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Créneaux — {{ $selectedCentre->nom }}
                </h2>
                <small class="text-muted">{{ $rows->count() }} épreuve(s) à planifier</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Épreuve</th>
                            <th>Portée</th>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Salle</th>
                            <th>Consigne</th>
                            @if($canEdit)<th class="text-end"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php $p = $row['planning']; @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['epreuve']->libelle }}</div>
                                    <small class="text-muted">
                                        <code>{{ $row['epreuve']->code }}</code>
                                        · {{ $row['epreuve']->typeEpreuve?->libelle }}
                                        · coef {{ $row['epreuve']->coefficient }}
                                        · {{ $row['epreuve']->duree_minutes }} min
                                    </small>
                                </td>
                                <td>
                                    @if($row['epreuve']->scope_type === 'cycle')
                                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ $row['scope'] }}</span>
                                    @else
                                        <span class="badge bg-info-subtle text-info-emphasis">{{ $row['scope'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($p?->date_epreuve)
                                        <span class="fw-semibold">{{ $p->date_epreuve->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-muted">non planifié</span>
                                    @endif
                                </td>
                                <td>
                                    @if($p)
                                        <span class="font-monospace">{{ substr($p->heure_debut, 0, 5) }}–{{ substr($p->heure_fin, 0, 5) }}</span>
                                    @else — @endif
                                </td>
                                <td>
                                    @if($p?->salle_id)
                                        @php $s = $salles->firstWhere('id', $p->salle_id); @endphp
                                        {{ $s?->nom ?? '—' }}
                                        @if($s?->batiment)<small class="text-muted">· {{ $s->batiment }}</small>@endif
                                    @else
                                        <span class="text-muted">à définir</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ Str::limit($p?->consigne ?? '—', 60) }}</td>
                                @if($canEdit)
                                <td class="text-end">
                                    <button @click="startEdit('{{ $row['epreuve']->id }}', @js($p ? [
                                        'date_epreuve' => optional($p->date_epreuve)->format('Y-m-d'),
                                        'heure_debut'  => substr($p->heure_debut, 0, 5),
                                        'heure_fin'    => substr($p->heure_fin, 0, 5),
                                        'salle_id'     => $p->salle_id,
                                        'consigne'     => $p->consigne,
                                    ] : null))"
                                            class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-pen me-1"></i>{{ $p ? 'Modifier' : 'Planifier' }}
                                    </button>
                                    @if($p)
                                        <form method="POST" action="{{ route('admin.pages.concours.planning.destroy', $p) }}"
                                              class="d-inline-block"
                                              onsubmit="return confirm('Supprimer ce créneau ?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Aucune épreuve à planifier — créez d'abord des épreuves.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal — slot editor --}}
        <div :class="{ 'd-block': editing !== null }" x-cloak
             class="modal" tabindex="-1" style="background: rgba(15,23,42,.55);"
             @keydown.escape.window="close()">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('admin.pages.concours.planning.store') }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="epreuve_id" :value="editing?.epreuve_id">
                    <input type="hidden" name="concours_session_centre_id" :value="editing?.concours_session_centre_id">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="far fa-calendar-plus text-primary me-2"></i>
                            Planifier l'épreuve
                        </h5>
                        <button type="button" class="btn-close" @click="close()"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Date <span class="text-danger">*</span></label>
                                <input type="date" name="date_epreuve" x-model="editing.date_epreuve"
                                       class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Début <span class="text-danger">*</span></label>
                                <input type="time" name="heure_debut" x-model="editing.heure_debut"
                                       class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Fin <span class="text-danger">*</span></label>
                                <input type="time" name="heure_fin" x-model="editing.heure_fin"
                                       class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small">Salle / Amphi</label>
                                <select name="salle_id" x-model="editing.salle_id" class="form-select">
                                    <option value="">— Non assignée —</option>
                                    @foreach($salles as $s)
                                        <option value="{{ $s->id }}">
                                            {{ $s->nom }} @if($s->batiment) ({{ $s->batiment }}) @endif
                                            — {{ $s->capacite }} places
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small">Consigne (affichée sur la fiche du candidat)</label>
                                <textarea name="consigne" x-model="editing.consigne" rows="3" class="form-control"
                                          placeholder="Ex. : se présenter 30 min avant, calculatrice non programmable autorisée…"></textarea>
                            </div>
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            <i class="fas fa-circle-info me-1"></i>
                            Conflit de salle&nbsp;? Le système vous préviendra ; deux épreuves en parallèle dans la même salle sont possibles techniquement, mais déconseillées.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" @click="close()">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Enregistrer le créneau
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endif
@endsection
