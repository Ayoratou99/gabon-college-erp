@extends('usermanagement::layouts.guest')

@section('title', __('Connexion'))

@section('content')
    <p class="login-box-msg">Connectez-vous pour accéder au tableau de bord.</p>

    <form id="login-form" method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <input type="hidden" name="g-recaptcha-response" id="recaptcha-token">

        <div class="input-group mb-3">
            <input type="text" name="identifier" value="{{ old('identifier') }}"
                   class="form-control @error('identifier') is-invalid @enderror"
                   placeholder="Email ou téléphone" autofocus required>
            <div class="input-group-text"><i class="fas fa-user"></i></div>
            @error('identifier')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="input-group mb-3">
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="Mot de passe" required>
            <div class="input-group-text"><i class="fas fa-lock"></i></div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-7">
                <div class="form-check">
                    <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                </div>
            </div>
            <div class="col-5">
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </div>
        </div>
    </form>

    @if(config('usermanagement.recaptcha.enabled'))
        @push('scripts')
            <script>
                document.getElementById('login-form').addEventListener('submit', function (e) {
                    e.preventDefault();
                    const form = e.target;
                    grecaptcha.ready(function () {
                        grecaptcha.execute('{{ config('usermanagement.recaptcha.site_key') }}', { action: 'login' })
                            .then(function (token) {
                                document.getElementById('recaptcha-token').value = token;
                                form.submit();
                            });
                    });
                });
            </script>
        @endpush
    @endif
@endsection
