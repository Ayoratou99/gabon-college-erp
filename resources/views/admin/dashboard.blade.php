@extends('layouts.admin')

@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')
    @if($session)
        <p class="text-muted mb-4">
            Vue d'ensemble de la session
            <strong>{{ $session->libelle }}</strong>
            ({{ $session->code }})
        </p>
    @endif

    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['label' => 'Dossiers',           'value' => $kpis['total'],   'icon' => 'fas fa-folder-open',     'tone' => 'primary'],
                ['label' => 'En cours',           'value' => $kpis['en_cours'],'icon' => 'fas fa-hourglass-half',  'tone' => 'secondary'],
                ['label' => 'Acceptés (non payé)','value' => $kpis['oui'],     'icon' => 'fas fa-circle-check',    'tone' => 'warning'],
                ['label' => 'Payés',              'value' => $kpis['valid'],   'icon' => 'fas fa-money-bill-wave', 'tone' => 'success'],
                ['label' => 'Rejetés',            'value' => $kpis['rejete'],  'icon' => 'fas fa-circle-xmark',    'tone' => 'danger'],
                ['label' => 'Admis',              'value' => $kpis['admis'],   'icon' => 'fas fa-trophy',          'tone' => 'info'],
            ];
        @endphp

        @foreach($cards as $c)
            <div class="col-md-4 col-xl-2">
                <div class="card kpi-card border-start border-{{ $c['tone'] }} border-3 h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="{{ $c['icon'] }} fs-2 text-{{ $c['tone'] }} me-3"></i>
                        <div>
                            <div class="kpi-value">{{ number_format($c['value'], 0, ',', ' ') }}</div>
                            <div class="kpi-label">{{ $c['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-label mb-2">Montant payé (session active)</div>
                    <div class="kpi-value text-success">
                        {{ number_format($kpis['paid_amount'], 0, ',', ' ') }} FCFA
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Dossiers récents</h2>
            <a href="{{ route('admin.concours.candidats.index') }}" class="btn btn-sm btn-outline-primary">
                Voir tous
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom &amp; prénom</th>
                        <th>Centre</th>
                        <th>Premier choix</th>
                        <th>Statut</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recent as $c)
                        <tr>
                            <td><code>{{ $c->matricule_public }}</code></td>
                            <td>{{ $c->nom }} {{ $c->prenom }}</td>
                            <td>{{ $c->centre?->nom ?? '—' }}</td>
                            <td>{{ $c->premierChoix?->nom ?? '—' }}</td>
                            <td><span class="status-pill status-pill--{{ $c->statut }}">{{ $c->statut }}</span></td>
                            <td class="text-muted small">{{ $c->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun dossier pour la session active.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
