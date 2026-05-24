@extends('layouts.admin')

@section('title', 'Candidats')
@section('page-title', 'Candidats')

@section('page-actions')
    @php $exportParams = request()->only(['statut', 'centre_id', 'search']); @endphp
    <div class="btn-group">
        <a href="{{ route('admin.pages.concours.candidats.export', array_merge(['format' => 'xlsx'], $exportParams)) }}"
           class="btn btn-success btn-sm">
            <i class="far fa-file-excel me-2"></i>Excel
        </a>
        <a href="{{ route('admin.pages.concours.candidats.export', array_merge(['format' => 'csv'], $exportParams)) }}"
           class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-csv me-2"></i>CSV
        </a>
        <a href="{{ route('admin.pages.concours.candidats.export', array_merge(['format' => 'pdf'], $exportParams)) }}"
           class="btn btn-danger btn-sm">
            <i class="far fa-file-pdf me-2"></i>PDF
        </a>
    </div>
@endsection

@section('content')
    @if($session)
        <p class="text-muted small mb-3">Session <strong>{{ $session->libelle }}</strong></p>
    @endif

    <div class="card mb-3">
        <form method="GET" class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Recherche</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control" placeholder="Nom, prénom, matricule, email">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Centre</label>
                <select name="centre_id" class="form-select">
                    <option value="">— Tous les centres —</option>
                    @foreach($centres as $c)
                        <option value="{{ $c->id }}" @selected(($filters['centre_id'] ?? '') === $c->id)>{{ $c->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">— Tous statuts —</option>
                    @foreach($statuses as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['statut'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Filtrer</button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom &amp; prénom</th>
                        <th>Centre</th>
                        <th>Premier choix</th>
                        <th>Statut</th>
                        <th class="text-end">Reçu le</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($candidats as $c)
                        <tr>
                            <td><code>{{ $c->matricule_public }}</code></td>
                            <td>{{ $c->nom }} {{ $c->prenom }}</td>
                            <td>{{ $c->centre?->nom ?? '—' }}</td>
                            <td>{{ $c->premierChoix?->nom ?? '—' }}</td>
                            <td><span class="status-pill status-pill--{{ $c->statut }}">{{ $c->statut }}</span></td>
                            <td class="text-end text-muted small">{{ $c->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.pages.concours.candidats.show', $c) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Voir
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun candidat ne correspond.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">{{ $candidats->total() }} résultat(s)</small>
            {{ $candidats->onEachSide(1)->links() }}
        </div>
    </div>
@endsection
