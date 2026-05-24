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

    @if($candidat->statut === 'oui')
        <div class="alert alert-warning">
            <strong>Paiement en attente.</strong> Votre dossier a été accepté, finalisez le paiement
            ({{ number_format($candidat->session?->fraisInscription() ?? 10300, 0, ',', ' ') }} FCFA).
        </div>
    @endif

    @if($candidat->statut === 'rejete')
        <div class="alert alert-danger">
            <h5>Dossier rejeté</h5>
            <ul>
                @foreach($candidat->motifsRejet as $m)
                    <li>{{ $m->motif }}</li>
                @endforeach
            </ul>
            <a href="{{ route('concours.public.lookup.form') }}" class="btn btn-light btn-sm">
                Modifier mon dossier
            </a>
        </div>
    @endif

</section>
@endsection
