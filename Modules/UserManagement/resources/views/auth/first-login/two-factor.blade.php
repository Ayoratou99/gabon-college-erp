@extends('usermanagement::layouts.guest')
@section('title', 'Activer la double authentification')

@section('content_inner')
    <p class="login-box-msg">
        Étape 3 sur 3 — sécurisez votre compte avec une application 2FA
        (Google Authenticator, Microsoft Authenticator, Authy…).
    </p>

    <div class="text-center mb-3">
        <div class="d-inline-block p-2 bg-white border rounded">
            {!! $qr !!}
        </div>
    </div>

    <p class="text-center small text-muted mb-3">
        Ou saisissez ce code manuellement&nbsp;:<br>
        <code class="user-select-all">{{ $secret }}</code>
    </p>

    <form method="POST" action="{{ route('first-login.2fa.submit') }}">
        @csrf
        <div class="input-group mb-3">
            <input type="text" name="otp"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                   class="form-control text-center fw-bold @error('otp') is-invalid @enderror"
                   placeholder="Code à 6 chiffres" autofocus required>
            <div class="input-group-text"><i class="fas fa-key"></i></div>
            @error('otp')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-success w-100">
            <i class="fas fa-check-double me-2"></i>Activer mon compte
        </button>
    </form>
@endsection
