<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $settings['site.banner.title'] ?? config('app.name'))</title>
    <meta name="description" content="{{ $settings['site.banner.subtitle'] ?? '' }}">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    @yield('content')

    <footer class="public-footer mt-5">
        <div class="container">
            <div class="row gy-3">
                <div class="col-md-6">
                    <strong>{{ config('app.name') }}</strong><br>
                    <small class="text-secondary">© {{ now()->year }} — Tous droits réservés.</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>
                        @if($settings['support.email'] ?? null)
                            <i class="far fa-envelope"></i>
                            <a href="mailto:{{ $settings['support.email'] }}">{{ $settings['support.email'] }}</a>
                            &nbsp;·&nbsp;
                        @endif
                        @if($settings['support.phone'] ?? null)
                            <i class="fas fa-phone"></i>
                            <a href="tel:{{ preg_replace('/\s+/', '', $settings['support.phone']) }}">{{ $settings['support.phone'] }}</a>
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
