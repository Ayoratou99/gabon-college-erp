@extends('layouts.admin')

@section('title', 'Sélection des admis')
@section('page-title', 'Sélection des admis')

@section('content')
@if($publication)
    <div class="alert alert-info">
        <strong>Résultats déjà publiés</strong> pour cette session le
        {{ $publication->published_at->format('d/m/Y à H:i') }} —
        {{ $publication->total_admis }} admis sur {{ $publication->total_candidats }}.
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0"><i class="fas fa-trophy text-success me-2"></i>Gagnants du concours</h2>
        </div>
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div class="me-auto small text-muted">
                Fichier Excel des admis, <strong>un onglet par section</strong> d'orientation,
                classés par rang (rang général + rang dans le centre).
            </div>
            <a href="{{ route('admin.pages.concours.selection.gagnants.export') }}" class="btn btn-success">
                <i class="fas fa-file-excel me-2"></i>Exporter les gagnants (Excel)
            </a>
        </div>
    </div>

    {{-- PV final — obligatoire avant la clôture de la session. --}}
    <div class="card mb-3 {{ $publication->hasPv() ? '' : 'border-warning' }}">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0"><i class="far fa-file-pdf text-danger me-2"></i>Procès-verbal final</h2>
        </div>
        <div class="card-body">
            @if($publication->hasPv())
                <p class="mb-2">
                    <i class="fas fa-circle-check text-success me-1"></i>
                    PV déposé — la session peut être clôturée.
                </p>
                @if($session?->code)
                    <a href="{{ route('concours.public.results.download', ['sessionCode' => $session->code]) }}"
                       target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                        <i class="far fa-eye me-1"></i>Consulter le PV
                    </a>
                @endif
            @else
                <p class="text-warning-emphasis mb-3">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    Aucun PV n'est encore joint à cette publication.
                    <strong>La session ne pourra pas être clôturée</strong> tant que le PV final n'est pas déposé.
                </p>
            @endif

            <form method="POST" action="{{ route('admin.pages.concours.selection.pv.upload') }}"
                  enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end mt-2">
                @csrf
                <div>
                    <label class="form-label small mb-1" for="pv">{{ $publication->hasPv() ? 'Remplacer le PV' : 'Déposer le PV final' }} (PDF)</label>
                    <input type="file" name="pv" id="pv" accept="application/pdf" required class="form-control form-control-sm">
                </div>
                <button class="btn btn-sm btn-primary"><i class="fas fa-upload me-1"></i>Enregistrer</button>
            </form>
            @error('pv')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        </div>
    </div>
@elseif(!$session)
    <div class="alert alert-warning">Aucune session de concours active.</div>
@else
@php
    $wizardData = [
        'sessionId' => $session->id,
        'sections'  => $sections,
    ];
