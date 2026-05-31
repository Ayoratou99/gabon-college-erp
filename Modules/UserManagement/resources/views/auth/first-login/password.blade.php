@extends('usermanagement::layouts.guest')
@section('title', 'Définir mon mot de passe')

@section('content_inner')
    <p class="login-box-msg">
        Étape 2 sur 3 — bienvenue <strong>{{ $user->prenom }} {{ $user->nom }}</strong>.
        Choisissez un mot de passe que vous serez seul(e) à connaître.
    </p>

    <form method="POST" action="{{ route('first-login.password.submit') }}">
        @csrf
        <div class="input-group mb-3">
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="Nouveau mot de passe" autofocus required minlength="10">
            <div class="input-group-text"><i class="fas fa-lock"></i></div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="input-group mb-3">
            <input type="password" name="password_confirmation"
                   class="form-control" placeholder="Confirmer le mot de passe" required minlength="10">
            <div class="input-group-text"><i class="fas fa-check"></i></div>
        </div>

        <div class="alert alert-info small mb-3">
            <i class="fas fa-shield-halved me-1"></i>
            Minimum 10 caractères. Mélangez lettres, chiffres et symboles pour plus de sécurité.
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-arrow-right me-2"></i>Enregistrer et continuer
        </button>
    </form>
@endsection
