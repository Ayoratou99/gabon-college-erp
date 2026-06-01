@extends('layouts.public')
@section('title', 'Récupérer mon dossier')

@section('content')
<section class="page-hero">
    <div class="container">
        <h1><i class="fas fa-edit me-2"></i> Récupérer mon dossier rejeté</h1>
        <p>Identifiez-vous avec l'email et le téléphone utilisés à l'inscription.</p>
    </div>
</section>

<section class="container py-5">
    <div class="form-card mx-auto" style="max-width: 560px;">
        @if ($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-circle-exclamation me-2"></i>{{ $errors->first() }}
            </div>
        @endif
        <form method="POST" action="{{ route('concours.public.lookup.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email d'inscription</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="form-control form-control-lg" placeholder="vous@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Téléphone d'inscription</label>
                <input type="tel" name="telephone" value="{{ old('telephone') }}"
                       class="form-control form-control-lg" placeholder="077056138" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="fas fa-key me-2"></i>Récupérer mon dossier
            </button>
        </form>
        <p class="text-center small text-muted mt-3 mb-0">
            <i class="far fa-shield me-1"></i> Pour votre sécurité, nous ne révélons jamais qu'un dossier
            existe pour ces informations s'il n'a pas été rejeté.
        </p>
    </div>
</section>
@endsection
