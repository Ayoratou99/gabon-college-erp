{{-- Floating "Inscriptions ouvertes" card — flotte sur le bord droit (desktop)
     ou en bandeau bas (mobile) sur toutes les pages publiques tant que la
     session active accepte les inscriptions. Disparaît automatiquement :
       - quand la session est fermée (date_fermeture passée) — rien n'est rendu
       - quand on est déjà sur le tunnel d'inscription (redondant)
       - quand on scrolle jusqu'au bandeau CTA final (#cta-banner-final)
       - quand le visiteur clique sur la croix (mémorisé pour la session
         navigateur, ré-affiché à la prochaine visite ou pour un nouveau concours)
     Couleurs depuis les variables --cuk-* (pilotées par Parametrage). --}}
@php
    $fcSession = \Modules\Concours\Models\ConcoursSession::publicCurrent();
    $fcOpen    = $fcSession?->isInscriptionOpen() ?? false;

    $fcRoute   = (string) (request()->route()?->getName() ?? '');
    // Cache la carte sur tout le tunnel d'inscription (wizard) — on y est déjà.
    $fcOnInscription = str_contains($fcRoute, 'inscription');

    if ($fcOpen && ! $fcOnInscription && $fcSession !== null) {
        $fcYear  = $fcSession->anneeAcademique?->code ?? date('Y');
        // Fee is owned by the session (frais_inscription_override), no longer
        // by Parametrage — single source of truth.
        $fcFee   = (int) $fcSession->fraisInscription();
        $fcCurr  = $settings['concours.fee.currency'] ?? 'FCFA';

        // Compte à rebours : nombre de jours pleins jusqu'à la fermeture.
        $fcClose    = $fcSession->date_fermeture_inscriptions;
        $fcDaysLeft = $fcClose ? (int) now()->startOfDay()->diffInDays($fcClose->copy()->startOfDay(), false) : null;
        $fcShowCountdown = $fcDaysLeft !== null && $fcDaysLeft >= 0 && $fcDaysLeft <= 14;

        // Clé de dismissal liée au code de session → un NOUVEAU concours
        // ré-affiche la carte même si l'ancien avait été fermé par le visiteur.
        $fcDismissKey = 'cuk_fcta_dismissed_' . ($fcSession->code ?? 'session');
    }
@endphp

