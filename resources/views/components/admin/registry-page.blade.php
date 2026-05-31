@props([
    'slug',                 // active slug
    'definition',           // current slug's definition (array)
    'definitions',          // all definitions for tab nav
    'canManage' => false,
    'apiBase',              // base URL for the JSON API (POST/PUT/DELETE)
    'dataUrl',              // server-side DataTables endpoint
    'tabRoute',             // Laravel route name used to build per-slug tabs
])

{{-- Slug tabs --}}
<ul class="nav settings-tabs mb-3 flex-wrap">
    @foreach($definitions as $key => $def)
        <li class="nav-item">
            <a href="{{ route($tabRoute, ['slug' => $key]) }}"
               class="nav-link {{ $key === $slug ? 'active' : '' }}">
                <i class="{{ $def['icon'] }} me-2"></i>{{ $def['title'] }}
            </a>
        </li>
    @endforeach
</ul>

<div x-data="resourceCrud({
    apiBase:  @js($apiBase),
    dtUrl:    @js($dataUrl),
    dtOrder:  [],
    tableId:  'registry-table',
    fields:   @js($definition['fields']),
    dtColumns: @js(collect($definition['columns'])->map(fn ($c) => [
        'data'      => $c['data'],
        'orderable' => $c['orderable'] ?? true,
        'className' => $c['className'] ?? '',
    ])->all()),
})">

    @if($canManage)
        <div class="d-flex justify-content-end mb-3">
            <button @click="create()" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-2"></i>Nouvelle entrée
            </button>
        </div>
    @endif

    <div class="card">
        <x-admin.datatable id="registry-table"
            :headings="array_merge(array_column($definition['columns'], 'label'), [''])" />
    </div>

    {{-- Modal (Alpine-driven).  Bootstrap's .d-block uses !important and would
         override Alpine's x-show display:none, so we toggle .d-block via :class
         instead and rely on .modal's default display:none when editing == null. --}}
    <div :class="{ 'd-block': editing !== null }" x-cloak
         class="modal" tabindex="-1" style="background: rgba(15,23,42,.55);"
         @keydown.escape.window="close()">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="{{ $definition['icon'] }} me-2"></i>
                        <span x-text="editing?.id ? 'Modifier' : 'Créer'"></span> — {{ $definition['title'] }}
                    </h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>

                <div class="modal-body">
                    <template x-if="editing">
                        <div class="row g-3">
                            @foreach($definition['fields'] as $f)
                                <div class="col-md-6">
                                    <label class="form-label small">{{ $f['label'] }}
                                        @if(($f['required'] ?? false))<span class="text-danger">*</span>@endif
                                    </label>

                                    @switch($f['type'])
                                        @case('boolean')
                                            <div class="form-check form-switch mt-1">
                                                <input type="checkbox" class="form-check-input"
                                                       x-model="editing.data['{{ $f['name'] }}']">
                                            </div>
                                            @break
                                        @case('textarea')
                                            <textarea class="form-control" rows="3"
                                                      x-model="editing.data['{{ $f['name'] }}']"
                                                      :class="errorFor('{{ $f['name'] }}') ? 'is-invalid' : ''"></textarea>
                                            @break
                                        @case('select')
                                            <select class="form-select"
                                                    x-model="editing.data['{{ $f['name'] }}']"
                                                    :class="errorFor('{{ $f['name'] }}') ? 'is-invalid' : ''">
                                                <option value="">—</option>
                                                @foreach(($f['options'] ?? []) as $val => $lbl)
                                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                            @break
                                        @case('integer')
                                            <input type="number" class="form-control"
                                                   x-model.number="editing.data['{{ $f['name'] }}']"
                                                   :class="errorFor('{{ $f['name'] }}') ? 'is-invalid' : ''">
                                            @break
                                        @case('decimal')
                                            <input type="number" step="{{ $f['step'] ?? '0.01' }}" class="form-control"
                                                   x-model.number="editing.data['{{ $f['name'] }}']"
                                                   :class="errorFor('{{ $f['name'] }}') ? 'is-invalid' : ''">
                                            @break
                                        @case('date')
                                            <input type="date" class="form-control"
                                                   x-model="editing.data['{{ $f['name'] }}']"
                                                   :class="errorFor('{{ $f['name'] }}') ? 'is-invalid' : ''">
                                            @break
                                        @default
                                            <input type="text" class="form-control"
                                                   x-model="editing.data['{{ $f['name'] }}']"
                                                   :class="errorFor('{{ $f['name'] }}') ? 'is-invalid' : ''">
                                    @endswitch

                                    <div class="invalid-feedback d-block" x-text="errorFor('{{ $f['name'] }}')"></div>
                                </div>
                            @endforeach
                        </div>
                    </template>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" @click="close()">Annuler</button>
                    <button class="btn btn-success" @click="save()" :disabled="saving">
                        <i class="fas fa-save me-2"></i>
                        <span x-text="saving ? 'Enregistrement…' : 'Enregistrer'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
        <div class="toast align-items-center text-bg-dark border-0 show"
             x-show="toast" x-transition role="alert">
            <div class="d-flex">
                <div class="toast-body" x-text="toast"></div>
                <button class="btn-close btn-close-white me-2 m-auto" @click="toast=''"></button>
            </div>
        </div>
    </div>
</div>
