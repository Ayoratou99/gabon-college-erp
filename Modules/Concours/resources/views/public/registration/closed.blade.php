@extends('layouts.public')
@section('title', 'Inscriptions fermées')
@section('content')
<section class="container py-5 text-center">
    <h1 class="mb-3"><i class="fas fa-lock"></i> Inscriptions fermées</h1>
    <p class="lead">Les inscriptions au concours ne sont pas ouvertes pour le moment.</p>
    <a href="{{ route('home') }}" class="btn btn-secondary">Retour à l'accueil</a>
</section>
@endsection
