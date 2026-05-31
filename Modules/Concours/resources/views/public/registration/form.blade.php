@extends('layouts.public')

@section('title', 'Inscription au concours')

@section('content')
<section class="page-hero">
    <div class="container">
        <h1><i class="fas fa-pen-to-square me-2"></i>Inscription au concours</h1>
        <p>
            Session <strong>{{ $session->libelle }}</strong> &middot;
            Épreuve&nbsp;: <strong>{{ $session->date_concours->format('d/m/Y') }}</strong> &middot;
            Frais&nbsp;: <strong>{{ number_format($session->fraisInscription(), 0, ',', ' ') }} FCFA</strong>
        </p>
    </div>
</section>

<section class="container py-5">

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong><i class="fas fa-circle-exclamation me-2"></i>Quelques champs ont besoin d'attention&nbsp;:</strong>
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('concours.inscription.submit') }}" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- ---- Identité ---- --}}
        <fieldset class="form-card mb-4">
            <legend class="h5">Identité</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" value="{{ old('nom') }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="prenom" value="{{ old('prenom') }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de naissance *</label>
                    <input type="date" name="date_naissance" value="{{ old('date_naissance') }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lieu de naissance *</label>
                    <input type="text" name="lieu_naissance" value="{{ old('lieu_naissance') }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sexe *</label>
                    <select name="sexe" class="form-select" required>
                        <option value="M" @selected(old('sexe') === 'M')>M</option>
                        <option value="F" @selected(old('sexe') === 'F')>F</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nationalité *</label>
                    <select name="nationalite_id" class="form-select" required>
                        @foreach ($nationalites as $n)
                            <option value="{{ $n->id }}" @selected(old('nationalite_id') === $n->id)>{{ $n->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone *</label>
                    <input type="tel" name="telephone" value="{{ old('telephone') }}" class="form-control" placeholder="+241..." required>
                </div>
            </div>
        </fieldset>

        {{-- ---- Baccalauréat ---- --}}
        <fieldset class="form-card mb-4">
            <legend class="h5">Baccalauréat</legend>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Avez-vous déjà le BAC ? *</label>
                    <select name="deja_bac" id="deja_bac" class="form-select" required>
                        <option value="0" @selected(old('deja_bac') === '0')>Non</option>
                        <option value="1" @selected(old('deja_bac') === '1')>Oui</option>
                    </select>
                </div>
                <div class="col-md-4" id="annee_bac_wrap" style="{{ old('deja_bac') === '1' ? '' : 'display:none' }}">
                    <label class="form-label">Année d'obtention</label>
                    <input type="number" name="annee_bac" value="{{ old('annee_bac') }}" min="1980" max="{{ date('Y') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Série du BAC *</label>
                    <select name="serie_bac_id" class="form-select" required>
                        @foreach ($series as $s)
                            <option value="{{ $s->id }}" @selected(old('serie_bac_id') === $s->id)>{{ $s->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Établissement fréquenté *</label>
                    <input type="text" name="etablissement_frequente" value="{{ old('etablissement_frequente') }}" class="form-control" required>
                </div>
            </div>
        </fieldset>

        {{-- ---- Choix ---- --}}
        <fieldset class="form-card mb-4">
            <legend class="h5">Choix de formation & centre</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Premier choix *</label>
                    <select name="section_premier_choix_id" class="form-select" required>
                        @foreach ($sections as $sec)
                            <option value="{{ $sec->id }}" @selected(old('section_premier_choix_id') === $sec->id)>{{ $sec->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Second choix (facultatif)</label>
                    <select name="section_second_choix_id" class="form-select">
                        <option value="">— aucun —</option>
                        @foreach ($sections as $sec)
                            <option value="{{ $sec->id }}" @selected(old('section_second_choix_id') === $sec->id)>{{ $sec->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Centre d'examen *</label>
                    <select name="centre_id" class="form-select" required>
                        @foreach ($centres as $c)
                            <option value="{{ $c->id }}" @selected(old('centre_id') === $c->id)>{{ $c->nom }} @if($c->ville)— {{ $c->ville }}@endif</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </fieldset>

        {{-- ---- Photo + documents ---- --}}
        <fieldset class="form-card mb-4">
            <legend class="h5">Pièces justificatives</legend>
            <div class="mb-3">
                <label class="form-label">Photo d'identité * <small class="text-muted">(jpg/png, max 4 Mo)</small></label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="form-control" required>
            </div>
            @foreach ($documents as $doc)
                <div class="mb-3">
                    <label class="form-label">
                        {{ $doc->libelle }} @if($doc->obligatoire)*@endif
                        <small class="text-muted">
                            ({{ implode('/', (array) $doc->formats_acceptes) }}, max {{ $doc->taille_max_ko }} Ko)
                        </small>
                    </label>
                    <input type="file" name="documents[{{ $doc->code }}]"
                           accept=".{{ implode(',.', (array) $doc->formats_acceptes) }}"
                           class="form-control"
                           @if($doc->obligatoire) required @endif>
                </div>
            @endforeach
        </fieldset>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane me-2"></i> Envoyer mon dossier
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
