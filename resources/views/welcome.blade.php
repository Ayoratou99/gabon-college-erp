@extends('layouts.public')

@section('content')
    <section class="hero">
        <div class="container">
            <h1 class="display-5">{{ $settings['site.banner.title'] ?? 'Concours d\'entrée' }}</h1>
            <p class="lead">{{ $settings['site.banner.subtitle'] ?? '' }}</p>
            @if(($settings['site.banner.cta_link'] ?? null) && ($settings['site.banner.cta_text'] ?? null))
                <a href="{{ $settings['site.banner.cta_link'] }}" class="btn btn-light btn-lg fw-bold">
                    <i class="fas fa-pen me-2"></i>{{ $settings['site.banner.cta_text'] }}
                </a>
            @endif
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4">
            @foreach (collect($settings['site.home.sections'] ?? [])->sortBy('order') as $section)
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3">
                            <i class="{{ $section['icon'] ?? 'fas fa-circle' }}"></i>
                        </div>
                        <h3 class="h5">{{ $section['title'] ?? '' }}</h3>
                        <p class="text-muted">{{ $section['body'] ?? '' }}</p>
                        @if(($section['link'] ?? null) && ($section['cta'] ?? null))
                            <a href="{{ $section['link'] }}" class="mt-auto fw-semibold">
                                {{ $section['cta'] }} <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if(($settings['concours.fee.amount'] ?? null) !== null)
            <div class="alert alert-info text-center mt-5 mb-0">
                Les frais d'inscription au concours s'élèvent à
                <strong>{{ number_format($settings['concours.fee.amount'], 0, ',', ' ') }}
                {{ $settings['concours.fee.currency'] ?? 'FCFA' }}</strong>,
                payables après acceptation du dossier.
            </div>
        @endif
    </section>
@endsection
