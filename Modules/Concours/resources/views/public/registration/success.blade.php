@extends('layouts.public')

@section('title', 'Inscription enregistrée')

@section('content')
<section class="container py-5">
    <div class="alert alert-success">
        <h2 class="alert-heading"><i class="fas fa-check-circle me-2"></i> Votre dossier est enregistré.</h2>
        <p class="mb-2">Conservez ce numéro pour suivre l'état de votre demande&nbsp;:</p>
        <p class="display-6 fw-bold">{{ $matricule }}</p>
        <hr>
        <p class="mb-0">
            <a href="{{ route('concours.public.status.form') }}" class="btn btn-light">
                <i class="fas fa-search me-2"></i> Vérifier l'état de mon dossier
            </a>
        </p>
    </div>
</section>
@endsection