@if(($fcOpen ?? false) && ! ($fcOnInscription ?? true) && ($fcSession ?? null) !== null)
<style>
    .floating-cta {
        position: fixed;
        right: 18px;
        top: 50%;
        transform: translateY(-50%) translateX(0);
        width: 280px;
        z-index: 1045;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 18px 48px rgba(15, 23, 42, .22), 0 4px 12px rgba(15, 23, 42, .12);
        overflow: hidden;
        opacity: 1;
        transition: transform .45s cubic-bezier(.16,.84,.44,1), opacity .35s ease;
        border: 1px solid rgba(var(--cuk-primary-rgb, 29,78,216), .14);
    }
    .floating-cta[hidden] { display: none; }
    .floating-cta.is-out {
        transform: translateY(-50%) translateX(120%);
        opacity: 0;
        pointer-events: none;
    }
    .floating-cta__head {
        background: linear-gradient(135deg,
            var(--cuk-primary, #1d4ed8),
            color-mix(in srgb, var(--cuk-accent, #0ea5e9) 90%, var(--cuk-primary, #1d4ed8)));
        color: #fff;
        padding: 14px 16px 12px;
        position: relative;
    }
    .floating-cta__pulse {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: .68rem; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; opacity: .96;
    }
    .floating-cta__dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #4ade80; box-shadow: 0 0 0 0 rgba(74,222,128,.7);
        animation: fctaPulse 1.8s infinite;
    }
    @keyframes fctaPulse {
        0%   { box-shadow: 0 0 0 0 rgba(74,222,128,.7); }
        70%  { box-shadow: 0 0 0 8px rgba(74,222,128,0); }
        100% { box-shadow: 0 0 0 0 rgba(74,222,128,0); }
    }
    .floating-cta__title { font-size: 1.02rem; font-weight: 800; margin: 6px 0 0; line-height: 1.2; }
    .floating-cta__close {
        position: absolute; top: 8px; right: 8px;
        width: 26px; height: 26px; border: 0; border-radius: 50%;
        background: rgba(255,255,255,.18); color: #fff; cursor: pointer;
        font-size: .9rem; line-height: 1; display: grid; place-items: center;
        transition: background .2s;
    }
    .floating-cta__close:hover { background: rgba(255,255,255,.34); }
    .floating-cta__body { padding: 14px 16px 16px; }
    .floating-cta__row {
        display: flex; align-items: center; gap: 8px;
        font-size: .86rem; color: #334155; margin-bottom: 8px;
    }
    .floating-cta__row i { color: var(--cuk-primary, #1d4ed8); width: 16px; text-align: center; }
    .floating-cta__row strong { color: #0f172a; }
    .floating-cta__countdown {
        background: color-mix(in srgb, var(--cuk-accent, #0ea5e9) 14%, #fff);
        border: 1px solid color-mix(in srgb, var(--cuk-accent, #0ea5e9) 35%, #fff);
        border-radius: 10px; padding: 7px 10px; margin: 4px 0 12px;
        font-size: .82rem; color: #0f172a; text-align: center;
    }
    .floating-cta__countdown b { font-size: 1.05rem; color: var(--cuk-danger, #dc2626); }
    .floating-cta__btn {
        display: block; width: 100%; text-align: center;
        background: var(--cuk-primary, #1d4ed8); color: #fff !important;
        font-weight: 700; padding: 10px 12px; border-radius: 10px;
        text-decoration: none; transition: transform .15s, box-shadow .15s, filter .15s;
        box-shadow: 0 6px 16px rgba(var(--cuk-primary-rgb, 29,78,216), .35);
    }
    .floating-cta__btn:hover { transform: translateY(-1px); filter: brightness(1.05); color: #fff; }

    /* --- Mobile : bandeau fixé en bas, pleine largeur --- */
    @media (max-width: 768px) {
        .floating-cta {
            right: 0; left: 0; top: auto; bottom: 0;
            width: 100%; transform: none; border-radius: 16px 16px 0 0;
            display: flex; align-items: center; gap: 12px;
            padding-right: 6px;
        }
        .floating-cta.is-out { transform: translateY(120%); }
        .floating-cta__head { flex: 0 0 auto; padding: 10px 12px; border-radius: 0; }
        .floating-cta__title { display: none; }
        .floating-cta__body { flex: 1 1 auto; padding: 8px 10px; display: flex; align-items: center; gap: 12px; }
        .floating-cta__row, .floating-cta__countdown { display: none; }
        .floating-cta__btn { width: auto; padding: 9px 16px; white-space: nowrap; }
    }
    @media (prefers-reduced-motion: reduce) {
        .floating-cta, .floating-cta__dot { transition: none; animation: none; }
    }
</style>

<aside class="floating-cta" id="floatingCta" role="complementary"
       aria-label="Inscriptions ouvertes"
       data-dismiss-key="{{ $fcDismissKey }}">
    <div class="floating-cta__head">
        <button type="button" class="floating-cta__close" id="floatingCtaClose" aria-label="Fermer">&times;</button>
        <span class="floating-cta__pulse"><span class="floating-cta__dot"></span> Inscriptions ouvertes</span>
        <h3 class="floating-cta__title">Concours {{ $fcYear }}</h3>
    </div>
    <div class="floating-cta__body">
        <div class="floating-cta__row">
            <i class="fas fa-graduation-cap"></i>
            <span>Concours d'entrée&nbsp;<strong>{{ $fcYear }}</strong></span>
        </div>
        <div class="floating-cta__row">
            <i class="fas fa-coins"></i>
            <span>Frais&nbsp;: <strong>{{ number_format($fcFee, 0, ',', ' ') }} {{ $fcCurr }}</strong></span>
        </div>
        @if($fcShowCountdown)
            <div class="floating-cta__countdown">
                @if($fcDaysLeft === 0)
                    <i class="fas fa-hourglass-end"></i> Dernier jour pour vous inscrire&nbsp;!
                @else
                    Plus que <b>{{ $fcDaysLeft }}</b> jour{{ $fcDaysLeft > 1 ? 's' : '' }} avant la clôture
                @endif
            </div>
        @endif
        <a href="{{ route('concours.inscription.form') }}" class="floating-cta__btn">
            <i class="fas fa-paper-plane me-1"></i> Commencer mon inscription
        </a>
    </div>
</aside>

<script>
    (function () {
        var card = document.getElementById('floatingCta');
        if (!card) return;
        var key = card.getAttribute('data-dismiss-key');

        // Déjà fermée pour cette session navigateur ? on ne l'affiche pas.
        try {
            if (key && window.sessionStorage.getItem(key) === '1') {
                card.setAttribute('hidden', '');
                return;
            }
        } catch (e) { /* sessionStorage indisponible (mode privé strict) — on affiche quand même */ }

        // Croix de fermeture → mémorise + animation de sortie.
        var closeBtn = document.getElementById('floatingCtaClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                card.classList.add('is-out');
                try { if (key) window.sessionStorage.setItem(key, '1'); } catch (e) {}
                window.setTimeout(function () { card.setAttribute('hidden', ''); }, 500);
            });
        }

        // Auto-masquage quand le bandeau CTA final entre dans le viewport,
        // ré-affichage quand on remonte (évite le doublon visuel sans
        // perdre la carte définitivement).
        var finalBanner = document.getElementById('cta-banner-final');
        if (finalBanner && 'IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (card.hasAttribute('hidden')) return; // fermée manuellement
                    card.classList.toggle('is-out', en.isIntersecting);
                });
            }, { threshold: 0.15 });
            io.observe(finalBanner);
        }
    })();
</script>
@endif
