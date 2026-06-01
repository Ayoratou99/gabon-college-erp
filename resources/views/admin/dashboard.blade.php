@extends('layouts.admin')

@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')

    {{-- Session band — selector + meta + quick actions --}}
    @if($session)
        <div class="session-band mb-4">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="session-band__badge">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="me-auto">
                    <div class="small text-uppercase fw-bold opacity-75" style="letter-spacing:.12em;">
                        Session affichée
                        @if($session->est_active)
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">active</span>
                        @else
                            <span class="badge bg-light text-dark ms-1">archive</span>
                        @endif
                    </div>
                    <div class="fs-5 fw-bold">{{ $session->libelle ?? $session->code }}</div>
                    <div class="small opacity-75">
                        Année {{ $session->anneeAcademique?->code ?? '—' }}
                        @if($session->date_concours) · épreuve du {{ $session->date_concours->format('d/m/Y') }}@endif
                        · {{ number_format($session->candidats_count ?? 0, 0, ',', ' ') }} candidats
                    </div>
                </div>
                @if($allSessions && $allSessions->count() > 1)
                    @if($canSwitchSession)
                        {{-- Editors: picking a session ACTIVATES it globally — same
                             effect as « Sélectionner » on the Sessions page. The
                             dashboard then resolves to ConcoursSession::active(). --}}
                        <form method="POST" action="{{ route('admin.pages.concours.sessions.switch') }}" class="d-inline-block">
                            @csrf
                            <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 260px;">
                                @foreach($allSessions as $s)
                                    <option value="{{ $s->id }}" @selected($s->id === $session->id)>
                                        {{ $s->libelle ?? $s->code }} ({{ $s->candidats_count }} cand.)
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        {{-- Viewers (chef-centre): display-only filter — never flips
                             the global active pointer, just re-renders for ?session=CODE. --}}
                        <form method="GET" class="d-inline-block">
                            <select name="session" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 260px;">
                                @foreach($allSessions as $s)
                                    <option value="{{ $s->code }}" @selected($s->id === $session->id)>
                                        {{ $s->libelle ?? $s->code }} ({{ $s->candidats_count }} cand.)
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                @endif
                <a href="{{ route('admin.pages.concours.sessions.index') }}" class="btn btn-light btn-sm fw-semibold">
                    <i class="fas fa-list me-2"></i>Sessions
                </a>
            </div>
        </div>
    @endif

    {{-- KPI band --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['label' => 'Dossiers',           'value' => $kpis['total'],    'icon' => 'fas fa-folder-open',     'tone' => 'primary'],
                ['label' => 'En cours',           'value' => $kpis['en_cours'], 'icon' => 'fas fa-hourglass-half',  'tone' => 'secondary'],
                ['label' => 'Acceptés',           'value' => $kpis['oui'],      'icon' => 'fas fa-circle-check',    'tone' => 'warning'],
                ['label' => 'Validés (payés)',    'value' => $kpis['valid'],    'icon' => 'fas fa-money-bill-wave', 'tone' => 'success'],
                ['label' => 'Rejetés',            'value' => $kpis['rejete'],   'icon' => 'fas fa-circle-xmark',    'tone' => 'danger'],
                ['label' => 'Admis',              'value' => $kpis['admis'],    'icon' => 'fas fa-trophy',          'tone' => 'info'],
            ];
        @endphp
        @foreach($cards as $c)
            <div class="col-md-4 col-xl-2">
                <div class="kpi-tile kpi-tile--{{ $c['tone'] }}">
                    <div class="kpi-tile__icon"><i class="{{ $c['icon'] }}"></i></div>
                    <div class="kpi-tile__value">{{ number_format($c['value'], 0, ',', ' ') }}</div>
                    <div class="kpi-tile__label">{{ $c['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Financials + conversion rates --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card kpi-finance h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="text-muted text-uppercase small fw-bold" style="letter-spacing:.12em;">
                                Encaissements (session active)
                            </div>
                            <div class="fs-1 fw-bold text-success mt-1">
                                {{ number_format($kpis['paid_amount'], 0, ',', ' ') }} <span class="fs-5 text-muted">FCFA</span>
                            </div>
                            <div class="small text-muted mt-1">{{ $kpis['paid_count'] }} paiements confirmés</div>
                        </div>
                        <div class="kpi-finance__icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-md-6">
                            <div class="conv-ring conv-ring--accept" style="--p: {{ $kpis['acceptance_rate'] }}">
                                <span>{{ $kpis['acceptance_rate'] }}%</span>
                            </div>
                            <div class="small fw-semibold mt-2">Taux d'acceptation</div>
                            <div class="small text-muted">dossiers acceptés / total reçu</div>
                        </div>
                        <div class="col-md-6">
                            <div class="conv-ring conv-ring--pay" style="--p: {{ $kpis['payment_rate'] }}">
                                <span>{{ $kpis['payment_rate'] }}%</span>
                            </div>
                            <div class="small fw-semibold mt-2">Taux de paiement</div>
                            <div class="small text-muted">payés / acceptés (oui + valid)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Inscriptions sur 14 jours</h2>
                </div>
                <div class="card-body">
                    <canvas id="timelineChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0"><i class="fas fa-chart-pie text-primary me-2"></i>Répartition par statut</h2>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Centre × statut stacked bar chart --}}
    @if(! empty($centreNames))
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0"><i class="fas fa-chart-bar text-primary me-2"></i>Répartition par centre × statut</h2>
            </div>
            <div class="card-body">
                <canvas id="centreStatusChart" height="80"></canvas>
            </div>
        </div>
    @endif

    {{-- Top centres + recent dossiers --}}
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0"><i class="fas fa-location-dot text-primary me-2"></i>Centres les plus actifs</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Centre</th>
                                <th class="text-end">Dossiers</th>
                                <th class="text-end">Payés</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topCentres as $c)
                                <tr>
                                    <td class="fw-semibold">{{ $c->centre }}</td>
                                    <td class="text-end">{{ number_format($c->n, 0, ',', ' ') }}</td>
                                    <td class="text-end">
                                        <span class="badge bg-success-subtle text-success-emphasis">
                                            {{ number_format($c->valides, 0, ',', ' ') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Pas de données.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i>Dossiers récents</h2>
                    <a href="{{ route('admin.pages.concours.candidats.index') }}" class="btn btn-sm btn-outline-primary">
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
                                <th>Statut</th>
                                <th class="text-end">Reçu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recent as $c)
                                <tr>
                                    <td><code>{{ $c->matricule_public }}</code></td>
                                    <td>{{ $c->nom }} {{ $c->prenom }}</td>
                                    <td>{{ $c->centre?->nom ?? '—' }}</td>
                                    <td><span class="status-pill status-pill--{{ $c->statut }}">{{ $c->statutLabel() }}</span></td>
                                    <td class="text-muted small text-end">{{ $c->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Aucun dossier pour la session active.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script>
// window.Chart is registered globally in resources/js/app.js — no module
// import here, the view just consumes the already-bundled library.
document.addEventListener('DOMContentLoaded', () => {
    const Chart = window.Chart;
    if (!Chart) { return; }

    const css = getComputedStyle(document.documentElement);
    const primary = css.getPropertyValue('--cuk-primary').trim() || '#1d4ed8';
    const accent  = css.getPropertyValue('--cuk-accent').trim()  || '#0ea5e9';
    const success = css.getPropertyValue('--cuk-success').trim() || '#16a34a';
    const danger  = css.getPropertyValue('--cuk-danger').trim()  || '#dc2626';

    // 14-day registration timeline
    const tlCtx = document.getElementById('timelineChart');
    if (tlCtx) {
        new Chart(tlCtx, {
            type: 'line',
            data: {
                labels: @json(array_column($timeline, 'label')),
                datasets: [{
                    label: 'Inscriptions',
                    data:  @json(array_column($timeline, 'value')),
                    borderColor: primary,
                    backgroundColor: primary + '22',
                    fill: true,
                    tension: .35,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: primary,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    // Centre × statut stacked bar
    const csCtx = document.getElementById('centreStatusChart');
    if (csCtx) {
        new Chart(csCtx, {
            type: 'bar',
            data: {
                labels: @json($centreNames),
                datasets: [
                    { label: 'En cours', data: @json($centreXStatus['non']    ?? []), backgroundColor: '#94a3b8', stack: 's' },
                    { label: 'Acceptés', data: @json($centreXStatus['oui']    ?? []), backgroundColor: '#f59e0b', stack: 's' },
                    { label: 'Validés',  data: @json($centreXStatus['valid']  ?? []), backgroundColor: success,   stack: 's' },
                    { label: 'Rejetés',  data: @json($centreXStatus['rejete'] ?? []), backgroundColor: danger,    stack: 's' },
                    { label: 'Admis',    data: @json($centreXStatus['admis']  ?? []), backgroundColor: primary,   stack: 's' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend:  { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
                    tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                },
            },
        });
    }

    // Status donut
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['En cours', 'Accepté', 'Validé', 'Rejeté', 'Admis'],
                datasets: [{
                    data: [
                        {{ $kpis['en_cours'] }},
                        {{ $kpis['oui'] }},
                        {{ $kpis['valid'] }},
                        {{ $kpis['rejete'] }},
                        {{ $kpis['admis'] }},
                    ],
                    backgroundColor: ['#94a3b8', '#f59e0b', success, danger, primary],
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
                },
            },
        });
    }
});
</script>
@endpush

@endsection
