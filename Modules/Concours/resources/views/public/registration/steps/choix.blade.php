@php
    $val = fn (string $k, mixed $default = '') => old($k, $draft[$k] ?? $default);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Premier choix de formation <span class="text-danger">*</span></label>
        <select name="section_premier_choix_id" class="form-select" required>
            <option value="">— Sélectionner —</option>
            @foreach($sections as $s)
                <option value="{{ $s->id }}" @selected($val('section_premier_choix_id') === $s->id)>{{ $s->code }} — {{ $s->nom }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Second choix (optionnel)</label>
        <select name="section_second_choix_id" class="form-select">
            <option value="">— Aucun —</option>
            @foreach($sections as $s)
                <option value="{{ $s->id }}" @selected($val('section_second_choix_id') === $s->id)>{{ $s->code }} — {{ $s->nom }}</option>
            @endforeach
        </select>
        <div class="form-text small">Doit être différent du premier choix. Vous pouvez le laisser vide.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Centre d'examen <span class="text-danger">*</span></label>
        <select name="centre_id" class="form-select" required>
            <option value="">— Sélectionner —</option>
            @foreach($centres as $c)
                <option value="{{ $c->id }}" @selected($val('centre_id') === $c->id)>
                    {{ $c->selectLabel() }}
                </option>
            @endforeach
        </select>
        <div class="form-text small">
            <i class="fas fa-circle-info me-1"></i>
            Le choix du centre est définitif&nbsp;: vous composerez sur place le jour de l'épreuve.
        </div>
    </div>
</div>
