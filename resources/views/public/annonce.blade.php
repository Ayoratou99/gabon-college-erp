@extends('layouts.public')

@section('title', 'Annonce — ' . $session->libelle)

@section('content')
<section class="container py-5">
    <div class="text-center mb-4">
        <span class="badge bg-success-subtle text-success-emphasis mb-2"><i class="fas fa-bullhorn me-1"></i>Annonce</span>
        <h1 class="h4 mb-0">{{ $session->libelle }}</h1>
    </div>

    <div class="mx-auto" style="max-width: 820px;">
        @if($session->flyerIsPdf())
            <div class="ratio border rounded shadow-sm" style="--bs-aspect-ratio: 130%;">
                <iframe src="{{ $session->flyerUrl() }}" title="Flyer d'annonce" style="border:0;"></iframe>
            </div>
        @else
            <img src="{{ $session->flyerUrl() }}" alt="Flyer d'annonce" class="img-fluid rounded shadow-sm d-block mx-auto">
        @endif

        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a href="{{ route('concours.inscription.form') }}" class="btn btn-primary">
                <i class="fas fa-paper-plane me-2"></i>Commencer mon inscription
            </a>
            <a href="{{ $session->flyerUrl() }}" download class="btn btn-outline-secondary">
                <i class="fas fa-download me-2"></i>Télécharger
            </a>
            <button type="button" class="btn btn-outline-primary" id="shareAnnonce"
                    data-url="{{ route('annonce') }}" data-title="{{ $session->libelle }}">
                <i class="fas fa-share-nodes me-2"></i>Partager
            </button>
        </div>
        <p class="text-center small text-success mt-3" id="shareFeedback" style="display:none;">
            <i class="fas fa-check me-1"></i>Lien copié dans le presse-papiers.
        </p>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.getElementById('shareAnnonce')?.addEventListener('click', async function () {
        const url = this.dataset.url;
        const title = this.dataset.title || document.title;
        if (navigator.share) {
            try { await navigator.share({ title, url }); return; } catch (e) { /* cancelled */ }
        }
        try {
            await navigator.clipboard.writeText(url);
            const fb = document.getElementById('shareFeedback');
            if (fb) { fb.style.display = 'block'; setTimeout(() => { fb.style.display = 'none'; }, 2500); }
        } catch (e) {
            window.prompt('Copiez ce lien :', url);
        }
    });
</script>
@endpush
