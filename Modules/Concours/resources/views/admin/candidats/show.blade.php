@extends('layouts.admin')

@section('title', $candidat->prenom . ' ' . $candidat->nom)
@section('page-title', $candidat->prenom . ' ' . $candidat->nom)

@section('page-actions')
    <a href="{{ route('admin.pages.concours.candidats.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i> Liste
    </a>
@endsection

@section('content')
<div x-data="{
        decision: null,
        motifs: [''],
        loading: false,
        message: '',
        async submit() {
            this.loading = true;
            this.message = '';
            try {
                const payload = { decision: this.decision };
                if (this.decision === 'reject') {
                    payload.motifs = this.motifs.filter(m => m.trim().length > 0);
                    if (payload.motifs.length === 0) {
                        this.message = 'Précisez au moins un motif.';
                        this.loading = false; return;
                    }
                }
                await window.axios.post('/api/admin/concours/candidats/{{ $candidat->id }}/decide', payload);
                window.location.reload();
            } catch (e) {
                this.message = e.response?.data?.message ?? 'Erreur lors de la décision.';
            } finally { this.loading = false; }
        },
    }">

    <div class="row g-3">
        <div class="col-lg-8">

            {{-- Identité --}}
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between">
                    <h2 class="h5 mb-0">Identité</h2>
                    <span class="status-pill status-pill--{{ $candidat->statut }}">{{ $candidat->statut }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Matricule</dt><dd class="col-sm-8"><code>{{ $candidat->matricule_public }}</code></dd>
                        <dt class="col-sm-4">Nom &amp; prénom</dt><dd class="col-sm-8">{{ $candidat->nom }} {{ $candidat->prenom }}</dd>
                        <dt class="col-sm-4">Date / Lieu naissance</dt>
                        <dd class="col-sm-8">{{ $candidat->date_naissance?->format('d/m/Y') }} — {{ $candidat->lieu_naissance }}</dd>
                        <dt class="col-sm-4">Sexe / Nationalité</dt>
                        <dd class="col-sm-8">{{ $candidat->sexe }} — {{ $candidat->nationalite?->nom }}</dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $candidat->email }}</dd>
                        <dt class="col-sm-4">Téléphone</dt><dd class="col-sm-8">{{ $candidat->telephone }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Baccalauréat --}}
            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Baccalauréat</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Déjà BAC ?</dt>
                        <dd class="col-sm-8">{{ $candidat->deja_bac ? 'Oui ('.$candidat->annee_bac.')' : 'Non' }}</dd>
                        <dt class="col-sm-4">Série</dt>
                        <dd class="col-sm-8">{{ $candidat->serieBac?->nom }} {{ $candidat->bac_libelle_libre ? '— '.$candidat->bac_libelle_libre : '' }}</dd>
                        <dt class="col-sm-4">Établissement</dt>
                        <dd class="col-sm-8">{{ $candidat->etablissement_frequente }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Choix --}}
            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Choix de formation &amp; centre</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Premier choix</dt><dd class="col-sm-8">{{ $candidat->premierChoix?->nom }}</dd>
                        <dt class="col-sm-4">Second choix</dt><dd class="col-sm-8">{{ $candidat->secondChoix?->nom ?? '—' }}</dd>
                        <dt class="col-sm-4">Centre d'examen</dt><dd class="col-sm-8">{{ $candidat->centre?->nom }} — {{ $candidat->centre?->ville }}</dd>
                        @if($candidat->sectionOrientation)
                            <dt class="col-sm-4">Orientation</dt>
                            <dd class="col-sm-8"><span class="badge bg-primary">{{ $candidat->sectionOrientation->nom }}</span></dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Documents --}}
            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Documents</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse($candidat->documents as $d)
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="far fa-file me-2"></i>{{ $d->documentRequis?->libelle }}</span>
                            <span class="text-muted small">{{ strtoupper(pathinfo($d->file_path, PATHINFO_EXTENSION)) }}
                                — {{ round($d->size_bytes / 1024) }} Ko</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucun document.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Motifs de rejet --}}
            @if($candidat->motifsRejet->isNotEmpty())
                <div class="card mb-3 border-danger">
                    <div class="card-header bg-white"><h2 class="h5 mb-0 text-danger">Motifs de rejet</h2></div>
                    <ul class="list-group list-group-flush">
                        @foreach($candidat->motifsRejet as $m)
                            <li class="list-group-item small">
                                {{ $m->motif }}
                                <span class="text-muted">— {{ $m->decidedBy?->nom }} {{ $m->decidedBy?->prenom }}, {{ $m->decided_at->format('d/m/Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            @if($canValidate && in_array($candidat->statut, ['non', 'rejete'], true))
                <div class="card mb-3">
                    <div class="card-header bg-white"><h2 class="h5 mb-0">Décision</h2></div>
                    <div class="card-body">
                        <div class="d-grid gap-2 mb-3">
                            <button @click="decision = 'accept'" class="btn btn-success" :class="{ active: decision === 'accept' }">
                                <i class="fas fa-check me-2"></i>Accepter le dossier
                            </button>
                            <button @click="decision = 'reject'" class="btn btn-outline-danger" :class="{ active: decision === 'reject' }">
                                <i class="fas fa-times me-2"></i>Rejeter le dossier
                            </button>
                        </div>

                        <template x-if="decision === 'reject'">
                            <div>
                                <label class="form-label small">Motifs</label>
                                <template x-for="(m, i) in motifs" :key="i">
                                    <div class="input-group mb-2">
                                        <input type="text" x-model="motifs[i]" class="form-control form-control-sm" placeholder="Motif…">
                                        <button @click="motifs.splice(i, 1)" type="button" class="btn btn-outline-secondary btn-sm"><i class="fas fa-trash"></i></button>
                                    </div>
                                </template>
                                <button @click="motifs.push('')" type="button" class="btn btn-link btn-sm p-0">
                                    + Ajouter un motif
                                </button>
                            </div>
                        </template>

                        <template x-if="decision">
                            <button @click="submit()" :disabled="loading" class="btn btn-primary w-100 mt-3">
                                <span x-show="!loading"><i class="fas fa-paper-plane me-2"></i>Confirmer</span>
                                <span x-show="loading"><i class="fas fa-spinner fa-spin me-2"></i>Envoi…</span>
                            </button>
                        </template>

                        <p x-show="message" x-text="message" class="text-danger small mt-2 mb-0"></p>
                    </div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Paiements</h2></div>
                <ul class="list-group list-group-flush small">
                    @forelse($candidat->payments as $p)
                        <li class="list-group-item">
                            <strong>{{ number_format($p->amount, 0, ',', ' ') }} {{ $p->currency }}</strong>
                            — <span class="badge bg-{{ $p->status === 'PAID' ? 'success' : 'secondary' }}">{{ $p->status }}</span><br>
                            <code>{{ $p->external_reference }}</code><br>
                            <span class="text-muted">{{ $p->created_at->format('d/m/Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucun paiement.</li>
                    @endforelse
                </ul>
            </div>

            @if($candidat->modifications->isNotEmpty())
                <div class="card">
                    <div class="card-header bg-white"><h2 class="h5 mb-0">Historique</h2></div>
                    <ul class="list-group list-group-flush small">
                        @foreach($candidat->modifications as $mod)
                            <li class="list-group-item">
                                <span class="badge bg-light text-dark">{{ $mod->channel }}</span>
                                <strong>{{ $mod->field }}</strong> : {{ $mod->old_value ?? '—' }} → {{ $mod->new_value }}
                                <div class="text-muted">{{ $mod->changed_at->format('d/m/Y H:i') }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
