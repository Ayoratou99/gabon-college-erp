@extends('layouts.admin')

@section('title', $candidat->prenom . ' ' . $candidat->nom)
@section('page-title', $candidat->prenom . ' ' . $candidat->nom)

@section('page-actions')
    @if(! empty($canEdit) && ! empty($sessionActive))
        <a href="{{ route('admin.pages.concours.candidats.edit', $candidat->id) }}"
           class="btn btn-primary btn-sm me-2">
            <i class="fas fa-pen-to-square me-2"></i>Modifier
        </a>
    @endif
    {{-- Fiche d'inscription PDF + Emploi du temps — always available, even
         for archived sessions, so the back-office can reprint at any time.
         These buttons live in @section('page-actions') (rendered in the admin
         layout header, OUTSIDE the main content's x-data scope) so each needs
         its own `x-data="{}"` for Alpine to bind @click. We dispatch on window
         so the doc-preview modal's `@preview-doc.window` listener picks it up. --}}
    @php
        $ficheUrl  = route('admin.concours.candidats.pdf', ['candidat' => $candidat->id, 'document' => 'fiche']);
        $emploiUrl = route('admin.concours.candidats.pdf', ['candidat' => $candidat->id, 'document' => 'emploi-du-temps']);
        $ficheName = 'fiche-' . strtolower($candidat->matricule_public) . '.pdf';
        $emploiName = 'emploi-' . strtolower($candidat->matricule_public) . '.pdf';
    @endphp
    <button type="button" class="btn btn-outline-secondary btn-sm me-2"
            x-data
            @click="window.dispatchEvent(new CustomEvent('preview-doc', { detail: { doc: {
                label: @js('Fiche d\'inscription'),
                originalName: @js($ficheName),
                mime: 'application/pdf',
                isAdminPdf: true,
                pdfUrl: @js($ficheUrl),
            }}}))">
        <i class="far fa-file-pdf me-2"></i>Fiche PDF
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm me-2"
            x-data
            @click="window.dispatchEvent(new CustomEvent('preview-doc', { detail: { doc: {
                label: 'Emploi du temps',
                originalName: @js($emploiName),
                mime: 'application/pdf',
                isAdminPdf: true,
                pdfUrl: @js($emploiUrl),
            }}}))">
        <i class="far fa-calendar-alt me-2"></i>Emploi du temps
    </button>
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

    @if(empty($sessionActive))
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-lock fa-lg"></i>
            <div>
                <strong>Session clôturée.</strong>
                Les inscriptions de la session
                <em>{{ $candidat->session?->libelle ?? $candidat->session?->code ?? 'concours' }}</em>
                sont terminées : le dossier reste consultable mais aucune décision (acceptation, rejet, revue de pièce, remplacement) n'est plus possible.
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">

            {{-- Identité --}}
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between">
                    <h2 class="h5 mb-0">Identité</h2>
                    <span class="status-pill status-pill--{{ $candidat->statut }}">{{ $candidat->statut }}</span>
                </div>
                <div class="card-body">
                    {{-- The photo route probes the legacy_photos disk for candidats
                         with a legacy_id, so we render the <img> even when photo_path
                         is null — the endpoint will 404 if no file is found, and the
                         onerror handler swaps the broken image for the placeholder. --}}
                    @php $mayHavePhoto = $candidat->photo_path || $candidat->legacy_id; @endphp
                    <div class="row g-3">
                        @if($mayHavePhoto)
                            <div class="col-md-3 text-center">
                                <img src="{{ route('admin.concours.candidats.photo', $candidat->id) }}"
                                     alt="Photo de {{ $candidat->prenom }} {{ $candidat->nom }}"
                                     class="img-fluid rounded border"
                                     style="max-height: 180px; object-fit: cover;"
                                     onerror="this.onerror=null; this.outerHTML='<div class=&quot;d-flex flex-column align-items-center justify-content-center border rounded bg-light text-muted&quot; style=&quot;min-height:180px;&quot;><i class=&quot;far fa-user fa-3x mb-2&quot;></i><div class=&quot;small&quot;>Photo introuvable</div></div>';">
                                <div class="small text-muted mt-1">
                                    <i class="far fa-id-badge me-1"></i>Photo d'identité
                                </div>
                            </div>
                            <div class="col-md-9">
                        @else
                            <div class="col-md-3 text-center">
                                <div class="d-flex flex-column align-items-center justify-content-center border rounded bg-light text-muted"
                                     style="min-height: 180px;">
                                    <i class="far fa-user fa-3x mb-2"></i>
                                    <div class="small">Aucune photo</div>
                                </div>
                            </div>
                            <div class="col-md-9">
                        @endif
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

            {{-- Pièces attendues vs déposées — surfaces les pièces requises
                 pour la section choisie qui n'ont PAS encore été déposées.
                 Pour les réclamer, chef-centre rejette le dossier avec un
                 motif explicite ; le candidat les voit ensuite et peut les
                 déposer via la modify-wizard. --}}
            @php
                $uploadedCodes = $candidat->documents->pluck('documentRequis.code')->filter()->all();
                $missingExpected = ($expectedDocs ?? collect())->reject(fn ($d) => in_array($d->code, $uploadedCodes, true));
            @endphp
            @if($missingExpected->isNotEmpty())
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-circle-exclamation text-warning me-2"></i>
                            Pièces attendues non déposées
                            <small class="text-muted ms-2" style="font-size:.75em;">
                                pour la section <strong>{{ $candidat->premierChoix?->nom }}</strong>
                            </small>
                        </h2>
                    </div>
                    <ul class="list-group list-group-flush">
                        @foreach($missingExpected as $exp)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $exp->libelle }}</strong>
                                    <code class="ms-2 small text-muted">{{ $exp->code }}</code>
                                </div>
                                @if($exp->obligatoire)
                                    <span class="badge bg-danger">
                                        <i class="fas fa-asterisk me-1"></i>Obligatoire
                                    </span>
                                @else
                                    <span class="badge bg-secondary">Optionnelle</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <div class="card-footer bg-white small text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Pour réclamer une pièce manquante&nbsp;: rejetez le dossier avec un motif clair
                        (ex.&nbsp;<em>«&nbsp;Pièce manquante&nbsp;: {{ $missingExpected->first()->libelle }}&nbsp;»</em>).
                        Le candidat la verra dans son tableau de bord et pourra la déposer via «&nbsp;Modifier mon dossier&nbsp;».
                    </div>
                </div>
            @endif

            {{-- Documents avec workflow de revue --}}
            @php
                $pendingCount = $candidat->documents->where('review_status', \Modules\Concours\Models\CandidatDocument::REVIEW_PENDING)->count();
                $approvedCount = $candidat->documents->where('review_status', \Modules\Concours\Models\CandidatDocument::REVIEW_APPROVED)->count();
                $rejectedCount = $candidat->documents->where('review_status', \Modules\Concours\Models\CandidatDocument::REVIEW_REJECTED)->count();
                $reviewBadgeMeta = [
                    'en_attente' => ['cls' => 'warning text-dark', 'label' => 'En attente', 'icon' => 'fa-clock'],
                    'valide'     => ['cls' => 'success',           'label' => 'Validé',     'icon' => 'fa-check'],
                    'a_refaire'  => ['cls' => 'danger',            'label' => 'À refaire',  'icon' => 'fa-rotate-left'],
                ];
            @endphp
            <div class="card mb-3"
                 x-data="candidatDocsReview({
                     candidatId: '{{ $candidat->id }}',
                     csrf: '{{ csrf_token() }}',
                     canEdit: {{ ! empty($canEdit) ? 'true' : 'false' }},
                 })">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="h5 mb-0">
                        <i class="far fa-folder-open text-primary me-2"></i>Documents
                        <small class="text-muted ms-2" style="font-size:.75em;">
                            {{ $candidat->documents->count() }} pièce(s) &middot;
                            @if($pendingCount)<span class="text-warning">{{ $pendingCount }} en attente</span> &middot;@endif
                            <span class="text-success">{{ $approvedCount }} validée(s)</span>
                            @if($rejectedCount) &middot; <span class="text-danger">{{ $rejectedCount }} à refaire</span>@endif
                        </small>
                    </h2>
                    @if(! empty($canEdit) && ! empty($sessionActive) && $pendingCount > 0)
                        <form method="POST" action="{{ route('admin.pages.concours.candidats.show', $candidat->id) }}" class="mb-0"
                              onsubmit="event.preventDefault(); $dispatch('bulk-validate');">
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-success"
                                @click="bulkValidate"
                                :disabled="bulkBusy"
                                title="Marquer toutes les pièces en attente comme validées">
                            <span x-show="!bulkBusy"><i class="fas fa-check-double me-1"></i>Tout valider</span>
                            <span x-show="bulkBusy"><i class="fas fa-spinner fa-spin me-1"></i>…</span>
                        </button>
                    @endif
                </div>

                <ul class="list-group list-group-flush">
                    @forelse($candidat->documents as $d)
                        @php $meta = $reviewBadgeMeta[$d->review_status] ?? $reviewBadgeMeta['en_attente']; @endphp
                        <li class="list-group-item"
                            x-data="{
                                doc: {
                                    id: '{{ $d->id }}',
                                    code: '{{ $d->documentRequis?->code }}',
                                    label: '{{ addslashes($d->documentRequis?->libelle ?? '?') }}',
                                    originalName: @js($d->original_name ?: basename($d->file_path)),
                                    sizeKb: {{ (int) round($d->size_bytes / 1024) }},
                                    ext: '{{ strtolower(pathinfo($d->file_path, PATHINFO_EXTENSION)) }}',
                                    mime: @js($d->mime_type ?: 'application/octet-stream'),
                                    status: '{{ $d->review_status }}',
                                    statusCls: @js($meta['cls']),
                                    statusLabel: @js($meta['label']),
                                    statusIcon: @js($meta['icon']),
                                    reviewedAt: @js($d->reviewed_at?->format('d/m/Y H:i')),
                                    reviewedBy: @js($d->reviewedBy ? trim($d->reviewedBy->prenom . ' ' . $d->reviewedBy->nom) : null),
                                    comment: @js($d->review_comment),
                                },
                                showReject: false,
                                rejectComment: '',
                                busy: false,
                                replaceBusy: false,
                                error: '',
                            }">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <div class="flex-grow-1" style="min-width:14rem;">
                                    <strong x-text="doc.label">{{ $d->documentRequis?->libelle }}</strong>
                                    <span class="badge bg-light text-muted ms-1" x-text="doc.ext.toUpperCase()">{{ strtoupper(pathinfo($d->file_path, PATHINFO_EXTENSION)) }}</span>
                                    <span class="badge ms-1" :class="'bg-' + doc.statusCls">
                                        <i :class="'fas ' + doc.statusIcon"></i>
                                        <span x-text="doc.statusLabel">{{ $meta['label'] }}</span>
                                    </span>
                                    <div class="small text-muted mt-1">
                                        <span x-text="doc.originalName">{{ $d->original_name ?: basename($d->file_path) }}</span>
                                        &middot; <span x-text="doc.sizeKb + ' Ko'">{{ round($d->size_bytes / 1024) }} Ko</span>
                                        <template x-if="doc.reviewedAt">
                                            <span class="ms-2">
                                                <i class="far fa-clock me-1"></i>
                                                <span x-text="doc.reviewedAt"></span>
                                                <template x-if="doc.reviewedBy">
                                                    <span> · par <span x-text="doc.reviewedBy"></span></span>
                                                </template>
                                            </span>
                                        </template>
                                    </div>
                                    <template x-if="doc.comment">
                                        <div class="small text-danger mt-1">
                                            <i class="fas fa-comment me-1"></i>
                                            <span x-text="doc.comment"></span>
                                        </div>
                                    </template>
                                </div>

                                <div class="d-flex gap-1 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            @click="$dispatch('preview-doc', { doc })">
                                        <i class="far fa-eye me-1"></i>Aperçu
                                    </button>

                                    @if(! empty($canEdit) && ! empty($sessionActive))
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                @click="approve"
                                                :disabled="busy || doc.status === 'valide'">
                                            <i class="fas fa-check me-1"></i>Valider
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                @click="showReject = true"
                                                :disabled="busy">
                                            <i class="fas fa-rotate-left me-1"></i>À refaire
                                        </button>

                                        <label class="btn btn-sm btn-outline-secondary mb-0" :class="{ disabled: replaceBusy }">
                                            <i class="fas fa-upload me-1"></i>
                                            <span x-show="!replaceBusy">Remplacer</span>
                                            <span x-show="replaceBusy"><i class="fas fa-spinner fa-spin"></i></span>
                                            <input type="file" class="d-none"
                                                   accept="application/pdf,image/jpeg,image/png,image/webp,image/jpg"
                                                   @change="replace($event)"
                                                   :disabled="replaceBusy">
                                        </label>
                                    @elseif(! empty($canEdit) && empty($sessionActive))
                                        <span class="badge bg-light text-muted border" title="Session clôturée — décisions verrouillées">
                                            <i class="fas fa-lock me-1"></i>Verrouillé
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Reject comment inline form --}}
                            <div x-show="showReject" x-transition x-cloak class="mt-2 p-3 border rounded bg-light">
                                <label class="form-label small">Motif de rejet (sera communiqué au candidat) <span class="text-danger">*</span></label>
                                <textarea x-model="rejectComment" class="form-control form-control-sm" rows="2" maxlength="500"
                                          placeholder="Ex : la photo est floue, le scan est tronqué…"></textarea>
                                <div class="d-flex gap-2 justify-content-end mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            @click="showReject = false; rejectComment = '';">Annuler</button>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            @click="reject"
                                            :disabled="busy || rejectComment.trim().length === 0">
                                        <i class="fas fa-rotate-left me-1"></i>Confirmer le rejet
                                    </button>
                                </div>
                            </div>

                            <div x-show="error" x-text="error" class="text-danger small mt-2"></div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucun document.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Preview modal — handles both uploaded documents (per-doc id +
                 preview route) AND admin-generated PDFs (fiche / emploi du
                 temps) where the dispatcher passes a fully-formed pdfUrl. --}}
            <div class="modal fade" id="docPreviewModal" tabindex="-1" aria-hidden="true"
                 x-data="{ doc: null, url: '' }"
                 @preview-doc.window="
                     doc = $event.detail.doc;
                     url = doc.isAdminPdf
                         ? doc.pdfUrl
                         : '/api/admin/concours/candidats/{{ $candidat->id }}/documents/' + doc.id + '/preview';
                     bootstrap.Modal.getOrCreateInstance(document.getElementById('docPreviewModal')).show();
                 ">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="h5 modal-title">
                                <i class="far fa-file me-2 text-primary"></i>
                                <span x-text="doc?.label"></span>
                                <small class="text-muted ms-2" x-text="doc?.originalName"></small>
                            </h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0" style="background:#f3f4f6;">
                            <template x-if="doc?.mime?.startsWith('image/')">
                                <img :src="url" class="img-fluid d-block mx-auto" style="max-height:80vh;">
                            </template>
                            <template x-if="doc?.mime === 'application/pdf'">
                                <iframe :src="url" style="width:100%; height:80vh; border:0;" loading="lazy"></iframe>
                            </template>
                            <template x-if="doc && !doc.mime.startsWith('image/') && doc.mime !== 'application/pdf'">
                                <div class="p-4 text-center text-muted">
                                    <p>Aperçu non disponible pour ce type de fichier (<span x-text="doc.mime"></span>).</p>
                                    <a :href="url" class="btn btn-outline-primary" download>
                                        <i class="fas fa-download me-2"></i>Télécharger
                                    </a>
                                </div>
                            </template>
                        </div>
                        <div class="modal-footer">
                            <a :href="url" class="btn btn-outline-secondary" target="_blank">
                                <i class="fas fa-up-right-from-square me-1"></i>Ouvrir dans un nouvel onglet
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
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
            {{-- Global accept/reject — only available while the session is active.
                 The view-layer gate matches the service-layer check in
                 CandidatValidationService::decide() (sessionInscriptionClosed),
                 so even a forged POST is refused once $sessionActive is false. --}}
            @if($canValidate && ! empty($sessionActive) && in_array($candidat->statut, ['non', 'rejete'], true))
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

            {{-- Historique du dossier — placed ABOVE Paiements per request.
                 Always rendered (even when empty) so the legacy candidats
                 still surface the section with a clear empty-state message
                 instead of having the whole card disappear. --}}
            @php
                $channelMeta = [
                    'admin'  => ['label' => 'Admin',        'icon' => 'fas fa-user-tie',    'cls' => 'primary'],
                    'public' => ['label' => 'Candidat',     'icon' => 'fas fa-user',        'cls' => 'info'],
                    'system' => ['label' => 'Système',      'icon' => 'fas fa-robot',       'cls' => 'success'],
                ];
                $statutLabel = [
                    'non' => 'En cours', 'oui' => 'Accepté', 'valid' => 'Validé (payé)',
                    'rejete' => 'Rejeté', 'admis' => 'Admis',
                ];
            @endphp
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i>Historique du dossier</h2>
                    <small class="text-muted">{{ $candidat->modifications->count() }} évènement(s)</small>
                </div>
                @if($candidat->modifications->isNotEmpty())
                    <ul class="timeline list-unstyled m-0 p-3">
                        @foreach($candidat->modifications as $mod)
                            @php
                                $m = $channelMeta[$mod->channel] ?? ['label' => $mod->channel, 'icon' => 'fas fa-circle', 'cls' => 'secondary'];
                            @endphp
                            <li class="timeline-item">
                                <span class="timeline-marker bg-{{ $m['cls'] }}-subtle text-{{ $m['cls'] }}-emphasis">
                                    <i class="{{ $m['icon'] }}"></i>
                                </span>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <strong class="small">
                                            @if($mod->field === 'statut')
                                                Statut : {{ $statutLabel[$mod->old_value] ?? $mod->old_value ?? '—' }}
                                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                                {{ $statutLabel[$mod->new_value] ?? $mod->new_value }}
                                            @else
                                                <code>{{ $mod->field }}</code> : <span class="text-muted">{{ $mod->old_value ?? '—' }}</span>
                                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                                <span>{{ $mod->new_value }}</span>
                                            @endif
                                        </strong>
                                        <span class="badge bg-{{ $m['cls'] }}-subtle text-{{ $m['cls'] }}-emphasis text-uppercase" style="font-size:.66rem; letter-spacing:.06em;">
                                            <i class="{{ $m['icon'] }} me-1"></i>{{ $m['label'] }}
                                        </span>
                                    </div>
                                    @if($mod->reason)
                                        <div class="small text-muted mt-1">{{ $mod->reason }}</div>
                                    @endif
                                    <div class="d-flex gap-3 small text-muted mt-1">
                                        <span><i class="far fa-clock me-1"></i>{{ $mod->changed_at->format('d/m/Y H:i') }}</span>
                                        @if($mod->user)
                                            <span><i class="far fa-user me-1"></i>{{ $mod->user->prenom }} {{ $mod->user->nom }}</span>
                                        @endif
                                        @if($mod->ip_address)
                                            <span><i class="fas fa-network-wired me-1"></i><code>{{ $mod->ip_address }}</code></span>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="card-body text-muted small">
                        <i class="fas fa-circle-info me-1"></i>
                        Aucun évènement enregistré pour ce dossier
                        @if(empty($sessionActive))
                            — ce dossier est issu d'une session clôturée importée depuis l'ancien système, sans piste d'audit historique.
                        @else
                            — l'historique se remplira à mesure que des actions (revue de pièce, décision, modification) seront effectuées.
                        @endif
                    </div>
                @endif
            </div>

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
        </div>
    </div>
