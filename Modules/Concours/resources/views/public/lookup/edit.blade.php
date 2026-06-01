@extends('layouts.public')

@section('title', 'Modifier mon dossier')

@section('content')
<section class="container py-5">
    <h1 class="mb-1">Modifier mon dossier</h1>
    <p class="text-muted mb-2">
        Matricule : <code>{{ $candidat->matricule_public }}</code>
    </p>

    @if ($candidat->motifsRejet->isNotEmpty())
        <div class="alert alert-warning">
            <strong>Motif(s) du rejet :</strong>
            <ul class="mb-0 mt-2">
                @foreach($candidat->motifsRejet as $m)
                    <li>{{ $m->motif }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('concours.public.modify.submit', $token) }}"
          enctype="multipart/form-data" novalidate>
        @csrf

        {{-- ----- Identité ----- --}}
        <fieldset class="card mb-4"><div class="card-body">
            <legend class="h5">Identité</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" value="{{ old('nom', $candidat->nom) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="prenom" value="{{ old('prenom', $candidat->prenom) }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de naissance *</label>
                    <input type="date" name="date_naissance"
                           value="{{ old('date_naissance', $candidat->date_naissance?->format('Y-m-d')) }}"
                           class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lieu de naissance *</label>
                    <input type="text" name="lieu_naissance" value="{{ old('lieu_naissance', $candidat->lieu_naissance) }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sexe *</label>
                    <select name="sexe" class="form-select" required>
                        <option value="M" @selected(old('sexe', $candidat->sexe) === 'M')>M</option>
                        <option value="F" @selected(old('sexe', $candidat->sexe) === 'F')>F</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nationalité *</label>
                    <select name="nationalite_id" class="form-select" required>
                        @foreach ($nationalites as $n)
                            <option value="{{ $n->id }}" @selected(old('nationalite_id', $candidat->nationalite_id) === $n->id)>{{ $n->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $candidat->email) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone *</label>
                    <input type="tel" name="telephone" value="{{ old('telephone', $candidat->telephone) }}" class="form-control" placeholder="077056138" required>
                </div>
            </div>
        </div></fieldset>

        {{-- ----- Bac ----- --}}
        <fieldset class="card mb-4"><div class="card-body">
            <legend class="h5">Baccalauréat</legend>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Avez-vous déjà le BAC ? *</label>
                    <select name="deja_bac" id="deja_bac" class="form-select" required>
                        <option value="0" @selected(! old('deja_bac', $candidat->deja_bac))>Non</option>
                        <option value="1" @selected(old('deja_bac', $candidat->deja_bac))>Oui</option>
                    </select>
                </div>
                <div class="col-md-4" id="annee_bac_wrap" style="{{ old('deja_bac', $candidat->deja_bac) ? '' : 'display:none' }}">
                    <label class="form-label">Année d'obtention</label>
                    <input type="number" name="annee_bac" value="{{ old('annee_bac', $candidat->annee_bac) }}"
                           min="1980" max="{{ date('Y') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Série du BAC *</label>
                    <select name="serie_bac_id" class="form-select" required>
                        @foreach ($series as $s)
                            <option value="{{ $s->id }}" @selected(old('serie_bac_id', $candidat->serie_bac_id) === $s->id)>{{ $s->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Établissement fréquenté *</label>
                    <input type="text" name="etablissement_frequente"
                           value="{{ old('etablissement_frequente', $candidat->etablissement_frequente) }}"
                           class="form-control" required>
                </div>
            </div>
        </div></fieldset>

        {{-- ----- Choix ----- --}}
        <fieldset class="card mb-4"><div class="card-body">
            <legend class="h5">Choix de formation &amp; centre</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Premier choix *</label>
                    <select name="section_premier_choix_id" class="form-select" required>
                        @foreach ($sections as $sec)
                            <option value="{{ $sec->id }}" @selected(old('section_premier_choix_id', $candidat->section_premier_choix_id) === $sec->id)>{{ $sec->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Second choix (facultatif)</label>
                    <select name="section_second_choix_id" class="form-select">
                        <option value="">— aucun —</option>
                        @foreach ($sections as $sec)
                            <option value="{{ $sec->id }}" @selected(old('section_second_choix_id', $candidat->section_second_choix_id) === $sec->id)>{{ $sec->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Centre d'examen *</label>
                    <select name="centre_id" class="form-select" required>
                        @foreach ($centres as $c)
                            <option value="{{ $c->id }}" @selected(old('centre_id', $candidat->centre_id) === $c->id)>
                                {{ $c->nom }}@if($c->ville) — {{ $c->ville }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div></fieldset>

        {{-- ----- Pieces (replace only if modified) ----- --}}
        <fieldset class="card mb-4"><div class="card-body">
            <legend class="h5">Pièces justificatives</legend>
            <p class="text-muted small">
                Laissez vide les champs que vous ne souhaitez pas modifier — les anciennes pièces seront conservées.
            </p>

            <div class="mb-3">
                <label class="form-label">Photo d'identité <small class="text-muted">(remplace l'ancienne si fournie)</small></label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="form-control">
            </div>

            @php $existingDocCodes = $candidat->documents->pluck('documentRequis.code')->filter()->all(); @endphp
            @foreach ($documents as $doc)
                @php $alreadyHave = in_array($doc->code, $existingDocCodes, true); @endphp
                <div class="mb-3">
                    <label class="form-label">
                        {{ $doc->libelle }}
                        @if($alreadyHave)
                            <span class="badge bg-success-subtle text-success ms-2">déjà transmis</span>
                        @endif
                        <small class="text-muted">
                            ({{ implode('/', (array) $doc->formats_acceptes) }}, max {{ $doc->taille_max_ko }} Ko)
                        </small>
                    </label>
                    <input type="file" name="documents[{{ $doc->code }}]"
                           accept=".{{ implode(',.', (array) $doc->formats_acceptes) }}"
                           class="form-control">
                </div>
            @endforeach
        </div></fieldset>

        {{-- ----- Notes pour le validateur ----- --}}
        <fieldset class="card mb-4"><div class="card-body">
            <legend class="h5">Précisions (optionnel)</legend>
            <textarea name="reason" rows="3" class="form-control"
                      placeholder="Indiquez brièvement ce que vous avez corrigé...">{{ old('reason') }}</textarea>
        </div></fieldset>

        <div class="d-flex justify-content-between">
            <a href="{{ route('concours.public.lookup.form') }}" class="btn btn-outline-secondary">
                Annuler
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane me-2"></i>Renvoyer mon dossier
            </button>
        </div>
    </form>
</section>

@push('scripts')
<script>
document.getElementById('deja_bac').addEventListener('change', e => {
    document.getElementById('annee_bac_wrap').style.display = e.target.value === '1' ? '' : 'none';
});
</script>
@endpush
@endsection
