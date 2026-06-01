{{-- Left-edge floating « Voir l'annonce » — shown on public pages when the
     public-current session has an announcement flyer AND inscriptions are open.
     Hidden on the /annonce page itself (would be redundant). --}}
@php
    $anSession = \Modules\Concours\Models\ConcoursSession::publicCurrent();
    $anRoute   = (string) (request()->route()?->getName() ?? '');
    $anShow    = $anSession
        && $anSession->hasFlyer()
        && ($anSession->isInscriptionOpen() ?? false)
        && ! str_contains($anRoute, 'annonce')
        && ! str_contains($anRoute, 'inscription');
@endphp

@if($anShow)
<a href="{{ route('annonce') }}" class="floating-annonce" aria-label="Voir l'annonce">
    <span class="floating-annonce__dot"></span>
    <i class="fas fa-bullhorn"></i>
    <span class="floating-annonce__label">Voir l'annonce</span>
</a>
<style>
    .floating-annonce {
        position: fixed; left: 0; top: 50%; transform: translateY(-50%);
        z-index: 1044; display: inline-flex; align-items: center; gap: 8px;
        background: linear-gradient(135deg, var(--cuk-accent, #0ea5e9), var(--cuk-primary, #1d4ed8));
        color: #fff !important; padding: 11px 16px 11px 13px;
        border-radius: 0 14px 14px 0; text-decoration: none;
        font-weight: 700; font-size: .9rem; letter-spacing: .01em;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .26);
        transition: transform .18s ease, filter .18s ease;
    }
    .floating-annonce:hover { transform: translateY(-50%) translateX(4px); filter: brightness(1.06); color: #fff; }
    .floating-annonce i { font-size: 1.05rem; }
    .floating-annonce__dot {
        width: 8px; height: 8px; border-radius: 50%; background: #4ade80;
        box-shadow: 0 0 0 0 rgba(74, 222, 128, .7); animation: anPulse 1.8s infinite;
    }
    @keyframes anPulse {
        0%   { box-shadow: 0 0 0 0 rgba(74, 222, 128, .7); }
        70%  { box-shadow: 0 0 0 8px rgba(74, 222, 128, 0); }
        100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
    }
    @media (max-width: 576px) {
        .floating-annonce__label { display: none; }
        .floating-annonce { padding: 12px; border-radius: 0 50% 50% 0; }
    }
    @media (prefers-reduced-motion: reduce) {
        .floating-annonce, .floating-annonce__dot { transition: none; animation: none; }
    }
</style>
@endif
