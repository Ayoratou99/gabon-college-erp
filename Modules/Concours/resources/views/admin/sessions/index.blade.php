@extends('layouts.admin')

@section('title', 'Sessions du concours')
@section('page-title', 'Sessions du concours')

@section('content')
<div x-data="{ showCreate: false }">

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
                                    <form method="POST" action="{{ route('admin.pages.concours.sessions.activate', $s) }}"
                                          class="d-inline-block ms-1"
                                          onsubmit="return confirm('Activer cette session ? Toute autre session active sera désactivée.');">
                                        @csrf
                                        <button class="btn btn-sm btn-success">
                                            <i class="fas fa-bolt me-1"></i>Sélectionner
                                        </button>
                                    </form>
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
</div>
@endsection
