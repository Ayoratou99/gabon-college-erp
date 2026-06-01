@extends('concours::pdf._base')

@section('title', 'Emploi du temps des épreuves — ' . $candidat->matricule_public)
@section('doc-title', 'Emploi du temps des épreuves')
@section('matricule', $candidat->matricule_public)

@section('content')

<h2>{{ $candidat->prenom }} {{ mb_strtoupper($candidat->nom ?? '') }}</h2>
<table class="kv">
    <tr><th>Centre d'examen</th> <td>{{ $candidat->centre?->nom ?? '—' }}</td></tr>
    <tr><th>Session</th>         <td>{{ $candidat->session?->libelle ?? $candidat->session?->code ?? '—' }}</td></tr>
    <tr><th>Date du concours</th><td>{{ optional($candidat->session?->date_concours)->format('d/m/Y') ?? '—' }}</td></tr>
</table>

<h2>Planning des épreuves</h2>

@if($planning->isEmpty())
    <p class="small">Aucune épreuve n'est encore planifiée pour votre centre. Le calendrier
    définitif vous sera communiqué prochainement. Consultez régulièrement votre dossier en ligne.</p>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width:20%">Date</th>
                <th style="width:22%">Horaire</th>
                <th>Épreuve</th>
                <th style="width:22%">Type</th>
            </tr>
        </thead>
        <tbody>
            @foreach($planning as $p)
                <tr>
                    <td>{{ optional($p->date_epreuve)->format('d/m/Y') }}</td>
                    <td>{{ substr((string) $p->heure_debut, 0, 5) }}&nbsp;–&nbsp;{{ substr((string) $p->heure_fin, 0, 5) }}</td>
                    @if($p->isBreak())
                        <td><em>{{ $p->libelle_libre ?: 'Pause' }}</em></td>
                        <td>—</td>
                    @else
                        <td>{{ $p->epreuve?->libelle ?? '—' }}</td>
                        <td>{{ $p->epreuve?->typeEpreuve?->libelle ?? '—' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div style="margin-top: 24pt;">
    <p class="small"><strong>Consignes&nbsp;:</strong></p>
    <ul class="small" style="margin-top: 4pt;">
        <li>Se présenter au centre d'examen <strong>au moins 30 minutes</strong> avant le début de la première épreuve.</li>
        <li>Munissez-vous d'une <strong>pièce d'identité</strong> en cours de validité et de votre <strong>fiche d'inscription</strong>.</li>
        <li>Les téléphones portables et tout matériel électronique sont strictement interdits dans la salle.</li>
        <li>Apporter votre matériel d'écriture (stylo bleu ou noir, crayon, gomme, calculatrice non programmable si autorisée).</li>
    </ul>
</div>

@if($candidat->session?->planning_note)
<div style="margin-top: 16pt; padding: 8pt 10pt; border: 1px solid #cbd5e1; border-radius: 4pt; background: #f8fafc;">
    <p class="small" style="margin: 0;"><strong>Note importante&nbsp;:</strong></p>
    <p class="small" style="margin: 4pt 0 0; white-space: pre-line;">{{ $candidat->session->planning_note }}</p>
</div>
@endif

@endsection