</div>

@push('scripts')
<script>
    /**
     * Per-document review actions: approve / reject / replace + bulk-validate.
     * Each <li> instantiates this inline (via x-data="{ doc: {...}, busy, ... }")
     * and calls into the global factory below for the actual XHR work.
     *
     * On success we mutate the local `doc` object so the badge + button states
     * update without a full page reload.
     */
    function candidatDocsReview(config) {
        const post = async (url, body) => {
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrf,
                    'Accept': 'application/json',
                    'Content-Type': body instanceof FormData ? undefined : 'application/json',
                },
                body: body instanceof FormData ? body : JSON.stringify(body),
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || data.ok === false) {
                throw new Error(data.error || data.message || 'Erreur serveur.');
            }
            return data;
        };

        const STATUS_META = {
            'en_attente': { cls: 'warning text-dark', label: 'En attente', icon: 'fa-clock' },
            'valide':     { cls: 'success',           label: 'Validé',     icon: 'fa-check' },
            'a_refaire':  { cls: 'danger',            label: 'À refaire',  icon: 'fa-rotate-left' },
        };

        return {
            bulkBusy: false,

            // ----- Per-doc actions (called from each <li>'s x-data scope) -----
            async approve() {
                this.error = '';
                this.busy = true;
                try {
                    const data = await post(
                        `/api/admin/concours/candidats/${config.candidatId}/documents/${this.doc.id}/review`,
                        { status: 'valide' },
                    );
                    this.applyStatus(data, 'valide');
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.busy = false;
                }
            },
            async reject() {
                this.error = '';
                if (this.rejectComment.trim().length === 0) {
                    this.error = 'Précisez un motif.'; return;
                }
                this.busy = true;
                try {
                    const data = await post(
                        `/api/admin/concours/candidats/${config.candidatId}/documents/${this.doc.id}/review`,
                        { status: 'a_refaire', comment: this.rejectComment },
                    );
                    this.applyStatus(data, 'a_refaire', this.rejectComment);
                    this.showReject = false;
                    this.rejectComment = '';
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.busy = false;
                }
            },
            async replace(event) {
                this.error = '';
                const file = event.target.files?.[0];
                if (!file) return;
                this.replaceBusy = true;
                const form = new FormData();
                form.append('file', file);
                try {
                    const data = await post(
                        `/api/admin/concours/candidats/${config.candidatId}/documents/${this.doc.id}/replace`,
                        form,
                    );
                    this.doc.originalName = data.original_name;
                    this.doc.sizeKb = data.size_kb;
                    this.applyStatus({ review_status: 'en_attente' }, 'en_attente');
                    this.doc.reviewedAt = null;
                    this.doc.reviewedBy = null;
                    this.doc.comment = null;
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.replaceBusy = false;
                    event.target.value = '';
                }
            },
            applyStatus(data, status, comment = null) {
                const meta = STATUS_META[status] ?? STATUS_META['en_attente'];
                this.doc.status      = status;
                this.doc.statusCls   = meta.cls;
                this.doc.statusLabel = meta.label;
                this.doc.statusIcon  = meta.icon;
                if (data.reviewed_at)  this.doc.reviewedAt = new Date(data.reviewed_at).toLocaleString('fr-FR');
                if (data.reviewed_by)  this.doc.reviewedBy = data.reviewed_by;
                if (comment !== null)  this.doc.comment = comment;
            },

            // ----- Bulk validate (on the card root) -----
            async bulkValidate() {
                if (!confirm('Valider toutes les pièces en attente ?')) return;
                this.bulkBusy = true;
                try {
                    const form = new FormData();
                    const resp = await fetch(
                        `/api/admin/concours/candidats/${config.candidatId}/documents/bulk-validate`,
                        { method: 'POST', headers: { 'X-CSRF-TOKEN': config.csrf }, body: form, redirect: 'manual' },
                    );
                    // The route 302s back to the candidat show; just reload.
                    window.location.reload();
                } catch (e) {
                    alert(e.message);
                } finally {
                    this.bulkBusy = false;
                }
            },
        };
    }
</script>
@endpush
@endsection
