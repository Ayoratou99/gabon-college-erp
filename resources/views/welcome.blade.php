@extends('layouts.public')

@section('content')
<section class="hero">
    @if($settings['site.banner.background_image'] ?? null)
        <div class="hero-bg" style="background-image: url('{{ \App\Support\Media::url($settings['site.banner.background_image']) }}');"></div>
    @endif
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <span class="hero-eyebrow">Session {{ \Modules\Concours\Models\ConcoursSession::publicCurrent()?->anneeAcademique?->code ?? '2025-2026' }}</span>
        <h1>{{ $settings['site.banner.title'] ?? 'Concours d entree' }}</h1>
        <p class="lead">{{ $settings['site.banner.subtitle'] ?? '' }}</p>
        <div class="hero-actions">
            @if(($settings['site.banner.cta_link'] ?? null) && ($settings['site.banner.cta_text'] ?? null))
                <a href="{{ $settings['site.banner.cta_link'] }}" class="btn btn-light btn-lg">
                    <i class="fas fa-pen-to-square me-2"></i>{{ $settings['site.banner.cta_text'] }}
                </a>
            @endif
            @if(($settings['site.banner.secondary_cta_link'] ?? null) && ($settings['site.banner.secondary_cta_text'] ?? null))
                <a href="{{ $settings['site.banner.secondary_cta_link'] }}" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-search me-2"></i>{{ $settings['site.banner.secondary_cta_text'] }}
                </a>
            @endif
        </div>
    </div>
</section>

@if(($settings['site.stats'] ?? []) !== [])
<section class="container stats-strip">
    <div class="stats-card">
        @foreach($settings['site.stats'] ?? [] as $s)
            <div class="stat">
                <div class="stat-icon"><i class="{{ $s['icon'] ?? 'fas fa-star' }}"></i></div>
                <div class="stat-value">{{ $s['value'] ?? '' }}</div>
                <div class="stat-label">{{ $s['label'] ?? '' }}</div>
            </div>
        @endforeach
    </div>
</section>
@endif

@if(($settings['site.procedure_steps'] ?? []) !== [])
<section class="container py-5 my-4">
    <div class="section-title">
        <span class="eyebrow">Comment ca marche</span>
        <h2>Procedure d inscription en 4 etapes</h2>
        <p class="section-sub">De la saisie du formulaire au paiement des frais, voici le parcours complet d un candidat.</p>
    </div>
    <div class="procedure-timeline">
        @foreach($settings['site.procedure_steps'] ?? [] as $step)
            <div class="timeline-step">
                <div class="step-icon"><i class="{{ $step['icon'] ?? 'far fa-circle' }}"></i></div>
                <h4>{{ $step['title'] ?? '' }}</h4>
                <p>{{ $step['body'] ?? '' }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif

@php
    $formations = \Modules\AcademicStructure\Models\Section::query()
        ->where('ouvert_au_concours', true)
        ->where('active', true)
        ->orderBy('display_order')->orderBy('nom')
        ->get(['id', 'code', 'nom', 'description', 'image_url', 'places_par_session']);
@endphp
@if($formations->isNotEmpty())
<section class="container py-5 my-4">
    <div class="section-title">
        <span class="eyebrow">Nos formations</span>
        <h2>Filieres DUT ouvertes au concours</h2>
        <p class="section-sub">{{ $formations->count() }} formations technologiques de pointe — choisissez votre voie.</p>
    </div>
    @php
        // Rotate through the bundled CUK photos as a sensible default when a
        // section doesn't carry its own image. Admin can override per-section
        // by populating `image_url` on the section row (Académie → Sections).
        $formationFallbacks = [
            '/img/cuk/campus-view.jpg',
            '/img/cuk/laboratoires.jpg',
            '/img/cuk/equipements.jpg',
            '/img/cuk/amphi.jpg',
            '/img/cuk/campus-hero.jpg',
        ];
    @endphp
    <div class="row g-3">
        @foreach($formations as $i => $f)
            <div class="col-md-6 col-lg-4">
                <div class="formation-card">
                    <div class="formation-image">
                        <img src="{{ \App\Support\Media::url($f->image_url ?: $formationFallbacks[$i % count($formationFallbacks)]) }}"
                             alt="{{ $f->nom }}" loading="lazy">
                        <span class="formation-badge formation-badge--over">{{ $f->code }}</span>
                    </div>
                    <div class="formation-body">
                        <h4 class="mb-2">{{ $f->nom }}</h4>
                        <p class="text-muted small mb-0">
                            {{ $f->description ?: 'Formation technologique en deux annees — debouches en industrie, recherche et entreprise.' }}
                        </p>
                        <div class="formation-meta">
                            <span><i class="fas fa-users"></i> {{ $f->places_par_session }} places</span>
                            <span><i class="fas fa-clock"></i> 2 ans</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

@if(($settings['site.home.sections'] ?? []) !== [])
<section class="container py-5 my-4">
    <div class="section-title">
        <span class="eyebrow">Services candidats</span>
        <h2>Tout ce dont vous avez besoin</h2>
    </div>
    <div class="row g-4">
        @foreach(collect($settings['site.home.sections'] ?? [])->sortBy('order') as $section)
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="{{ $section['icon'] ?? 'fas fa-circle' }}"></i></div>
                    <div class="feature-body">
                        <h3>{{ $section['title'] ?? '' }}</h3>
                        <p>{{ $section['body'] ?? '' }}</p>
                        @if(($section['link'] ?? null) && ($section['cta'] ?? null))
                            <a href="{{ $section['link'] }}" class="feature-cta">
                                {{ $section['cta'] }} <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

@if($settings['site.about.title'] ?? null)
<section class="container my-5">
    <div class="about-panel row align-items-center g-4">
        <div class="col-lg-7">
            <span class="text-uppercase fw-bold small" style="color: var(--cuk-primary); letter-spacing: .14em;">A propos</span>
            <h2 class="mt-2 mb-3">{{ $settings['site.about.title'] }}</h2>
            <p>{{ $settings['site.about.text'] ?? '' }}</p>
        </div>
        <div class="col-lg-5 text-center">
            <div class="display-1" style="color: var(--cuk-accent);"><i class="fas fa-university"></i></div>
        </div>
    </div>
</section>
@endif

@php $ctaSession = \Modules\Concours\Models\ConcoursSession::publicCurrent(); @endphp
<section class="container">
    <div class="cta-banner" id="cta-banner-final">
        <h3>Inscriptions ouvertes pour la session {{ $ctaSession?->anneeAcademique?->code ?? '2025-2026' }}</h3>
        <p>Frais d inscription&nbsp;: <strong>{{ number_format($ctaSession?->fraisInscription() ?? config('concours.payment.default_amount', 10300), 0, ',', ' ') }} {{ $settings['concours.fee.currency'] ?? 'FCFA' }}</strong> — payables apres validation du dossier.</p>
        <a href="{{ route('concours.inscription.form') }}" class="btn"><i class="fas fa-paper-plane me-2"></i> Commencer mon inscription</a>
    </div>
</section>
@endsection
