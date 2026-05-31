@extends('layouts.public')
@section('title', 'Inscriptions fermées')
@section('content')
<section class="page-hero">
    <div class="container">
        <h1><i class="fas fa-lock me-2"></i>Inscriptions fermées</h1>
        <p>Les inscriptions au concours ne sont pas ouvertes pour le moment.</p>
    </div>
</section>

<section class="container py-5">
    <div class="status-shell text-center mx-auto" style="max-width: 640px;">
        <div class="display-4 mb-3" style="color: var(--cuk-accent);"><i class="far fa-calendar-times"></i></div>
        <h3 class="fw-bold mb-2">Revenez à la prochaine ouverture</h3>
        <p class="text-muted mb-4">
            Suivez notre actualité pour être notifié de l'ouverture des inscriptions
            pour la prochaine session du concours d'entrée.
        </p>
        <a href="{{ route('home') }}" class="btn btn-primary">
            <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
        </a>
    </div>
</section>
@endsection
