@php
    // Helper: pull a value, preferring old() (validation re-render) then the
    // draft (cross-step persistence) then nothing.
    $val = fn (string $k, mixed $default = '') => old($k, $draft[$k] ?? $default);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nom <span class="text-danger">*</span></label>
        <input type="text" name="nom" value="{{ $val('nom') }}" class="form-control" required maxlength="100">
    </div>
    <div class="col-md-6">
        <label class="form-label">Prénom <span class="text-danger">*</span></label>
        <input type="text" name="prenom" value="{{ $val('prenom') }}" class="form-control" required maxlength="100">
    </div>

    <div class="col-md-4">
        <label class="form-label">Date de naissance <span class="text-danger">*</span></label>
        <input type="date" name="date_naissance" value="{{ $val('date_naissance') }}" class="form-control" required>
    </div>
    <div class="col-md-5">
        <label class="form-label">Lieu de naissance <span class="text-danger">*</span></label>
        <input type="text" name="lieu_naissance" value="{{ $val('lieu_naissance') }}" class="form-control" required maxlength="100">
    </div>
    <div class="col-md-3">
        <label class="form-label">Sexe <span class="text-danger">*</span></label>
        <select name="sexe" class="form-select" required>
            <option value="M" @selected($val('sexe') === 'M')>Masculin</option>
            <option value="F" @selected($val('sexe') === 'F')>Féminin</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Nationalité <span class="text-danger">*</span></label>
        <select name="nationalite_id" class="form-select" required>
            <option value="">— Sélectionner —</option>
            @foreach($nationalites as $n)
                <option value="{{ $n->id }}" @selected($val('nationalite_id') === $n->id)>{{ $n->nom }}</option>
            @endforeach
        </select>
    </div>
</div>
