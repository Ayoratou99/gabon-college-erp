@extends('layouts.public')
@section('title', 'Vérifier ma demande')

@section('content')
<section class="page-hero">
    <div class="container">
        <h1><i class="fas fa-search me-2"></i> Vérifier mon dossier</h1>
        <p>Saisissez votre matricule, votre nom, votre email ou votre numéro de téléphone — nous retrouvons votre dossier pour la session en cours.</p>
    </div>
</section>

<section class="container py-5">
    <div class="form-card mx-auto" style="max-width: 640px;">
        <form method="POST" action="{{ route('concours.public.status') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Votre identifiant</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" name="q" value="{{ old('q', $term ?? '') }}"
                           class="form-control form-control-lg fw-semibold"
                           placeholder="Matricule, nom, email ou téléphone"
                           autofocus required autocomplete="off">
                </div>
                <div class="form-text small mt-2">
                    Exemples&nbsp;: <code>CUK-XXXXXXXXXXXX</code> · <code>Mavoungou</code> · <code>marie@…</code> · <code>065&nbsp;…</code>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="fas fa-search me-2"></i> Rechercher
            </button>
        </form>

        @php
            // Only surface the rejected-dossier recovery flow when the active
            // session is currently in its inscription window. Once the window
            // is closed, modification is no longer allowed (the server-side
            // submitLookup will refuse too).
            $activeOpen = \Modules\Concours\Models\ConcoursSession::active()?->isInscriptionOpen() ?? false;
        @endphp
        @if($activeOpen)
            <hr class="my-4">
            <p class="text-center text-muted small mb-0">
                <i class="far fa-life-ring me-1"></i>
                Votre dossier a été rejeté ?
                <a href="{{ route('concours.public.lookup.form') }}" class="fw-semibold">
                    Récupérez-le pour le modifier
                </a>.
            </p>
        @endif
    </div>
</section>
@endsection