@endphp
<div x-data='selectionWizard(@json($wizardData))'>

    {{-- Stepper --}}
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-around text-center">
            <div :class="step >= 1 ? 'text-primary fw-bold' : 'text-muted'">
                <i class="fas fa-calculator d-block fs-3 mb-1"></i>
                1. Calculer les moyennes
            </div>
            <div :class="step >= 2 ? 'text-primary fw-bold' : 'text-muted'">
                <i class="fas fa-list-check d-block fs-3 mb-1"></i>
                2. Réviser la sélection
            </div>
            <div :class="step >= 3 ? 'text-success fw-bold' : 'text-muted'">
                <i class="fas fa-trophy d-block fs-3 mb-1"></i>
                3. Publier
            </div>
        </div>
    </div>

    {{-- Step 1 --}}
    <div x-show="step === 1" class="card">
        <div class="card-body text-center py-5">
            <h2 class="h4 mb-3">Étape 1 — Calcul des moyennes</h2>
            <p class="text-muted">
                Cette opération recalcule la moyenne pondérée + le rang
                de chaque candidat ayant payé pour la session active.
            </p>
            <button @click="recompute()" :disabled="loading" class="btn btn-primary btn-lg">
                <span x-show="!loading"><i class="fas fa-play me-2"></i>Lancer le calcul</span>
                <span x-show="loading"><i class="fas fa-spinner fa-spin me-2"></i>Calcul en cours…</span>
            </button>
            <p x-show="message" x-text="message" class="text-danger mt-3 mb-0"></p>
        </div>
    </div>

    {{-- Step 2 --}}
    <div x-show="step === 2">
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <strong>Étape 2</strong> — Cochez les gagnants du concours.
                    <div class="small text-muted">
                        Les candidats dans le quota sont pré-cochés. Le concours se joue au
                        <strong>classement</strong>&nbsp;: vous pouvez aussi désigner un candidat
                        situé <em>sous la barre</em> (lignes grisées «&nbsp;hors quota&nbsp;»), ou en retirer un.
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small">
                        <span x-text="totalChosen"></span> admis sélectionnés
                    </span>
                    <button @click="step = 3" :disabled="totalChosen === 0" class="btn btn-primary">
                        Passer à la publication <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>

        <template x-for="sectionId in Object.keys(proposal)" :key="sectionId">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between">
                    <h2 class="h5 mb-0">
                        <span x-text="proposal[sectionId].section.code"></span>
                        — <span x-text="proposal[sectionId].section.nom"></span>
                    </h2>
                    <small class="text-muted">
                        <span class="fw-bold" x-text="chosenIn(sectionId)"></span> retenu(s)
                        / <span x-text="proposal[sectionId].section.places_par_session"></span> places
                        <span class="ms-2">(<span x-text="proposal[sectionId].candidats.length"></span> classés)</span>
                    </small>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:3rem"></th>
                            <th>Matricule</th>
                            <th>Candidat</th>
                            <th>Centre</th>
                            <th class="text-end">Moyenne</th>
                            <th class="text-end">Rang</th>
                            <th class="text-end">Rang centre</th>
                            <th style="width: 14rem">Orientation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="c in proposal[sectionId].candidats" :key="c.id">
                            <tr :class="{
                                    'opacity-50': !chosen[c.id]?.kept,
                                    'table-warning': !c.suggested && chosen[c.id]?.kept,
                                }">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input"
                                           :checked="chosen[c.id]?.kept"
                                           @change="toggleKept(c.id)">
                                </td>
                                <td><code x-text="c.matricule_public"></code></td>
                                <td>
                                    <span x-text="c.nom + ' ' + c.prenom"></span>
                                    <template x-if="!c.suggested">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">hors quota</span>
                                    </template>
                                </td>
                                <td class="small text-muted" x-text="c.centre_nom ?? '—'"></td>
                                <td class="text-end"><strong x-text="c.moyenne"></strong></td>
                                <td class="text-end" x-text="c.rang"></td>
                                <td class="text-end text-muted" x-text="c.rang_centre ?? '—'"></td>
                                <td>
                                    <select class="form-select form-select-sm" x-model="chosen[c.id].orientationSectionId">
                                        <template x-for="s in sections" :key="s.id">
                                            <option :value="s.id" x-text="s.code + ' — ' + s.nom"></option>
                                        </template>
                                    </select>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    {{-- Step 3 --}}
    <div x-show="step === 3" class="card">
        <div class="card-body">
            <h2 class="h4 mb-3">Étape 3 — Publication</h2>
            <p>
                Vous êtes sur le point de publier les résultats. <strong x-text="totalChosen"></strong>
                candidat(s) seront marqués <em>admis</em>, recevront un compte utilisateur
                et l'orientation choisie. <strong>Cette action est irréversible.</strong>
            </p>

            <div class="mb-3">
                <label class="form-label">Communiqué (optionnel)</label>
                <textarea x-model="communique" rows="3" class="form-control"
                          placeholder="Texte affiché sur la page publique des résultats…"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Procès-verbal (PV) — PDF (optionnel)</label>
                <input type="file" accept="application/pdf" class="form-control"
                       @change="setPv($event)" :disabled="published">
                <div class="form-text small">
                    <i class="fas fa-file-pdf me-1"></i>Le PV officiel signé, joint à la publication des résultats
                    (vous pourrez aussi l'ajouter plus tard).
                </div>
            </div>

            <div class="d-flex gap-2">
                <button @click="step = 2" class="btn btn-outline-secondary" :disabled="published">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </button>
                <button @click="confirm()" :disabled="loading || published" class="btn btn-success ms-auto">
                    <span x-show="!loading"><i class="fas fa-trophy me-2"></i>Publier les résultats</span>
                    <span x-show="loading"><i class="fas fa-spinner fa-spin me-2"></i>Publication…</span>
                </button>
            </div>

            <p x-show="message" x-text="message" class="mt-3 mb-0"
               :class="published ? 'text-success fw-semibold' : 'text-danger'"></p>
        </div>
    </div>
</div>
@endif
@endsection
