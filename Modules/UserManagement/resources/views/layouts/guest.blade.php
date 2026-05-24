<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(config('usermanagement.recaptcha.enabled'))
        <meta name="recaptcha-site-key" content="{{ config('usermanagement.recaptcha.site_key') }}">
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('usermanagement.recaptcha.site_key') }}" async defer></script>
    @endif
    <title>@yield('title', config('app.name'))</title>

    {{-- AdminLTE 4 + Bootstrap 5 from CDN until Vite is wired in Stage 3. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc4/dist/css/adminlte.min.css">

    <style>
        body.login-page { background: linear-gradient(135deg, #1d4ed8 0%, #0ea5e9 100%); min-height: 100vh; }
        .login-card { box-shadow: 0 10px 35px rgba(0,0,0,.18); border: 0; }
        .login-logo a { color: #fff; font-weight: 700; letter-spacing: .5px; }
        .invalid-feedback.d-block { display: block; }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <a href="{{ route('home') }}">{{ config('app.name', 'CUK Concours') }}</a>
        </div>

        <div class="card login-card">
            <div class="card-body login-card-body">
                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
