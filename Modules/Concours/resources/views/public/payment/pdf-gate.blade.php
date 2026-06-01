@extends('layouts.public')
@section('title', $document === 'fiche' ? 'Télécharger ma fiche' : 'Télécharger mon emploi du temps')

@php
    $docCopy = $document === 'fiche'
        ? [
            'icon'  => 'far fa-file-pdf',
            'title' => 'Télécharger ma fiche d\'inscription',
            'lead'  => 'Pour télécharger votre fiche, confirmez votre identité avec l\'email et le téléphone que vous avez fournis lors de votre inscription.',
        ]
        : [
            'icon'  => 'far fa-calendar-alt',
            'title' => 'Télécharger mon emploi du temps des épreuves',
            'lead'  => 'Pour télécharger votre planning, confirmez votre identité avec l\'email et le téléphone que vous avez fournis lors de votre inscription.',
        ];
    $recaptchaEnabled = (bool) config('usermanagement.recaptcha.enabled');
    {{-- api.js is now loaded globally in layouts.public when reCAPTCHA is
         enabled (this page previously pushed it to a non-existent @stack('head'),
         so it never loaded). `grecaptcha` is available for the handler below. --}}
@endphp

@section('content')
<section class="container py-5">
    <div class="form-card mx-auto" style="max-width: 540px;">
        <div class="text-center mb-3">
            <div class="display-5"><i class="{{ $docCopy['icon'] }} text-primary"></i></div>
            <h1 class="h4 mt-2 mb-1">{{ $docCopy['title'] }}</h1>
            <p class="small text-muted mb-0">
                Matricule&nbsp;: <code>{{ $candidat->matricule_public }}</code>
            </p>
        </div>

        <p class="small text-muted text-center">{{ $docCopy['lead'] }}</p>

        <form id="pdf-gate-form" method="POST"
              action="{{ route('concours.public.candidat.pdf.stream', ['matricule' => $candidat->matricule_public, 'document' => $document]) }}">
            @csrf
            <input type="hidden" name="g-recaptcha-response" id="recaptcha-token">

            <div class="mb-3">
                <label class="form-label small">Email d'inscription <span class="text-danger">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror"
                       placeholder="ex. marie.dupont@gmail.com" required autofocus>
                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label small">Numéro de téléphone <span class="text-danger">*</span></label>
                <input type="tel" name="telephone" value="{{ old('telephone') }}"
                       class="form-control @error('telephone') is-invalid @enderror"
                       placeholder="ex. 074 12 34 56" required>
                @error('telephone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="fas fa-download me-2"></i>Télécharger le PDF
            </button>
        </form>

        <p class="text-center mt-3 mb-0 small">
            <a href="{{ route('concours.public.candidat.dashboard', $candidat->matricule_public) }}"
               class="text-muted">
                <i class="fas fa-arrow-left me-1"></i>Retour à mon dossier
            </a>
        </p>
    </div>
</section>
@endsection

@push('scripts')
@if($recaptchaEnabled)
<script>
    // reCAPTCHA v3 — invisible token issued at submit time.
    document.getElementById('pdf-gate-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.target;
        grecaptcha.ready(function () {
            grecaptcha.execute('{{ config('usermanagement.recaptcha.site_key') }}', { action: 'pdf_download' })
                .then(function (token) {
                    document.getElementById('recaptcha-token').value = token;
                    form.submit();
                });
        });
    });
</script>
@endif
@endpush
