@extends('usermanagement::layouts.guest')
@section('title', 'Première connexion')

@section('content_inner')
    <p class="login-box-msg">
        Étape 1 sur 3 — identifiez-vous avec l'email et le téléphone que vous avez fournis lors de l'inscription au concours.
    </p>

    <form method="POST" action="{{ route('first-login.start.submit') }}">
        @csrf
        <div class="input-group mb-3">
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   placeholder="Email" autofocus required>
            <div class="input-group-text"><i class="fas fa-envelope"></i></div>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="input-group mb-3">
            <input type="tel" name="telephone" value="{{ old('telephone') }}"
                   class="form-control @error('telephone') is-invalid @enderror"
                   placeholder="Téléphone" required>
            <div class="input-group-text"><i class="fas fa-phone"></i></div>
            @error('telephone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-arrow-right me-2"></i>Continuer
        </button>
    </form>

    <hr class="my-4">
    <p class="text-center small mb-0">
        Vous avez déjà activé votre compte ?
        <a href="{{ route('login') }}" class="fw-semibold">Connectez-vous</a>.
    </p>
@endsection
