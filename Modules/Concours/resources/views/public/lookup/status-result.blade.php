@extends('layouts.public')
@section('title', 'Statut du dossier')
@section('content')
<section class="container py-5" style="max-width:720px">
    @if ($candidat === null)
        <div class="alert alert-warning">
            Aucun dossier trouvé pour <strong>{{ $matricule }}</strong>.
        </div>
        <a href="{{ route('concours.public.status.form') }}" class="btn btn-secondary">Réessayer</a>
    @else
        <div class="card">
            <div class="card-body">
                <h2 class="h4">{{ $candidat->prenom }} {{ $candidat->nom }}</h2>
                <p class="text-muted">Matricule : {{ $candidat->matricule_public }}</p>

                @switch($candidat->statut)
                    @case('non')
                        <div class="alert alert-info">
                            <strong>Dossier en cours de traitement.</strong> Revenez plus tard pour connaître la décision.
                        </div>
                        @break
                    @case('oui')
                        <div class="alert alert-warning">
                            <strong>Dossier accepté</strong> — en attente du paiement des frais d'inscription.
                        </div>
                        <p>Procédez au paiement pour finaliser votre inscription.</p>
                        @break
                    @case('valid')
                        <div class="alert alert-success">
                            <strong>Inscription validée.</strong> Vous serez convoqué(e) à l'épreuve.
                        </div>
                        @break
                    @case('rejete')
                        <div class="alert alert-danger">
                            <strong>Dossier rejeté.</strong>
                            <ul>
                                @foreach ($candidat->motifsRejet as $m)
                                    <li>{{ $m->motif }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <a href="{{ route('concours.public.lookup.form') }}" class="btn btn-primary">
                            Modifier mon dossier
                        </a>
                        @break
                @endswitch
            </div>
        </div>
    @endif
</section>
@endsection
