@extends('layouts.public')
@section('title', 'Résultats du concours')

@section('content')
<section class="container py-5" style="max-width:1100px">

    <h1 class="h3 mb-3">Résultats du concours d'entrée</h1>

    @if($publication === null)
        <div class="alert alert-info">
            Les résultats ne sont pas encore publiés. Revenez après la date de publication
            annoncée par le Centre Universitaire de Koulamoutou.
        </div>
    @else
        <p class="text-muted">
            Publié le {{ $publication->published_at->format('d/m/Y à H:i') }} —
            <strong>{{ $publication->total_admis }}</strong> admis sur
            <strong>{{ $publication->total_candidats }}</strong> dossiers.
        </p>

        @if($publication->communique)
            <div class="alert alert-light border">{!! nl2br(e($publication->communique)) !!}</div>
        @endif

        @if($publication->fichier_path)
            <p>
                <a href="#" class="btn btn-outline-primary">
                    <i class="fas fa-file-pdf me-2"></i> Télécharger le procès-verbal officiel
                </a>
            </p>
        @endif

        <div class="card">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Matricule</th>
                        <th>Nom &amp; prénom</th>
                        <th>Section d'orientation</th>
                        <th class="text-end">Moyenne</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($admis as $c)
                        <tr>
                            <td>{{ $c->rang ?? '—' }}</td>
                            <td><code>{{ $c->matricule_public }}</code></td>
                            <td>{{ $c->nom }} {{ $c->prenom }}</td>
                            <td>{{ $c->sectionOrientation?->nom }}</td>
                            <td class="text-end">{{ $c->moyenne }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</section>
@endsection
