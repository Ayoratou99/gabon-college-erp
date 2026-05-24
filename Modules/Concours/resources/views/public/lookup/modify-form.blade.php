@extends('layouts.public')
@section('title', 'Récupérer mon dossier')
@section('content')
<section class="container py-5" style="max-width:600px">
    <h1 class="h3 mb-4">Récupérer mon dossier rejeté</h1>
    <p class="text-muted">
        Saisissez l'email et le téléphone que vous avez utilisés lors de votre inscription.
    </p>
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('concours.public.lookup.submit') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Téléphone</label>
            <input type="tel" name="telephone" value="{{ old('telephone') }}" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Récupérer mon dossier</button>
    </form>
</section>
@endsection
