@extends('usermanagement::layouts.guest')

@section('title', __('Double authentification'))

@section('content')
    @if($mode === 'enroll')
        <p class="login-box-msg">
            Activez la double authentification : scannez ce QR code avec
            <strong>Google Authenticator</strong> puis saisissez le code à 6 chiffres.
        </p>

        <div class="text-center mb-3">{!! $qr !!}</div>

        <p class="text-center small text-muted">
            Code manuel :
            <code class="user-select-all">{{ $secret }}</code>
        </p>
    @else
        <p class="login-box-msg">
            Saisissez le code à 6 chiffres affiché dans votre application d'authentification.
        </p>
    @endif

    <form method="POST" action="{{ $mode === 'enroll' ? route('two-factor.enroll') : route('two-factor.challenge') }}">
        @csrf
        <div class="input-group mb-3">
            <input type="text" name="otp" maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                   class="form-control text-center fs-3 letter-spacing-3 @error('otp') is-invalid @enderror"
                   placeholder="••••••" autofocus required>
            @error('otp')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">
            {{ $mode === 'enroll' ? 'Activer la double authentification' : 'Vérifier' }}
        </button>
    </form>

    <p class="mt-3 text-center">
        <a href="{{ route('login') }}" class="small text-muted">Revenir à la connexion</a>
    </p>
@endsection
