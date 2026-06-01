@php
    $get = fn (string $k, $default = null) => $draft[$k] ?? $default;
    $nationalite = $nationalites->firstWhere('id', $get('nationalite_id'))?->nom;
    $serie       = $series->firstWhere('id', $get('serie_bac_id'));
    $premier     = $sections->firstWhere('id', $get('section_premier_choix_id'))?->nom;
    $second      = $sections->firstWhere('id', $get('section_second_choix_id'))?->nom;
    $centre      = $centres->firstWhere('id', $get('centre_id'));

    // The modify-dossier flow passes $existingDocuments (keyed by
    // documents_requis.code) so each slot can show "existing → replaced"
    // and we don't force the candidat to re-upload everything.
    $existingDocuments = $existingDocuments ?? collect();

    // Per-flow URLs — passed from the wizard layout via the view-data.
    // Defaults are the inscription endpoints; the modify wizard overrides.
    $stageUrl   = $stageUrl   ?? route('concours.inscription.wizard.stage');
    $unstageUrl = $unstageUrl ?? url('/inscription/documents/stage');

    // Build the JS init payload: one slot for the photo + one per
    // documents_requis row. Each slot carries its `review_status` (so the
    // modify flow can render an "À refaire" badge + comment on the slots
    // that were flagged by chef-centre).
    $existingMeta = fn (?array $e) => $e ? [
        'name'           => $e['name'] ?? '',
        'review_status'  => $e['review_status'] ?? 'en_attente',
        'review_comment' => $e['review_comment'] ?? null,
    ] : null;

    $slots = [];
    $slots[] = [
        'code'        => $photoCode,
        'label'       => "Photo d'identité",
        'description' => "JPG / PNG / WebP, 4 Mo max. Fond clair, visage centré.",
        'accept'      => 'image/jpeg,image/png,image/webp,image/jpg',
        'required'    => empty($existingDocuments[$photoCode] ?? null),
        'staged'      => $stagedFiles[$photoCode] ?? null,
        'existing'    => $existingMeta($existingDocuments[$photoCode] ?? null),
    ];
    foreach ($documents as $d) {
        $existing   = $existingDocuments[$d->code] ?? null;
        $isRejected = ($existing['review_status'] ?? null) === 'a_refaire';
        $slots[] = [
            'code'        => $d->code,
            'label'       => $d->libelle,
            'description' => $d->description,
            'accept'      => 'application/pdf,image/jpeg,image/png,image/webp,image/jpg',
            // Required if (a) no existing on file, OR (b) chef-centre flagged
            // the existing version as à-refaire — the candidat must upload a
            // fresh one to clear that flag.
            'required'    => ((bool) ($d->obligatoire ?? true)) && (empty($existing) || $isRejected),
            'staged'      => $stagedFiles[$d->code] ?? null,
            'existing'    => $existingMeta($existing),
        ];
    }
@endphp

<div class="alert alert-info small mb-4">
    <i class="fas fa-circle-info me-1"></i>
    Téléversez chaque pièce <strong>une par une</strong> ci-dessous. Chaque envoi se fait à part
    pour éviter les erreurs de transmission sur connexion lente.
    Une fois toutes les pièces déposées, cliquez sur <strong>Soumettre mon dossier</strong>.
</div>

