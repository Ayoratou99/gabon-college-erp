@extends('layouts.admin')

@section('title', 'Saisie des notes')
@section('page-title', 'Saisie des notes')

@section('content')
    <p class="text-muted small mb-3">
        @if($session)Session <strong>{{ $session->libelle }}</strong> — @endif
        Sélectionnez une épreuve pour saisir ou modifier les notes.
    </p>

    <div class="row g-3">
        @forelse($epreuves as $e)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('admin.pages.concours.notes.grid', $e) }}"
                   class="card kpi-card text-decoration-none h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-light text-dark">{{ $e->code }}</span>
                            <span class="text-muted small">{{ $e->typeEpreuve?->libelle }}</span>
                        </div>
                        <h2 class="h5 mb-2 text-dark">{{ $e->libelle }}</h2>
                        <div class="text-muted small">
                            Coefficient <strong>{{ $e->coefficient }}</strong>
                            &middot; {{ $e->duree_minutes }} min
                            &middot; sur {{ $e->note_max }}
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">
                    Aucune épreuve définie pour cette session — créez-en depuis la page <strong>Épreuves</strong>.
                </div>
            </div>
        @endforelse
    </div>
@endsection
