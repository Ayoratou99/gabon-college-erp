@extends('layouts.admin')

@section('title', 'Saisie des notes')
@section('page-title', 'Saisie des notes')

@section('content')
    <p class="text-muted small mb-3">
        @if($session)Session <strong>{{ $session->libelle }}</strong> — @endif
        Sélectionnez une épreuve pour saisir ou modifier les notes.
    </p>

    {{-- Filtre par centre : ne garde que les matières effectivement planifiées
         dans ce centre, et pré-filtre la grille de saisie sur le même centre. --}}
    @if($centreOptions->count() > 1)
        <form method="GET" class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-end py-2">
                <span class="small text-muted mb-2"><i class="fas fa-filter me-1"></i>Filtrer</span>
                <div>
                    <label class="form-label small mb-1" for="filter-centre">Centre</label>
                    <select name="centre" id="filter-centre" class="form-select form-select-sm"
                            onchange="this.form.submit()">
                        <option value="">Tous les centres</option>
                        @foreach($centreOptions as $c)
                            <option value="{{ $c->id }}" @selected($filterCentre === (string) $c->id)>{{ $c->nom }}</option>
                        @endforeach
                    </select>
                </div>
                @if($filterCentre !== '')
                    <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary mb-0">
                        <i class="fas fa-times me-1"></i>Réinitialiser
                    </a>
                @endif
            </div>
        </form>
    @endif

    <div class="row g-3">
        @forelse($epreuves as $e)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('admin.pages.concours.notes.grid', array_filter(['epreuve' => $e->id, 'centre' => $filterCentre])) }}"
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
                    @if($filterCentre !== '')
                        Aucune épreuve n'est planifiée dans ce centre — vérifiez l'<strong>emploi du temps</strong>
                        ou retirez le filtre.
                    @else
                        Aucune épreuve définie pour cette session — créez-en depuis la page <strong>Épreuves</strong>.
                    @endif
                </div>
            </div>
        @endforelse
    </div>
@endsection