{{-- Récapitulatif des étapes précédentes --}}
<div class="card mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0"><i class="fas fa-clipboard-check text-primary me-2"></i>Récapitulatif</h3>
    </div>
    <div class="card-body py-3 small">
        <div class="row g-2">
            <div class="col-md-6">
                <span class="text-muted">Identité</span><br>
                <strong>{{ $get('nom') }} {{ $get('prenom') }}</strong> ({{ $get('sexe') }})<br>
                <span class="text-muted">Né(e) le {{ $get('date_naissance') }} à {{ $get('lieu_naissance') }}</span><br>
                <span class="text-muted">Nationalité&nbsp;: {{ $nationalite ?? '?' }}</span>
            </div>
            <div class="col-md-6">
                <span class="text-muted">Contact</span><br>
                <strong>{{ $get('email') }}</strong><br>
                <span class="text-muted">{{ $get('telephone') }}</span>
            </div>
            <div class="col-md-6">
                <span class="text-muted">Baccalauréat</span><br>
                @if($get('deja_bac'))
                    <strong>Obtenu en {{ $get('annee_bac') }}</strong>
                @else
                    <strong>En cours</strong>
                @endif
                — Série <strong>{{ $serie?->code ?? '?' }}</strong>
                @if($get('bac_libelle_libre'))<em>({{ $get('bac_libelle_libre') }})</em>@endif
                <br><span class="text-muted">{{ $get('etablissement_frequente') }}</span>
            </div>
            <div class="col-md-6">
                <span class="text-muted">Choix de formation</span><br>
                <strong>1er&nbsp;:</strong> {{ $premier ?? '?' }}<br>
                @if($second)<strong>2ème&nbsp;:</strong> {{ $second }}<br>@endif
                <span class="text-muted">Centre&nbsp;: {{ $centre?->nom }}{{ $centre?->ville ? ' — ' . $centre->ville : '' }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Per-slot uploaders --}}
<h3 class="h6 mb-3"><i class="fas fa-cloud-arrow-up text-primary me-2"></i>Pièces à téléverser</h3>

