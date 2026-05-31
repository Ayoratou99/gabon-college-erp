@extends('layouts.admin')

@section('title', 'Paramétrage')
@section('page-title', 'Paramétrage de la plateforme')

@section('content')
<div x-data="settingsForm({ index: @js($rows->map(fn ($r) => trim(($r['model']->key ?? '') . ' ' . ($r['model']->label ?? '') . ' ' . ($r['model']->description ?? '')))->values()->all()) })">

    {{-- Category tabs --}}
    <ul class="nav nav-pills settings-tabs mb-4 flex-wrap">
        @foreach($categories as $code => $label)
            <li class="nav-item">
                <a class="nav-link {{ $code === $active ? 'active' : '' }}"
                   href="{{ route('admin.pages.parametrage.index', ['cat' => $code]) }}">
                    @switch($code)
                        @case('concours')  <i class="fas fa-pen-to-square me-2"></i> @break
                        @case('site')      <i class="fas fa-palette me-2"></i> @break
                        @case('security')  <i class="fas fa-shield-halved me-2"></i> @break
                        @case('support')   <i class="fas fa-headset me-2"></i> @break
                        @default           <i class="fas fa-cog me-2"></i>
                    @endswitch
                    {{ $label }}
                </a>
            </li>
        @endforeach
        <li class="nav-item">
            <a class="nav-link"
               href="{{ route('admin.pages.parametrage.history') }}">
                <i class="fas fa-clock-rotate-left me-2"></i>Historique
            </a>
        </li>
    </ul>

    @if(! $canEdit)
        <div class="alert alert-info">
            <i class="fas fa-eye me-2"></i>Vous consultez les paramètres en lecture seule. Demandez à un administrateur l'accès en écriture.
        </div>
    @endif

    {{-- Search / filter within the current category. Client-side so it stays
         instant even as the number of settings grows. --}}
    @if($rows->isNotEmpty())
        <div class="card mb-3 shadow-sm">
            <div class="card-body py-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-magnifying-glass text-muted"></i>
                    </span>
                    <input type="search" x-model="search" class="form-control border-start-0 ps-0"
                           placeholder="Rechercher un paramètre — clé, libellé ou description…"
                           aria-label="Rechercher un paramètre">
                    <button type="button" class="btn btn-outline-secondary" x-show="search" x-cloak
                            @click="search = ''" title="Effacer">
                        <i class="fas fa-times"></i>
                    </button>
                    <span class="input-group-text bg-light text-muted"
                          x-text="visibleCount + ' / {{ $rows->count() }}'"></span>
                </div>
            </div>
        </div>
    @endif

    {{-- Settings cards --}}
    <div class="row g-3">
        @forelse($rows as $row)
            @php $s = $row['model']; @endphp

            <div class="col-md-6 col-xl-4"
                 x-show="matchesSearch(@js(trim(($s->key ?? '') . ' ' . ($s->label ?? '') . ' ' . ($s->description ?? ''))))"
                 x-transition.opacity>
                <div class="settings-card card h-100"
                     x-data="settingsEditor({
                         id: '{{ $s->id }}',
                         key: @js($s->key),
                         type: @js($s->type),
                         value: @js($row['value']),
                         encrypted: {{ $s->is_encrypted ? 'true' : 'false' }},
                         hidden: {{ $row['hidden'] ? 'true' : 'false' }},
                     })">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h3 class="h6 mb-1">{{ $s->label ?: $s->key }}</h3>
                                <code class="setting-key">{{ $s->key }}</code>
                            </div>
                            @if($s->is_encrypted)
                                <span class="badge bg-warning-subtle text-warning-emphasis ms-2"
                                      data-bs-toggle="tooltip" title="Valeur chiffrée — super-admin uniquement">
                                    <i class="fas fa-lock"></i>
                                </span>
                            @endif
                        </div>

                        @if($s->description)
                            <p class="text-muted small mb-3">{{ $s->description }}</p>
                        @endif

                        <div class="setting-field">
                            {{-- ─── Per-type editor ─── --}}
                            @if($row['hidden'])
                                <div class="form-control bg-light text-muted">••••••••</div>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-lock me-1"></i>Visible uniquement par les super-admins.
                                </small>

                            @elseif($s->type === 'boolean')
                                <div class="form-check form-switch fs-5 mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           x-model="editing.value" @change="markDirty">
                                </div>

                            @elseif($s->type === 'color')
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" x-model="editing.value" @change="markDirty"
                                           class="form-control form-control-color" style="width: 4rem;">
                                    <input type="text" x-model="editing.value" @input="markDirty"
                                           class="form-control font-monospace" placeholder="#RRGGBB"
                                           pattern="^#[0-9a-fA-F]{6}$">
                                </div>

                            @elseif($s->type === 'text' || $s->type === 'json')
                                <textarea x-model="textValue" @input="markDirty(); validateJson()"
                                          rows="{{ $s->type === 'json' ? 6 : 3 }}"
                                          class="form-control font-monospace"
                                          :class="{ 'is-invalid': jsonError }"></textarea>
                                <div class="invalid-feedback" x-show="jsonError" x-text="jsonError"></div>

                            @elseif($s->type === 'image_url')
                                {{-- Two input modes:
                                     - Drag-drop / click a file → AJAX upload, URL auto-fills, setting saved server-side.
                                     - Or paste a URL directly for an externally-hosted asset. --}}
                                <div x-data="{
                                        uploading: false,
                                        dragover: false,
                                        progressPct: 0,
                                        async upload(file) {
                                            if (!file) return;
                                            this.uploading = true;
                                            this.progressPct = 0;
                                            const form = new FormData();
                                            form.append('file', file);
                                            try {
                                                const xhr = new XMLHttpRequest();
                                                xhr.open('POST', '{{ route('admin.pages.parametrage.upload', $s->id) }}');
                                                xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
                                                xhr.setRequestHeader('Accept', 'application/json');
                                                xhr.upload.addEventListener('progress', (e) => {
                                                    if (e.lengthComputable) this.progressPct = Math.round((e.loaded / e.total) * 100);
                                                });
                                                const data = await new Promise((resolve, reject) => {
                                                    xhr.onload = () => resolve(JSON.parse(xhr.responseText));
                                                    xhr.onerror = () => reject(new Error('Erreur réseau.'));
                                                    xhr.send(form);
                                                });
                                                if (data.ok) {
                                                    // The server has already persisted; sync local state so
                                                    // the existing settingsEditor doesn't try to re-save the URL.
                                                    editing.value = data.url;
                                                    dirty = false;
                                                    msg = 'Image téléversée (' + data.size_kb + ' Ko)';
                                                    msgClass = 'text-success';
                                                } else {
                                                    msg = data.error || 'Échec du téléversement.';
                                                    msgClass = 'text-danger';
                                                }
                                            } catch (e) {
                                                msg = e.message; msgClass = 'text-danger';
                                            } finally {
                                                this.uploading = false;
                                            }
                                        },
                                    }"
                                    class="image-upload-zone"
                                    :class="{ 'image-upload-zone--dragover': dragover, 'image-upload-zone--busy': uploading }"
                                    @dragover.prevent="dragover = true"
                                    @dragleave.prevent="dragover = false"
                                    @drop.prevent="dragover = false; upload($event.dataTransfer.files[0])">

                                    <template x-if="!uploading">
                                        <div class="text-center py-2">
                                            <label class="btn btn-sm btn-outline-primary mb-2 mb-md-0">
                                                <i class="fas fa-cloud-arrow-up me-1"></i>Téléverser
                                                <input type="file" class="d-none"
                                                       accept="image/png,image/jpeg,image/webp,image/svg+xml,image/jpg"
                                                       @change="upload($event.target.files[0])">
                                            </label>
                                            <span class="small text-muted ms-2">ou glissez-déposez l'image ici</span>
                                        </div>
                                    </template>
                                    <template x-if="uploading">
                                        <div class="py-2 text-center small text-muted">
                                            <i class="fas fa-spinner fa-spin me-1"></i>
                                            <span>Envoi… </span><span x-text="progressPct + '%'"></span>
                                        </div>
                                    </template>
                                </div>

                                <div class="form-text small mt-2">Ou collez une URL externe&nbsp;:</div>
                                <input type="url" x-model="editing.value" @input="markDirty"
                                       class="form-control mt-1" placeholder="https://...">
                                <template x-if="editing.value">
                                    <img :src="editing.value" alt="" class="settings-preview mt-2"
                                         onerror="this.style.display='none'">
                                </template>

                            @elseif($s->type === 'integer' || $s->type === 'decimal')
                                <input type="number" x-model="editing.value" @input="markDirty"
                                       step="{{ $s->type === 'integer' ? '1' : '0.01' }}"
                                       class="form-control">

                            @else
                                <input type="{{ $row['input_type'] }}" x-model="editing.value" @input="markDirty"
                                       class="form-control">
                            @endif
                        </div>

                        {{-- Save / status row --}}
                        <div class="d-flex justify-content-between align-items-center mt-3 setting-actions"
                             x-show="dirty || msg" x-transition>
                            <small class="setting-msg" :class="msgClass" x-text="msg"></small>
                            <button type="button"
                                    class="btn btn-sm btn-primary"
                                    @click="save"
                                    :disabled="saving || !!jsonError || !{{ $canEdit ? 'true' : 'false' }}"
                                    x-show="dirty">
                                <span x-show="!saving"><i class="fas fa-save me-1"></i> Enregistrer</span>
                                <span x-show="saving"><i class="fas fa-spinner fa-spin me-1"></i> En cours…</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border text-center py-5">
                    <i class="fas fa-folder-open fs-2 text-muted d-block mb-2"></i>
                    Aucun paramètre dans cette catégorie.
                </div>
            </div>
        @endforelse

        {{-- Client-side "no match" (the @empty above handles a truly empty category). --}}
        <div class="col-12" x-show="noResults" x-cloak>
            <div class="alert alert-light border text-center py-4">
                <i class="fas fa-magnifying-glass fs-4 text-muted d-block mb-2"></i>
                Aucun paramètre ne correspond à « <span class="fw-semibold" x-text="search"></span> ».
            </div>
        </div>
    </div>

    {{-- Toast container --}}
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <template x-for="t in toasts" :key="t.id">
            <div class="toast show align-items-center text-bg-success border-0">
                <div class="d-flex">
                    <div class="toast-body" x-text="t.msg"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="dismissToast(t.id)"></button>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection
