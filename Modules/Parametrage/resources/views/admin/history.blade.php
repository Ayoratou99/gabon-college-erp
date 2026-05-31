@extends('layouts.admin')

@section('title', 'Paramétrage — historique')
@section('page-title', 'Historique des modifications')

@section('page-actions')
    <a href="{{ route('admin.pages.parametrage.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Paramètres
    </a>
@endsection

@section('content')
<div>

    {{-- Same nav-pills as the index, with an extra "Historique" tab. --}}
    <ul class="nav nav-pills settings-tabs mb-4 flex-wrap">
        @foreach($categories as $code => $label)
            <li class="nav-item">
                <a class="nav-link"
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
            <a class="nav-link active" href="{{ route('admin.pages.parametrage.history') }}">
                <i class="fas fa-clock-rotate-left me-2"></i>Historique
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">
                <i class="fas fa-clock-rotate-left text-primary me-2"></i>
                100 derniers changements de paramètres
            </h2>
            <small class="text-muted">{{ $entries->count() }} entrée(s)</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Quand</th>
                        <th>Paramètre</th>
                        <th>Catégorie</th>
                        <th>Ancienne valeur</th>
                        <th>Nouvelle valeur</th>
                        <th>Auteur</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $e)
                        <tr>
                            <td class="small text-muted text-nowrap">
                                {{ $e->changed_at?->format('d/m/Y H:i:s') ?? '—' }}
                            </td>
                            <td>
                                <strong>{{ $e->setting?->label ?: $e->setting?->key }}</strong><br>
                                <code class="small text-muted">{{ $e->setting?->key }}</code>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $e->setting?->category }}</span>
                            </td>
                            <td class="small text-muted" style="max-width:18ch;">
                                @if($e->setting?->is_encrypted)
                                    <em>[chiffrée]</em>
                                @else
                                    <code class="d-inline-block text-truncate" style="max-width:100%;" title="{{ $e->old_value }}">{{ \Illuminate\Support\Str::limit((string) $e->old_value, 40) }}</code>
                                @endif
                            </td>
                            <td class="small" style="max-width:18ch;">
                                @if($e->setting?->is_encrypted)
                                    <em>[chiffrée]</em>
                                @else
                                    <code class="d-inline-block text-truncate" style="max-width:100%;" title="{{ $e->new_value }}">{{ \Illuminate\Support\Str::limit((string) $e->new_value, 40) }}</code>
                                @endif
                            </td>
                            <td class="small">
                                @if($e->user)
                                    {{ $e->user->prenom }} {{ $e->user->nom }}<br>
                                    <span class="text-muted">{{ $e->user->email }}</span>
                                @else
                                    <span class="text-muted">Console / système</span>
                                @endif
                            </td>
                            <td class="small text-muted"><code>{{ $e->ip_address ?? '—' }}</code></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-folder-open fs-2 d-block mb-2"></i>
                                Aucun changement enregistré.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Les valeurs chiffrées (clés API, secrets) ne sont pas affichées en clair — seul l'évènement de modification est tracé.
        </div>
    </div>
</div>
@endsection
