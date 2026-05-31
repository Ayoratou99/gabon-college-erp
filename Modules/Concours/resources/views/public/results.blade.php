@extends('layouts.public')
@section('title', 'Résultats du concours')

@section('content')
<section class="page-hero">
    <div class="container">
        <h1><i class="fas fa-trophy me-2"></i> Résultats du concours d'entrée</h1>
        <p>
            Liste officielle des admis. Consultez les sessions précédentes via le sélecteur,
            ou téléchargez le procès-verbal au format PDF.
        </p>
    </div>
</section>

<section class="container py-5" style="max-width:1100px">

    @if ($sessions->isEmpty())
        <div class="alert alert-info">
            Aucun résultat n'a encore été publié sur cette plateforme.
            Revenez après la date de publication annoncée par le Centre Universitaire de Koulamoutou.
        </div>
    @else
        {{-- Session selector + download --}}
        <form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-6">
                <label class="form-label small mb-1">Session</label>
                <select name="session" class="form-select form-select-lg" onchange="this.form.submit()">
                    @foreach ($sessions as $s)
                        <option value="{{ $s->code }}" @selected($session?->id === $s->id)>
                            {{ $s->libelle ?? $s->code }}
                            @if($s->date_concours) — épreuve du {{ $s->date_concours->format('d/m/Y') }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($publication?->fichier_path)
                <div class="col-md-6 d-flex justify-content-md-end">
                    <a href="{{ route('concours.public.results.download', ['sessionCode' => $session->code]) }}"
                       class="btn btn-danger btn-lg">
                        <i class="fas fa-file-pdf me-2"></i>Télécharger le PV officiel
                    </a>
                </div>
            @endif
        </form>

        @if ($publication === null)
            <div class="alert alert-info">
                Les résultats de cette session ne sont pas encore publiés.
            </div>
        @else
            {{-- Publication summary --}}
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="stat-tile">
                        <span class="stat-tile-value">{{ number_format($publication->total_candidats ?? 0, 0, ',', ' ') }}</span>
                        <span class="stat-tile-label">Candidats</span>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="stat-tile stat-tile--success">
                        <span class="stat-tile-value">{{ number_format($publication->total_admis ?? 0, 0, ',', ' ') }}</span>
                        <span class="stat-tile-label">Admis</span>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="stat-tile stat-tile--accent">
                        <span class="stat-tile-value">
                            {{ $publication->total_candidats ? round(100 * $publication->total_admis / $publication->total_candidats) : 0 }}%
                        </span>
                        <span class="stat-tile-label">Taux de réussite</span>
                    </div>
                </div>
            </div>

            <p class="text-muted small">
                <i class="far fa-calendar me-1"></i>
                Publié le {{ $publication->published_at?->format('d/m/Y \à H:i') }}
            </p>

            @if ($publication->communique)
                <div class="alert alert-light border">
                    {!! nl2br(e($publication->communique)) !!}
                </div>
            @endif

            {{-- Admis list grouped by section --}}
            @if ($admis->isNotEmpty())
                @php
                    $bySection = $admis->groupBy(fn ($c) => $c->sectionOrientation?->nom ?? 'Sans section');
                @endphp

                @foreach ($bySection as $sectionName => $items)
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-graduation-cap me-2 text-primary"></i>{{ $sectionName }}</h5>
                            <span class="badge bg-success-subtle text-success-emphasis">
                                {{ $items->count() }} admis
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:80px">Rang</th>
                                        <th>Matricule</th>
                                        <th>Nom &amp; prénom</th>
                                        <th class="text-end">Moyenne</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $c)
                                        <tr>
                                            <td>
                                                @if($c->rang)
                                                    <span class="badge rounded-pill bg-primary">{{ $c->rang }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td><code>{{ $c->matricule_public }}</code></td>
                                            <td class="fw-semibold">{{ $c->nom }} {{ $c->prenom }}</td>
                                            <td class="text-end fw-semibold">
                                                {{ number_format((float) $c->moyenne, 2, ',', ' ') }}/20
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        @endif
    @endif

</section>
@endsection
