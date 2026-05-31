@extends('layouts.public')

@php
    // Defaults — the inscription flow passes nothing; the modify flow passes
    // its own variants. Keeping the variables at the top of the layout means
    // a quick glance shows what the wizard is parameterized on.
    $heroTitle      = $heroTitle      ?? 'Inscription au concours';
    $heroIcon       = $heroIcon       ?? 'fas fa-pen-to-square';
    $heroSubtitle   = $heroSubtitle   ?? null;
    $submitLabel    = $submitLabel    ?? 'Soumettre mon dossier';
    $submitRoute    = $submitRoute    ?? 'concours.inscription.wizard.submit';
    $backRoute      = $backRoute      ?? 'concours.inscription.wizard.back';
    $resetRoute     = $resetRoute     ?? 'concours.inscription.wizard.reset';
    $routeParams    = $routeParams    ?? [];   // e.g. ['token' => $token] for the modify flow
@endphp

@section('title', $heroTitle . ' — étape ' . ($stepIndex + 1) . '/' . $totalSteps)

@section('content')
<section class="page-hero">
    <div class="container">
        <h1><i class="{{ $heroIcon }} me-2"></i>{{ $heroTitle }}</h1>
        @if($heroSubtitle)
            <p>{!! $heroSubtitle !!}</p>
        @else
            <p>
                Session <strong>{{ $session->libelle }}</strong> &middot;
                Épreuve&nbsp;: <strong>{{ $session->date_concours->format('d/m/Y') }}</strong> &middot;
                Frais&nbsp;: <strong>{{ number_format($session->fraisInscription(), 0, ',', ' ') }} FCFA</strong>
            </p>
        @endif
    </div>
</section>

<section class="container py-5" style="max-width: 920px;">

    {{-- Progress bar / stepper --}}
    <div class="wizard-stepper mb-4">
        @php $progressPct = (int) round((($stepIndex + 1) / $totalSteps) * 100); @endphp
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted">Étape {{ $stepIndex + 1 }} sur {{ $totalSteps }}</span>
            <span class="small text-muted">{{ $progressPct }}%</span>
        </div>
        <div class="progress" role="progressbar" aria-valuenow="{{ $progressPct }}" aria-valuemin="0" aria-valuemax="100" style="height: .55rem;">
            <div class="progress-bar bg-primary" style="width: {{ $progressPct }}%"></div>
        </div>
        <ol class="list-unstyled d-flex flex-wrap gap-2 mt-3 mb-0 small">
            @foreach($steps as $idx => $s)
                @php
                    $isCurrent = ($s === $currentStep);
                    $isPast    = ($idx < $stepIndex);
                @endphp
                <li class="me-3">
                    <span class="wizard-step-chip {{ $isCurrent ? 'wizard-step-chip--current' : ($isPast ? 'wizard-step-chip--past' : 'wizard-step-chip--upcoming') }}">
                        <span class="wizard-step-num">{{ $idx + 1 }}</span>
                        {{ $stepLabels[$s] }}
                        @if($isPast)<i class="fas fa-check ms-1 text-success small"></i>@endif
                    </span>
                </li>
            @endforeach
        </ol>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger" role="alert">
            <strong><i class="fas fa-circle-exclamation me-2"></i>Quelques champs ont besoin d'attention&nbsp;:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-info">{!! session('status') !!}</div>
    @endif

    <form method="POST"
          action="{{ route($submitRoute, array_merge($routeParams, ['step' => $currentStep])) }}"
          enctype="multipart/form-data" novalidate>
        @csrf

        <div class="form-card mb-4">
            <h2 class="h5 mb-3">{{ $stepLabels[$currentStep] }}</h2>
            @include('concours::public.registration.steps.' . $currentStep, [
                'draft' => $draft,
            ])
        </div>

        {{-- Navigation --}}
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                @unless($isFirst)
                    <button type="submit"
                            formaction="{{ route($backRoute, array_merge($routeParams, ['step' => $currentStep])) }}"
                            formnovalidate
                            class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Précédent
                    </button>
                @endunless
            </div>
            <div class="d-flex gap-2">
                @if(! empty($draft))
                    <button type="submit"
                            formaction="{{ route($resetRoute, $routeParams) }}"
                            formnovalidate
                            class="btn btn-link text-danger"
                            onclick="return confirm('Effacer toutes les données saisies et recommencer ?');">
                        <i class="fas fa-rotate-left me-1"></i>Recommencer
                    </button>
                @endif
                <button type="submit" class="btn btn-primary">
                    @if($isLast)
                        <i class="fas fa-paper-plane me-2"></i>{{ $submitLabel }}
                    @else
                        Suivant <i class="fas fa-arrow-right ms-2"></i>
                    @endif
                </button>
            </div>
        </div>
    </form>

</section>
@endsection