<div x-data="inscriptionUploaders({
        stageUrl:   '{{ $stageUrl }}',
        unstageUrl: '{{ $unstageUrl }}',
        csrf:       '{{ csrf_token() }}',
        photoCode:  '{{ $photoCode }}',
        slots:      @js($slots),
    })">

    <template x-for="slot in slots" :key="slot.code">
        <div class="card mb-2"
             :class="slot.uploaded
                ? 'border-success'
                : (slot.uploading
                    ? 'border-warning'
                    : (slot.existing && slot.existing.review_status === 'a_refaire'
                        ? 'border-danger bg-danger-subtle'
                        : (slot.existing ? 'border-info' : '')))">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div class="flex-grow-1" style="min-width: 16rem;">
                        <strong x-text="slot.label"></strong>
                        <template x-if="slot.required">
                            <span class="text-danger">*</span>
                        </template>
                        {{-- À refaire badge: highest-priority signal when set. --}}
                        <template x-if="slot.existing && slot.existing.review_status === 'a_refaire'">
                            <span class="badge bg-danger ms-2">
                                <i class="fas fa-rotate-left me-1"></i>À refaire
                            </span>
                        </template>
                        <template x-if="slot.existing && slot.existing.review_status === 'valide'">
                            <span class="badge bg-success ms-2">
                                <i class="fas fa-circle-check me-1"></i>Déjà validée
                            </span>
                        </template>
                        <div class="small text-muted" x-text="slot.description"></div>

                        {{-- Modify flow: render the existing-file note, with a
                             "review feedback" sub-line when chef-centre flagged
                             it. This is the targeted-modify signal that tells
                             the candidat exactly which slot to fix and why. --}}
                        <template x-if="slot.existing && !slot.uploaded && slot.existing.review_status === 'a_refaire'">
                            <div class="small text-danger mt-2">
                                <i class="fas fa-comment me-1"></i>
                                <strong>Pièce à refaire&nbsp;:</strong>
                                <span x-show="slot.existing.review_comment" x-text="slot.existing.review_comment"></span>
                                <span x-show="!slot.existing.review_comment">téléversez un nouveau fichier ci-contre.</span>
                            </div>
                        </template>
                        <template x-if="slot.existing && !slot.uploaded && slot.existing.review_status !== 'a_refaire'">
                            <div class="small text-info-emphasis mt-1">
                                <i class="fas fa-circle-info me-1"></i>
                                Version actuellement déposée&nbsp;: <em x-text="slot.existing.name"></em>.
                                Téléversez un nouveau fichier ci-contre pour la remplacer (optionnel).
                            </div>
                        </template>
                    </div>

                    {{-- State machine: uploading > uploaded > empty (with or without existing) --}}
                    <template x-if="slot.uploading">
                        <div class="d-flex gap-2 align-items-center small">
                            <i class="fas fa-spinner fa-spin text-warning"></i>
                            <span>Envoi en cours…</span>
                            <span x-text="slot.progressPct + '%'"></span>
                        </div>
                    </template>
                    <template x-if="!slot.uploading && slot.uploaded">
                        <div class="d-flex gap-2 align-items-center small">
                            <template x-if="slot.preview">
                                <img :src="slot.preview" alt="Aperçu de la photo recadrée"
                                     class="rounded border" style="height:54px;width:42px;object-fit:cover;">
                            </template>
                            <i class="fas fa-circle-check text-success"></i>
                            <span class="text-success" x-text="slot.originalName"></span>
                            <span class="text-muted" x-text="'(' + slot.sizeKb + ' Ko)'"></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    @click="remove(slot)"
                                    :disabled="slot.removing">
                                <i class="fas fa-trash"></i>
                                <span class="d-none d-md-inline ms-1">Retirer</span>
                            </button>
                        </div>
                    </template>
                    <template x-if="!slot.uploading && !slot.uploaded">
                        <div class="d-flex gap-2 align-items-center">
                            <input type="file"
                                   class="form-control form-control-sm"
                                   :accept="slot.accept"
                                   :id="'file-' + slot.code"
                                   @change="slot.code === photoCode ? onPhotoSelected(slot, $event) : upload(slot, $event)">
                        </div>
                    </template>
                </div>

                <template x-if="slot.error">
                    <div class="alert alert-danger small mt-2 mb-0" x-text="slot.error"></div>
                </template>
            </div>
        </div>
    </template>

    <p class="small text-muted mt-3 mb-0">
        <i class="fas fa-shield-halved me-1"></i>
        Format accepté&nbsp;: PDF / JPG / PNG / WebP. Chaque fichier est envoyé seul,
        sans dépasser sa limite individuelle (max 10 Mo par pièce, 4 Mo pour la photo).
    </p>

    {{-- Photo crop / recadrage modal — opens automatically when a photo file is
         chosen (only for the photo slot). Uses Cropper.js; the cropped result is
         what gets staged, and a thumbnail preview is shown on the slot. Uses
         .modal + :class="{'d-block':cropOpen}" (NOT x-show) to dodge Bootstrap's
         .d-flex !important overriding the inline display:none. --}}
    <div class="modal" tabindex="-1" x-cloak
         :class="{ 'd-block': cropOpen }"
         style="background: rgba(15,23,42,.6);"
         @keydown.escape.window="closeCrop()">
        <div class="modal-dialog modal-dialog-centered modal-lg" @click.stop>
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="h6 mb-0"><i class="fas fa-crop-simple text-primary me-2"></i>Recadrer la photo d'identité</h3>
                    <button type="button" class="btn-close" @click="closeCrop()"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Déplacez et zoomez l'image pour centrer le visage. Le cadre est au format
                        portrait d'une photo d'identité.
                    </p>
                    <div style="max-height: 60vh; overflow: hidden;">
                        {{-- Cropper replaces this <img> with its own UI. --}}
                        <img id="photo-cropper-img" alt="" style="max-width: 100%; display: block;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="closeCrop()">Annuler</button>
                    <button type="button" class="btn btn-primary" @click="confirmCrop()">
                        <i class="fas fa-check me-1"></i>Recadrer et téléverser
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
{{-- Cropper.js — only used for the photo slot (recadrage avant téléversement). --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
    function inscriptionUploaders(config) {
        return {
            photoCode: config.photoCode,

            // ---- Photo crop modal state ----
            cropOpen: false,
            cropSlot: null,
            cropper:  null,
            cropUrl:  null,
            cropName: 'photo.jpg',

            slots: config.slots.map(s => ({
                code: s.code,
                label: s.label,
                description: s.description,
                accept: s.accept,
                required: s.required,
                existing:      s.existing || null,
                uploaded:      !!s.staged,
                uploading:     false,
                removing:      false,
                progressPct:   s.staged ? 100 : 0,
                originalName:  s.staged ? s.staged.original_name : '',
                sizeKb:        s.staged ? Math.round(s.staged.size_bytes / 1024) : 0,
                preview:       null,
                error:         '',
            })),

            // ---- Photo: open the crop modal instead of uploading directly ----
            onPhotoSelected(slot, event) {
                const file = event.target.files?.[0];
                event.target.value = ''; // allow re-selecting the same file later
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    slot.error = 'Veuillez choisir une image (JPG, PNG ou WebP).';
                    return;
                }
                slot.error = '';
                this.cropSlot = slot;
                this.cropName = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
                if (this.cropUrl) URL.revokeObjectURL(this.cropUrl);
                this.cropUrl = URL.createObjectURL(file);
                this.cropOpen = true;

                this.$nextTick(() => {
                    const img = document.getElementById('photo-cropper-img');
                    if (!img || typeof Cropper === 'undefined') {
                        // Cropper unavailable (offline CDN) → fall back to a plain upload.
                        this.closeCrop();
                        this.uploadFile(slot, file);
                        return;
                    }
                    if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
                    img.onload = () => {
                        this.cropper = new Cropper(img, {
                            aspectRatio: 3 / 4,   // photo d'identité (portrait)
                            viewMode: 1,
                            autoCropArea: 1,
                            background: false,
                            movable: true,
                            zoomable: true,
                            responsive: true,
                        });
                    };
                    img.src = this.cropUrl;
                });
            },

            confirmCrop() {
                if (!this.cropper || !this.cropSlot) { this.closeCrop(); return; }
                const slot = this.cropSlot;
                const canvas = this.cropper.getCroppedCanvas({
                    width: 600, height: 800,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                if (!canvas) { slot.error = 'Recadrage impossible, réessayez.'; this.closeCrop(); return; }
                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                const name = this.cropName;
                this.closeCrop();
                canvas.toBlob((blob) => {
                    if (!blob) { slot.error = 'Recadrage impossible, réessayez.'; return; }
                    slot.preview = dataUrl;
                    this.uploadFile(slot, new File([blob], name, { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.9);
            },

            closeCrop() {
                if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
                if (this.cropUrl) { URL.revokeObjectURL(this.cropUrl); this.cropUrl = null; }
                this.cropOpen = false;
                this.cropSlot = null;
            },

            // ---- Generic file input (non-photo slots) ----
            async upload(slot, event) {
                const file = event.target.files?.[0];
                if (!file) return;
                await this.uploadFile(slot, file);
            },

            // ---- Shared staging XHR (file already chosen / cropped) ----
            async uploadFile(slot, file) {
                slot.error = '';
                slot.uploading = true;
                slot.progressPct = 0;

                const form = new FormData();
                form.append('code', slot.code);
                form.append('file', file);

                try {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', config.stageUrl);
                    xhr.setRequestHeader('X-CSRF-TOKEN', config.csrf);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            slot.progressPct = Math.round((e.loaded / e.total) * 100);
                        }
                    });
                    const response = await new Promise((resolve, reject) => {
                        xhr.onload = () => resolve({ status: xhr.status, body: xhr.responseText });
                        xhr.onerror = () => reject(new Error('Erreur réseau.'));
                        xhr.send(form);
                    });
                    const data = JSON.parse(response.body);
                    if (response.status >= 200 && response.status < 300 && data.ok) {
                        slot.uploaded = true;
                        slot.originalName = data.original_name;
                        slot.sizeKb = data.size_kb;
                        slot.progressPct = 100;
                    } else {
                        slot.error = data.error || 'Le fichier a été rejeté.';
                        slot.preview = null;
                    }
                } catch (e) {
                    slot.error = e.message || 'Erreur réseau.';
                    slot.preview = null;
                } finally {
                    slot.uploading = false;
                }
            },

            async remove(slot) {
                if (!slot.uploaded || slot.removing) return;
                slot.removing = true;
                slot.error = '';
                try {
                    const resp = await fetch(`${config.unstageUrl}/${encodeURIComponent(slot.code)}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': config.csrf,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await resp.json();
                    if (resp.ok && data.ok) {
                        slot.uploaded = false;
                        slot.originalName = '';
                        slot.sizeKb = 0;
                        slot.progressPct = 0;
                        slot.preview = null;
                        const input = document.getElementById('file-' + slot.code);
                        if (input) input.value = '';
                    } else {
                        slot.error = 'Échec de la suppression.';
                    }
                } catch (e) {
                    slot.error = e.message || 'Erreur réseau.';
                } finally {
                    slot.removing = false;
                }
            },
        };
    }
</script>
@endpush
