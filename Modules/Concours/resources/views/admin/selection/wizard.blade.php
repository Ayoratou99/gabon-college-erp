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
                    <strong>Étape 2</strong> — Décochez les candidats à exclure ou changez leur orientation.
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
                        <span x-text="proposal[sectionId].candidats.length"></span> / <span x-text="proposal[sectionId].section.places_par_session"></span> places
                    </small>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:3rem"></th>
                            <th>Matricule</th>
                            <th>Candidat</th>
                            <th class="text-end">Moyenne</th>
                            <th class="text-end">Rang</th>
                            <th style="width: 14rem">Orientation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="c in proposal[sectionId].candidats" :key="c.id">
                            <tr :class="{ 'opacity-50': !chosen[c.id]?.kept }">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input"
                                           :checked="chosen[c.id]?.kept"
                                           @change="toggleKept(c.id)">
                                </td>
                                <td><code x-text="c.matricule_public"></code></td>
                                <td><span x-text="c.nom + ' ' + c.prenom"></span></td>
                                <td class="text-end"><strong x-text="c.moyenne"></strong></td>
                                <td class="text-end" x-text="c.rang"></td>
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
