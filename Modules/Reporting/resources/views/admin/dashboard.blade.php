@extends('layouts.admin')

@section('title', 'Reporting')
@section('page-title', 'Tableaux de bord')

@section('content')
<div x-data="reportingDashboard()" x-init="load()">

    @if($session)
        <p class="text-muted small mb-3">Session active : <strong>{{ $session->libelle }}</strong></p>
    @endif

    {{-- KPI strip (server-rendered) --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['label' => 'Total dossiers',      'value' => $summary['total'],    'tone' => 'primary',   'icon' => 'fas fa-folder-open'],
                ['label' => 'En cours',            'value' => $summary['pending'],  'tone' => 'secondary', 'icon' => 'fas fa-hourglass-half'],
                ['label' => 'Acceptés',            'value' => $summary['accepted'], 'tone' => 'warning',   'icon' => 'fas fa-circle-check'],
                ['label' => 'Payés',               'value' => $summary['paid'],     'tone' => 'success',   'icon' => 'fas fa-money-bill-wave'],
                ['label' => 'Rejetés',             'value' => $summary['rejected'], 'tone' => 'danger',    'icon' => 'fas fa-circle-xmark'],
                ['label' => 'Admis',               'value' => $summary['admitted'], 'tone' => 'info',      'icon' => 'fas fa-trophy'],
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

    {{-- Revenue --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-label mb-2">Montant total encaissé</div>
                    <div class="kpi-value text-success">
                        {{ number_format($payments['paid_amount'], 0, ',', ' ') }} FCFA
                    </div>
                    <div class="text-muted small mt-1">
                        {{ $payments['paid_count'] }} paiement(s) confirmé(s) ·
                        {{ $payments['pending_count'] }} en attente
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts row 1 --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Candidats par centre</h2></div>
                <div class="card-body" style="height: 320px;"><canvas x-ref="chartCentre"></canvas></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Répartition par statut</h2></div>
                <div class="card-body" style="height: 320px;"><canvas x-ref="chartStatus"></canvas></div>
            </div>
        </div>
    </div>

    {{-- Charts row 2 --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Inscriptions sur les 30 derniers jours</h2>
                    <div class="btn-group btn-group-sm">
                        <button @click="setDays(7)"  :class="days===7  ? 'btn btn-primary' : 'btn btn-outline-secondary'">7j</button>
                        <button @click="setDays(30)" :class="days===30 ? 'btn btn-primary' : 'btn btn-outline-secondary'">30j</button>
                        <button @click="setDays(90)" :class="days===90 ? 'btn btn-primary' : 'btn btn-outline-secondary'">90j</button>
                    </div>
                </div>
                <div class="card-body" style="height: 280px;"><canvas x-ref="chartTimeline"></canvas></div>
            </div>
        </div>
    </div>

    {{-- Charts row 3 --}}
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Candidats par section (1er choix)</h2></div>
                <div class="card-body" style="height: 320px;"><canvas x-ref="chartSection"></canvas></div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Répartition par série du BAC</h2></div>
                <div class="card-body" style="height: 320px;"><canvas x-ref="chartSeries"></canvas></div>
            </div>
        </div>
    </div>

    {{-- Sex distribution --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Répartition par sexe</h2></div>
                <div class="card-body" style="height: 220px;"><canvas x-ref="chartSex"></canvas></div>
            </div>
        </div>
    </div>

    <div x-show="loading" class="text-center text-muted py-3 small">
        <i class="fas fa-spinner fa-spin me-2"></i>Chargement des données…
    </div>
</div>
@endsection
