@extends('layouts.public')
@section('title', 'Vérifier ma demande')
@section('content')
<section class="container py-5" style="max-width:600px">
    <h1 class="h3 mb-4">Vérifier l'état de mon dossier</h1>
    <form method="POST" action="{{ route('concours.public.status') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Numéro d'inscrit (matricule)</label>
            <input type="text" name="matricule" class="form-control" placeholder="CUK-XXXXXXXX" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i> Vérifier</button>
    </form>
    <hr>
    <p class="text-muted small">
        Votre dossier a été rejeté ? <a href="{{ route('concours.public.lookup.form') }}">Récupérez-le pour le modifier</a>.
    </p>
</section>
@endsection
