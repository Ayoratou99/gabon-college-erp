@extends('layouts.public')

@section('title', 'Modification enregistrée')

@section('content')
<section class="container py-5">
    <div class="alert alert-success">
        <h2 class="alert-heading"><i class="fas fa-check-circle me-2"></i> Votre dossier a été mis à jour.</h2>
        <p class="mb-2">
            Votre dossier <strong>{{ $matricule }}</strong> a été remis en file d'attente
            pour une nouvelle revue par notre équipe.
        </p>
        <p class="mb-0">
            Vous serez notifié(e) par email dès qu'une décision aura été prise.
        </p>
        <hr>
        <p class="mb-0">
            <a href="{{ route('concours.public.status.form') }}" class="btn btn-light">
                <i class="fas fa-search me-2"></i>Vérifier l'état de mon dossier
            </a>
            <a href="{{ route('home') }}" class="btn btn-outline-secondary">Retour à l'accueil</a>
        </p>
    </div>
</section>
@endsection
