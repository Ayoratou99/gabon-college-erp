@php
    $val = fn (string $k, mixed $default = '') => old($k, $draft[$k] ?? $default);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" value="{{ $val('email') }}" class="form-control" required maxlength="191">
        <div class="form-text small">Vous recevrez votre matricule et les notifications du concours à cette adresse.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Téléphone <span class="text-danger">*</span></label>
        <input type="tel" name="telephone" value="{{ $val('telephone') }}" class="form-control" required placeholder="+241 6X XX XX XX" pattern="[+0-9 .\-]{6,30}">
        <div class="form-text small">Format accepté&nbsp;: chiffres, espaces, +, -, point. Ex&nbsp;: +241 06 12 34 56.</div>
    </div>
</div>

<p class="small text-muted mt-3 mb-0">
    <i class="fas fa-shield-halved me-1"></i>
    Un seul dossier par email et par téléphone pour ce concours&nbsp;: si vous avez déjà commencé une inscription,
    utilisez plutôt <a href="{{ route('concours.public.lookup.form') }}">« Récupérer mon dossier »</a>.
</p>
