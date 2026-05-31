@extends('layouts.admin')

@section('title', 'Pièces requises × Sections')
@section('page-title', 'Pièces requises × Sections')

@section('page-actions')
    <a href="{{ route('admin.referentiels.index', ['slug' => 'documents']) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Pièces (référentiel)
    </a>
@endsection

@section('content')
<section x-data="docRequisMatrix({
        toggleUrl: '{{ route('admin.pages.concours.document_requis_sections.toggle') }}',
        csrf:      '{{ csrf_token() }}',
        initial:   @js($linksByDoc),
    })">

    <div class="card mb-3">
        <div class="card-body small text-muted">
            <p class="mb-2">
                <i class="fas fa-circle-info me-1 text-primary"></i>
                <strong>Comment ça marche&nbsp;?</strong> Cocher une case lie une pièce à une section&nbsp;:
                la pièce ne sera demandée qu'aux candidats qui choisissent cette section en premier voeu.
            </p>
            <ul class="mb-0">
                <li><strong>Aucune case cochée</strong> sur une ligne&nbsp;: pièce <em>universelle</em>,
                    demandée à <strong>tous</strong> les candidats.</li>
                <li><strong>Au moins une case cochée</strong>&nbsp;: pièce <em>spécifique</em>,
                    invisible pour les candidats hors des sections cochées.</li>
                <li>Le drapeau <strong>obligatoire</strong> (édité dans le référentiel Pièces)
                    décide si la pièce est requise à l'inscription ou simplement proposée.</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">
                <i class="fas fa-table-cells text-primary me-2"></i>Matrice Pièces × Sections
            </h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 22rem;">Pièce</th>
                        <th class="text-center small text-uppercase">État</th>
                        @foreach($sections as $s)
                            <th class="text-center small" style="writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; min-width: 2.6rem;">
                                <code>{{ $s->code }}</code> {{ $s->nom }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $d)
                        <tr>
                            <td>
                                <strong>{{ $d->libelle }}</strong>
                                <code class="small text-muted ms-2">{{ $d->code }}</code>
                                @if($d->obligatoire)
                                    <span class="badge bg-danger ms-1">
                                        <i class="fas fa-asterisk me-1"></i>obligatoire
                                    </span>
                                @else
                                    <span class="badge bg-secondary ms-1">optionnelle</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge"
                                      :class="isUniversal('{{ $d->id }}') ? 'bg-info' : 'bg-warning text-dark'"
                                      x-text="isUniversal('{{ $d->id }}') ? 'Universelle' : 'Spécifique'">
                                </span>
                            </td>
                            @foreach($sections as $s)
                                <td class="text-center">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           :checked="isLinked('{{ $d->id }}', '{{ $s->id }}')"
                                           :disabled="busyCell === '{{ $d->id }}|{{ $s->id }}'"
                                           @change="toggle('{{ $d->id }}', '{{ $s->id }}', $event.target.checked)">
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ 2 + $sections->count() }}" class="text-center text-muted py-4">Aucune pièce active.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <template x-if="lastError">
            <div class="card-footer text-danger small">
                <i class="fas fa-circle-exclamation me-1"></i>
                <span x-text="lastError"></span>
            </div>
        </template>
    </div>
</section>

@push('scripts')
<script>
    function docRequisMatrix(config) {
        return {
            links: { ...config.initial },  // { docId: [sectionId, ...] }
            busyCell: null,
            lastError: '',

            isLinked(docId, sectionId) {
                return (this.links[docId] ?? []).includes(sectionId);
            },
            isUniversal(docId) {
                return (this.links[docId] ?? []).length === 0;
            },
            async toggle(docId, sectionId, target) {
                this.busyCell = docId + '|' + sectionId;
                this.lastError = '';
                try {
                    const resp = await fetch(config.toggleUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            document_requis_id: docId,
                            section_id: sectionId,
                            linked: target,
                        }),
                    });
                    const data = await resp.json();
                    if (!resp.ok || !data.ok) {
                        throw new Error(data.error || data.message || 'Échec de la sauvegarde.');
                    }
                    // Update local state from the authoritative server reply.
                    const current = new Set(this.links[docId] ?? []);
                    if (data.linked) current.add(sectionId);
                    else current.delete(sectionId);
                    this.links[docId] = [...current];
                } catch (e) {
                    this.lastError = e.message;
                    // Revert the optimistic checkbox flip.
                    // (Browsers will repaint via the :checked binding on next tick.)
                } finally {
                    this.busyCell = null;
                }
            },
        };
    }
</script>
@endpush
@endsection
