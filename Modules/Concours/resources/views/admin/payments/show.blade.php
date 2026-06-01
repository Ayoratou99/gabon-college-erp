@extends('layouts.admin')

@section('title', 'Paiement — ' . substr($payment->id, 0, 8))
@section('page-title', 'Détail du paiement')

@section('page-actions')
    <a href="{{ route('admin.pages.concours.payments.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i> Liste des paiements
    </a>
@endsection

@section('content')
    <div class="row g-3">

        {{-- Left column: payment + candidat summary --}}
        <div class="col-lg-8">

            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Transaction</h2>
                    <span class="badge bg-{{ $statusColor }} fs-6">{{ $payment->status }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">ID interne</dt>
                        <dd class="col-sm-8"><code class="small">{{ $payment->id }}</code></dd>

                        <dt class="col-sm-4">Initié le</dt>
                        <dd class="col-sm-8">{{ $payment->created_at?->format('d/m/Y H:i:s') }}</dd>

                        <dt class="col-sm-4">Payé le</dt>
                        <dd class="col-sm-8">
                            @if($payment->paid_at)
                                {{ $payment->paid_at->format('d/m/Y H:i:s') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Montant</dt>
                        <dd class="col-sm-8">
                            <strong>{{ number_format((int) $payment->amount, 0, ',', ' ') }}</strong>
                            {{ $payment->currency }}
                        </dd>

                        <dt class="col-sm-4">Session</dt>
                        <dd class="col-sm-8">
                            @if($payment->session)
                                {{ $payment->session->code }} — {{ $payment->session->libelle }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">eBilling bill_id</dt>
                        <dd class="col-sm-8">
                            @if($payment->ebilling_id)
                                <code>{{ $payment->ebilling_id }}</code>
                            @else
                                <span class="text-muted">non encore émis</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Référence externe</dt>
                        <dd class="col-sm-8">
                            <code class="small d-inline-block text-break" style="max-width:100%;">
                                {{ $payment->external_reference }}
                            </code>
                            <div class="small text-muted mt-1">
                                Chiffré (AES-256-GCM) — la même clé décode le callback eBilling.
                            </div>
                        </dd>

                        <dt class="col-sm-4">Référence vérifiée ?</dt>
                        <dd class="col-sm-8">
                            @if($payment->signature_verified)
                                <span class="badge bg-success">Oui</span>
                            @else
                                <span class="badge bg-secondary">Pas encore</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">IP du callback</dt>
                        <dd class="col-sm-8">
                            @if($payment->callback_ip)
                                <code>{{ $payment->callback_ip }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            {{-- Raw eBilling payload — always rendered so the audit block stays
                 discoverable even when empty. Legacy-imported payments and
                 transactions that haven't received a callback yet carry no
                 payload, and silently hiding the card made it look missing. --}}
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Charge utile eBilling (callback)</h2>
                    <p class="small text-muted mb-0">Dernier corps de requête reçu, brut, pour audit.</p>
                </div>
                @if($payment->payload)
                    <div class="card-body p-0">
<pre class="small mb-0 p-3 bg-light" style="max-height: 360px; overflow:auto;">{{ json_encode($payment->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @else
                    <div class="card-body">
                        <p class="text-muted mb-0">
                            <i class="fas fa-circle-info me-1"></i>
                            Aucune charge utile enregistrée — paiement importé du système hérité,
                            ou callback eBilling pas encore reçu pour cette transaction.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right column: linked candidat --}}
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Candidat lié</h2>
                </div>
                <div class="card-body">
                    @if($payment->candidat)
                        @php $c = $payment->candidat; @endphp
                        <dl class="row mb-3">
                            <dt class="col-sm-5">Matricule</dt>
                            <dd class="col-sm-7"><code>{{ $c->matricule_public }}</code></dd>

                            <dt class="col-sm-5">Nom &amp; prénom</dt>
                            <dd class="col-sm-7">{{ $c->nom }} {{ $c->prenom }}</dd>

                            <dt class="col-sm-5">Email</dt>
                            <dd class="col-sm-7"><span class="text-break">{{ $c->email }}</span></dd>

                            <dt class="col-sm-5">Téléphone</dt>
                            <dd class="col-sm-7">{{ $c->telephone }}</dd>

                            <dt class="col-sm-5">Centre</dt>
                            <dd class="col-sm-7">{{ $c->centre?->nom ?? '—' }}</dd>

                            <dt class="col-sm-5">Session</dt>
                            <dd class="col-sm-7">{{ $c->session?->code ?? '—' }}</dd>

                            <dt class="col-sm-5">Statut dossier</dt>
                            <dd class="col-sm-7">
                                <span class="status-pill status-pill--{{ $c->statut }}">{{ $c->statutLabel() }}</span>
                            </dd>
                        </dl>

                        <a href="{{ route('admin.pages.concours.candidats.show', $c->id) }}"
                           class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-folder-open me-2"></i>Ouvrir le dossier complet
                        </a>
                    @else
                        <p class="text-muted mb-0">Aucun candidat lié — la ligne a peut-être été supprimée.</p>
                    @endif
                </div>
            </div>

            <div class="card border-warning">
                <div class="card-body small">
                    <strong>
                        <i class="fas fa-lock me-1 text-warning"></i>
                        Lecture seule
                    </strong>
                    <p class="text-muted mb-0 mt-2">
                        Les paiements ne se modifient pas depuis le back-office : ils suivent
                        l'état renvoyé par eBilling via le callback chiffré. Pour un
                        remboursement, contactez l'opérateur eBilling.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
