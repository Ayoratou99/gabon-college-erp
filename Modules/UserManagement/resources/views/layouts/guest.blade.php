@extends('layouts.public')

@php
    /** @var array<string, mixed> $settings */
    $authSubtitle = $authSubtitle ?? 'Connexion à votre espace';
@endphp

@php
    $authBg = $settings['site.auth.background_image'] ?? '/img/cuk/amphi.jpg';
@endphp

@push('styles')
    <style>
        body.public-shell { background: #f1f5f9; }

        /* Auth band — admin-chosen photo behind a coloured gradient overlay.
           Falls back gracefully to a pure-gradient backdrop if no image. */
        .auth-band {
            position: relative;
            padding: 4.5rem 1rem;
            overflow: hidden;
            @if($authBg)
                background:
                    linear-gradient(135deg,
                        color-mix(in srgb, var(--cuk-primary, #1d4ed8) 80%, transparent),
                        color-mix(in srgb, var(--cuk-accent,  #0ea5e9) 70%, transparent)),
                    url('{{ \App\Support\Media::url($authBg) }}') center / cover no-repeat;
            @else
                background: linear-gradient(135deg,
                    color-mix(in srgb, var(--cuk-primary, #1d4ed8) 90%, transparent),
                    color-mix(in srgb, var(--cuk-accent,  #0ea5e9) 80%, transparent));
            @endif
        }
        .auth-band::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 15% 20%, rgba(255,255,255,.18), transparent 45%),
                        radial-gradient(circle at 85% 80%, rgba(255,255,255,.12), transparent 45%);
            pointer-events: none;
        }
        .auth-card {
            position: relative;
            width: 100%; max-width: 480px; margin: 0 auto;
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 26px 60px rgba(15, 23, 42, .22);
            overflow: hidden;
        }
        .auth-card-header {
            padding: 1.75rem 2rem 1.5rem;
            background: linear-gradient(135deg, var(--cuk-primary, #1d4ed8), var(--cuk-accent, #0ea5e9));
            color: #fff;
            text-align: center;
        }
        .auth-card-header img.brand-logo {
            height: 56px; width: auto;
            background: #fff;
            padding: 4px;
            border-radius: 12px;
            box-shadow: 0 8px 18px rgba(0,0,0,.18);
            margin-bottom: .9rem;
        }
        .auth-card-header h1 {
            font-size: 1.35rem; font-weight: 800; letter-spacing: -.01em; margin: 0;
        }
        .auth-card-header .subtitle {
            opacity: .9; font-size: .88rem; margin-top: .35rem; font-weight: 500;
        }
        .auth-card-body { padding: 2rem; }
        .auth-card .form-control {
            padding: .65rem .85rem; border-radius: .55rem;
            border: 1.5px solid rgba(15, 23, 42, .1);
            transition: border-color .14s ease, box-shadow .14s ease;
        }
        .auth-card .form-control:focus {
            border-color: var(--cuk-primary, #1d4ed8);
            box-shadow: 0 0 0 3px rgba(var(--cuk-primary-rgb, 29 78 216), .15);
            outline: none;
        }
        .auth-card .input-group-text {
            background: #f1f5f9; border: 1.5px solid rgba(15, 23, 42, .1);
            color: var(--cuk-primary, #1d4ed8);
        }
        .auth-card .btn-primary {
            background: linear-gradient(135deg, var(--cuk-primary, #1d4ed8), var(--cuk-accent, #0ea5e9));
            border: none;
            padding: .7rem; font-weight: 700; border-radius: .55rem;
            box-shadow: 0 8px 20px rgba(var(--cuk-primary-rgb, 29 78 216), .35);
            transition: transform .12s ease, box-shadow .15s ease;
        }
        .auth-card .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(var(--cuk-primary-rgb, 29 78 216), .45);
        }
        .auth-card .login-box-msg {
            color: #475569; font-size: .92rem; text-align: center; margin-bottom: 1.25rem;
        }
    </style>
@endpush

@section('content')
    <section class="auth-band">
        <div class="container">
            <div class="auth-card">
                <div class="auth-card-header">
                    @if($settings['site.brand.logo_url'] ?? null)
                        <img src="{{ \App\Support\Media::url($settings['site.brand.logo_url']) }}" alt="Logo" class="brand-logo">
                    @endif
                    <h1>{{ $settings['site.brand.full_name'] ?? config('app.name') }}</h1>
                    <div class="subtitle">{{ $authSubtitle }}</div>
                </div>
                <div class="auth-card-body">
                    @yield('content_inner')
                </div>
            </div>
            <p class="text-center mt-4 mb-0 small" style="color: rgba(255,255,255,.92);">
                <a href="{{ route('home') }}" class="text-white text-decoration-none fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i> Retour au site public
                </a>
            </p>
        </div>
    </section>
@endsection
