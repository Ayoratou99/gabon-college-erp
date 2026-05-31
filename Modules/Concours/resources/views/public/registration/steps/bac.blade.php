@php
    $val = fn (string $k, mixed $default = '') => old($k, $draft[$k] ?? $default);
    $dejaBac = (bool) $val('deja_bac', false);
@endphp

<div x-data="{ dejaBac: {{ $dejaBac ? 'true' : 'false' }} }" class="row g-3">

    <div class="col-md-4">
        <label class="form-label">Avez-vous le BAC ? <span class="text-danger">*</span></label>
        <div class="d-flex gap-3">
            <div class="form-check">
                <input id="bac-oui" class="form-check-input" type="radio" name="deja_bac" value="1" @checked($dejaBac) x-model="dejaBac" @click="dejaBac = true">
                <label class="form-check-label" for="bac-oui">Oui</label>
            </div>
            <div class="form-check">
                <input id="bac-non" class="form-check-input" type="radio" name="deja_bac" value="0" @checked(! $dejaBac) x-model="dejaBac" @click="dejaBac = false">
                <label class="form-check-label" for="bac-non">Pas encore</label>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Année d'obtention</label>
        <input type="number" name="annee_bac" value="{{ $val('annee_bac') }}" class="form-control"
               min="1980" max="{{ date('Y') }}" :disabled="!dejaBac" :required="dejaBac"
               placeholder="Ex&nbsp;: 2024">
    </div>

    <div class="col-md-4">
        <label class="form-label">Série du BAC <span class="text-danger">*</span></label>
        <select name="serie_bac_id" class="form-select" required>
            <option value="">— Sélectionner —</option>
            @foreach($series as $s)
                <option value="{{ $s->id }}" @selected($val('serie_bac_id') === $s->id)>{{ $s->code }} — {{ $s->nom }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Libellé libre (si « Autre » série)</label>
        <input type="text" name="bac_libelle_libre" value="{{ $val('bac_libelle_libre') }}" class="form-control" maxlength="191">
    </div>

    <div class="col-12">
        <label class="form-label">Établissement fréquenté <span class="text-danger">*</span></label>
        <input type="text" name="etablissement_frequente" value="{{ $val('etablissement_frequente') }}" class="form-control" required maxlength="191">
    </div>
</div>
