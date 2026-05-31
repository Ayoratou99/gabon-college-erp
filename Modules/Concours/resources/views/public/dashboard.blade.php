@extends('layouts.public')
@section('title', 'Mon dossier — ' . $candidat->matricule_public)

@section('content')
<section class="container py-5" style="max-width:980px">

    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $candidat->prenom }} {{ $candidat->nom }}</h1>
            <p class="text-muted mb-0">
                Matricule&nbsp;: <code>{{ $candidat->matricule_public }}</code>
                &nbsp;·&nbsp; Centre&nbsp;: {{ $candidat->centre?->nom }}
                &nbsp;·&nbsp; Premier choix&nbsp;: {{ $candidat->premierChoix?->nom }}
            </p>
        </div>
        <span class="badge bg-{{
            ['non' => 'secondary', 'oui' => 'warning', 'valid' => 'success', 'rejete' => 'danger', 'admis' => 'primary'][$candidat->statut] ?? 'secondary'
        }} fs-6">{{ strtoupper($candidat->statut) }}</span>
    </div>

    @if($candidat->statut === 'admis' && $publication)
        <div class="alert alert-primary">
            <h4 class="alert-heading"><i class="fas fa-trophy me-2"></i> Vous êtes admis(e)&nbsp;!</h4>
            <p class="mb-0">
                Orientation&nbsp;: <strong>{{ $candidat->sectionOrientation?->nom }}</strong>
                &nbsp;·&nbsp; Rang&nbsp;: <strong>{{ $candidat->rang ?? '—' }}</strong>
                &nbsp;·&nbsp; Moyenne&nbsp;: <strong>{{ $candidat->moyenne ?? '—' }}</strong>
            </p>
        </div>
    @endif

    @if(in_array($candidat->statut, ['valid', 'admis'], true))
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <i class="far fa-file-pdf fs-3 text-primary me-2"></i>
                <div class="me-auto">
                    <h3 class="h6 mb-0">Mes documents officiels</h3>
                    <p class="small text-muted mb-0">À présenter le jour de l'épreuve avec votre pièce d'identité.</p>
                </div>
                <a class="btn btn-outline-primary"
                   href="{{ route('concours.public.candidat.pdf', ['matricule' => $candidat->matricule_public, 'document' => 'fiche']) }}">
                    <i class="far fa-file-pdf me-2"></i>Fiche d'inscription
                </a>
                <a class="btn btn-outline-primary"
                   href="{{ route('concours.public.candidat.pdf', ['matricule' => $candidat->matricule_public, 'document' => 'emploi-du-temps']) }}">
                    <i class="far fa-calendar-alt me-2"></i>Emploi du temps
                </a>
            </div>
        </div>
    @endif

    @if($schedule->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Mon planning d'épreuves</h2></div>
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Épreuve</th>
                        <th>Type</th>
                        <th>Salle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedule as $p)
                        <tr>
                            <td>{{ $p->date_epreuve->format('d/m/Y') }}</td>
                            <td>{{ substr($p->heure_debut, 0, 5) }} – {{ substr($p->heure_fin, 0, 5) }}</td>
                            <td>{{ $p->epreuve->libelle }}</td>
                            <td>{{ $p->epreuve->typeEpreuve?->libelle }}</td>
                            <td>{{ $p->salle?->nom ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @php
        $sessionOpen = $candidat->session?->isInscriptionOpen() ?? false;
    @endphp
    @if($candidat->statut === 'oui')
        <div class="card mb-4 border-warning">
            <div class="card-body">
                <h2 class="h5 mb-2"><i class="fas fa-credit-card text-warning me-2"></i>Paiement en attente</h2>
                <p class="mb-3">
                    Votre dossier a été accepté. Finalisez votre inscription en payant les
                    frais d'examen&nbsp;:
                    <strong>{{ number_format($candidat->session?->fraisInscription() ?? 10300, 0, ',', ' ') }} FCFA</strong>.
                </p>
                @if($sessionOpen)
                    <form method="POST" action="{{ route('concours.public.payment.start', $candidat->matricule_public) }}">
                        @csrf
                        <button class="btn btn-warning">
                            <i class="fas fa-credit-card me-2"></i>Payer maintenant via eBilling
                        </button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">
                        Vous serez redirigé(e) vers la plateforme eBilling. Une fois le paiement
                        confirmé par eBilling, le statut de votre dossier passera automatiquement à
                        « Validé ».
                    </p>
                @else
                    <div class="alert alert-secondary small mb-0">
                        Les inscriptions de cette session sont closes — le paiement n'est plus possible.
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($candidat->statut === 'rejete')
        @php
            $rejectedDocs = $candidat->documents->where('review_status', \Modules\Concours\Models\CandidatDocument::REVIEW_REJECTED)->values();
        @endphp
        <div class="alert alert-danger">
            <h5 class="alert-heading"><i class="fas fa-circle-exclamation me-2"></i>Dossier rejeté</h5>

            {{-- Layer 1 — global rejection motifs (always present, that's the
                 mandatory layer at admin reject time). --}}
            @if($candidat->motifsRejet->isNotEmpty())
                <p class="mb-2"><strong>Motif(s) du rejet&nbsp;:</strong></p>
                <ul class="mb-3">
                    @foreach($candidat->motifsRejet as $m)
                        <li>{{ $m->motif }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- Layer 2 — per-document rejection feedback (additive — present
                 only when chef-centre flagged specific files as "à refaire"). --}}
            @if($rejectedDocs->isNotEmpty())
                <hr>
                <p class="mb-2"><strong>Pièces à reprendre&nbsp;:</strong></p>
                <ul class="mb-3">
                    @foreach($rejectedDocs as $d)
                        <li>
                            <strong>{{ $d->documentRequis?->libelle ?? 'Pièce' }}</strong>
                            @if($d->review_comment)
                                — <em>{{ $d->review_comment }}</em>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <a href="{{ route('concours.public.lookup.form') }}" class="btn btn-light btn-sm">
                <i class="fas fa-pen me-2"></i>Modifier mon dossier
            </a>
        </div>
    @endif

    {{-- Per-doc review summary for non-rejected dossiers: a quick "review
         status" box so candidats whose dossier is still en cours can see
         which pieces have been checked off. Only shown when there's at
         least one document and we're not in the rejected/admis states. --}}
    @if(in_array($candidat->statut, ['non', 'oui', 'valid'], true) && $candidat->documents->isNotEmpty())
        @php
            $pendingDocs  = $candidat->documents->where('review_status', \Modules\Concours\Models\CandidatDocument::REVIEW_PENDING);
            $approvedDocs = $candidat->documents->where('review_status', \Modules\Concours\Models\CandidatDocument::REVIEW_APPROVED);
            $refaireDocs  = $candidat->documents->where('review_status', \Modules\Concours\Models\CandidatDocument::REVIEW_REJECTED);
        @endphp
        @if($refaireDocs->isNotEmpty() || $pendingDocs->count() < $candidat->documents->count())
            <div class="card mb-4">
                <div class="card-header bg-white"><h2 class="h5 mb-0">État des pièces justificatives</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach($candidat->documents as $d)
                        @php
                            $cls = match ($d->review_status) {
                                'valide'    => 'success',
                                'a_refaire' => 'danger',
                                default     => 'secondary',
                            };
                            $label = match ($d->review_status) {
                                'valide'    => 'Validée',
                                'a_refaire' => 'À refaire',
                                default     => 'En attente',
                            };
                            $icon = match ($d->review_status) {
                                'valide'    => 'fa-circle-check',
                                'a_refaire' => 'fa-rotate-left',
                                default     => 'fa-clock',
                            };
                        @endphp
                        <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong>{{ $d->documentRequis?->libelle ?? 'Pièce' }}</strong>
                                @if($d->review_status === 'a_refaire' && $d->review_comment)
                                    <div class="small text-danger mt-1">
                                        <i class="fas fa-comment me-1"></i>{{ $d->review_comment }}
                                    </div>
                                @endif
                            </div>
                            <span class="badge bg-{{ $cls }}">
                                <i class="fas {{ $icon }} me-1"></i>{{ $label }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

</section>
@endsection
