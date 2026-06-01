<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $settings['site.banner.title'] ?? config('app.name'))</title>
    <meta name="description" content="{{ $settings['site.banner.subtitle'] ?? '' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    {{-- Brand palette pulled live from Parametrage — admin can change colors
         without redeploying. CSS variables propagate everywhere in app.scss. --}}
    <style>
        :root {
            --cuk-primary:  {{ $settings['site.theme.primary_color']  ?? '#1d4ed8' }};
            --cuk-accent:   {{ $settings['site.theme.accent_color']   ?? '#0ea5e9' }};
            --cuk-dark:     {{ $settings['site.theme.dark_color']     ?? '#0f172a' }};
            --cuk-success:  {{ $settings['site.theme.success_color']  ?? '#16a34a' }};
            --cuk-danger:   {{ $settings['site.theme.danger_color']   ?? '#dc2626' }};
            --cuk-primary-rgb: {{ implode(',', sscanf($settings['site.theme.primary_color'] ?? '#1d4ed8', '#%02x%02x%02x')) }};
        }
    </style>
    @stack('styles')
</head>
<body class="public-shell d-flex flex-column min-vh-100">

    {{-- Top navigation --}}
    @php
        // Empty string in DB → use the local bundled logo. The setting key
        // still exists (admin can override); we just provide a sensible default
        // for fresh installs and DBs seeded before the image was bundled.
        $brandLogo  = ($settings['site.brand.logo_url'] ?? '') ?: '/img/cuk/logo.jpg';
        $brandShort = $settings['site.brand.short_name'] ?? 'CUK';
        $brandFull  = $settings['site.brand.full_name']  ?? config('app.name');
        $currentRoute = request()->route()?->getName();
        $navItems = [
            ['route' => 'home',                          'label' => 'Accueil',          'icon' => 'fas fa-home'],
            ['route' => 'concours.inscription.form',     'label' => 'Inscription',      'icon' => 'fas fa-pen-to-square'],
            ['route' => 'concours.public.status.form',   'label' => 'Mon dossier',      'icon' => 'fas fa-magnifying-glass'],
            ['route' => 'concours.public.results',       'label' => 'Résultats',        'icon' => 'fas fa-trophy'],
        ];
    @endphp
    <nav class="public-nav navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                @if($brandLogo)
                    <img src="{{ \App\Support\Media::url($brandLogo) }}" alt="{{ $brandFull }}" class="brand-logo">
                @else
                    <span class="brand-mark">{{ $brandShort }}</span>
                @endif
                <span class="brand-tag d-none d-lg-inline">{{ $brandFull }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    @foreach($navItems as $n)
                        <li class="nav-item">
                            <a class="nav-link {{ $currentRoute === $n['route'] ? 'active' : '' }}" href="{{ route($n['route']) }}">
                                <i class="{{ $n['icon'] }} me-1"></i> {{ $n['label'] }}
                            </a>
                        </li>
                    @endforeach
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-cuk-primary btn-sm fw-semibold" href="{{ route('login') }}">
                            <i class="fas fa-user me-1"></i> Connexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    {{-- Page content fills remaining height — keeps footer sticky --}}
    <main class="flex-grow-1">
        @yield('content')
    </main>

    {{-- Big rich footer --}}
    <footer class="public-footer">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="footer-brand mb-3">{{ config('app.name') }}</h5>
                    <p class="text-light opacity-75 small">
                        {{ $settings['site.footer.about_text'] ?? '' }}
                    </p>
                    <div class="footer-social mt-3">
                        @if($settings['site.social.facebook'] ?? null)
                            <a href="{{ $settings['site.social.facebook'] }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        @endif
                        @if($settings['site.social.twitter'] ?? null)
                            <a href="{{ $settings['site.social.twitter'] }}" target="_blank" rel="noopener" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
                        @endif
                        @if($settings['site.social.linkedin'] ?? null)
                            <a href="{{ $settings['site.social.linkedin'] }}" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        @endif
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <h6 class="footer-heading">Liens rapides</h6>
                    <ul class="footer-links list-unstyled">
                        @foreach($settings['site.footer.quick_links'] ?? [] as $link)
                            <li><a href="{{ $link['url'] ?? '#' }}">{{ $link['label'] ?? '' }}</a></li>
                        @endforeach
                        <li><a href="{{ route('documents.officiels') }}"><i class="far fa-file-lines me-1"></i>Documents officiels</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 col-lg-5">
                    <h6 class="footer-heading">Contact</h6>
                    <ul class="footer-contact list-unstyled small">
                        @if($settings['site.footer.address'] ?? null)
                            <li><i class="fas fa-map-marker-alt"></i> {{ $settings['site.footer.address'] }}</li>
                        @endif
                        @if($settings['support.email'] ?? null)
                            <li><i class="far fa-envelope"></i> <a href="mailto:{{ $settings['support.email'] }}">{{ $settings['support.email'] }}</a></li>
                        @endif
                        @if($settings['support.phone'] ?? null)
                            <li><i class="fas fa-phone"></i> <a href="tel:{{ preg_replace('/\s+/', '', $settings['support.phone']) }}">{{ $settings['support.phone'] }}</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container py-3 d-flex flex-column flex-md-row justify-content-between align-items-center small">
                <span class="opacity-75">© {{ now()->year }} {{ config('app.name') }}. Tous droits réservés.</span>
                <span class="opacity-75">Plateforme refondue — version 2.</span>
            </div>
        </div>
    </footer>

    {{-- Carte flottante "Inscriptions ouvertes" — ne se rend que si la session
         active accepte encore les inscriptions et qu'on n'est pas déjà dans le
         tunnel d'inscription (self-gated dans le partial). --}}
    @include('partials.public.floating-cta')

    {{-- Left-edge « Voir l'annonce » — only when the public session has a flyer
         and inscriptions are open (self-gated in the partial). --}}
    @include('partials.public.floating-annonce')

    {{-- reCAPTCHA v3 — loaded site-wide on public pages when configured, BEFORE
         the page scripts so `grecaptcha` is defined for any form that calls it
         (login, PDF identity gate). Also surfaces Google's floating badge on
         every public page. Skipped entirely when NOCAPTCHA_SECRET is empty. --}}
    @if(config('usermanagement.recaptcha.enabled'))
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('usermanagement.recaptcha.site_key') }}" async defer></script>
    @endif

    @stack('scripts')
</body>
</html>
