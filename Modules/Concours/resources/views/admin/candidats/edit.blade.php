@extends('layouts.admin')

@section('title', 'Modifier — ' . $candidat->nom . ' ' . $candidat->prenom)
@section('page-title', 'Modifier le dossier')

@section('page-actions')
    <a href="{{ route('admin.pages.concours.candidats.show', $candidat->id) }}"
       class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-2"></i>Retour au dossier
    </a>
@endsection

@php
    // Initial snapshot. Carbon dates round-trip as Y-m-d. Booleans stay
    // boolean. We hand this to Alpine via @js() so the JSON-encode handles
    // the escaping correctly.
    $initial = [
        'nom'                      => (string) $candidat->nom,
        'prenom'                   => (string) $candidat->prenom,
        'date_naissance'           => optional($candidat->date_naissance)->format('Y-m-d') ?? '',
        'lieu_naissance'           => (string) $candidat->lieu_naissance,
        'sexe'                     => (string) $candidat->sexe,
        'nationalite_id'           => (string) $candidat->nationalite_id,
        'email'                    => (string) $candidat->email,
        'telephone'                => (string) $candidat->telephone,
        'deja_bac'                 => (bool) $candidat->deja_bac,
        'annee_bac'                => $candidat->annee_bac,
        'serie_bac_id'             => (string) $candidat->serie_bac_id,
        'bac_libelle_libre'        => (string) ($candidat->bac_libelle_libre ?? ''),
        'etablissement_frequente'  => (string) $candidat->etablissement_frequente,
        'section_premier_choix_id' => (string) $candidat->section_premier_choix_id,
        'section_second_choix_id'  => (string) ($candidat->section_second_choix_id ?? ''),
        'centre_id'                => (string) $candidat->centre_id,
    ];

    $updateUrl = route('admin.concours.candidats.update', $candidat->id);
    $backUrl   = route('admin.pages.concours.candidats.show', $candidat->id);
@endphp

@section('content')
<section x-data="{
        initial: @js($initial),
        form:    @js($initial),
        errors:  {},
        loading: false,
        successFields: [],
        globalError: '',
        reason: '',
        isDirty(k) {
            const a = this.initial[k];
            const b = this.form[k];
            if (a === null || a === undefined || a === '') {
                return !(b === null || b === undefined || b === '');
            }
            return String(a) !== String(b ?? '');
        },
        dirtyCount() {
            return Object.keys(this.initial).filter(k => this.isDirty(k)).length;
        },
        cls(k) {
            if (this.errors?.[k]) return 'is-invalid';
            return this.isDirty(k) ? 'border-warning' : '';
        },
        dirtyPayload() {
            const out = { reason: this.reason || null };
            for (const k of Object.keys(this.initial)) {
                if (this.isDirty(k)) out[k] = this.form[k];
            }
            return out;
        },
        reset() {
            this.form = Object.assign({}, this.initial);
            this.errors = {};
            this.successFields = [];
            this.globalError = '';
        },
        async submit() {
            this.loading = true;
            this.errors = {};
            this.globalError = '';
            this.successFields = [];
            const payload = this.dirtyPayload();
            const dirtyKeys = Object.keys(payload).filter(k => k !== 'reason');
            if (dirtyKeys.length === 0) {
                this.globalError = 'Aucune modification à enregistrer.';
                this.loading = false;
                return;
            }
            try {
                const resp = await window.axios.put('{{ $updateUrl }}', payload);
                this.successFields = resp.data?.changed_fields ?? [];
                // Refresh the snapshot so the dirty count drops back to 0.
                this.initial = Object.assign({}, this.form);
                if (this.successFields.length === 0) {
                    this.globalError = 'Aucun champ n\'a réellement changé (valeurs identiques aux existantes).';
                } else {
                    setTimeout(() => { window.location.href = '{{ $backUrl }}'; }, 1200);
                }
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                    this.globalError = e.response.data.message || 'Validation échouée.';
                } else if (e.response?.status === 403) {
                    this.globalError = 'Vous n\'avez pas l\'autorisation de modifier ce dossier.';
                } else {
                    this.globalError = e.response?.data?.message ?? 'Erreur serveur.';
                }
            } finally {
                this.loading = false;
            }
        },
    }"
    x-init="$watch('form.deja_bac', v => { if (!v) form.annee_bac = null })">

    <form @submit.prevent="submit" class="row g-3">
        @csrf

        {{-- Header banner --}}
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center mb-0">
                <i class="fas fa-id-card-clip fs-4 me-3"></i>
                <div class="me-auto">
                    <strong>{{ $candidat->matricule_public }}</strong>
                    — {{ $candidat->nom }} {{ $candidat->prenom }}
                    <span class="status-pill status-pill--{{ $candidat->statut }} ms-2">{{ $candidat->statutLabel() }}</span>
                    <div class="small text-muted">
                        Inscrit{{ $candidat->sexe === 'F' ? 'e' : '' }} le {{ $candidat->created_at->format('d/m/Y H:i') }}
                        — Session <code>{{ $candidat->session?->code }}</code>
                    </div>
                </div>
                <span class="badge bg-light text-dark border" x-text="dirtyCount() + ' modification(s) en attente'"></span>
            </div>
        </div>

        {{-- Left column --}}
        <div class="col-lg-8">

            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Identité</h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Nom</label>
                        <input type="text" class="form-control" x-model="form.nom" :class="cls('nom')">
                        <div class="invalid-feedback d-block" x-text="errors.nom?.[0]"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Prénom</label>
                        <input type="text" class="form-control" x-model="form.prenom" :class="cls('prenom')">
                        <div class="invalid-feedback d-block" x-text="errors.prenom?.[0]"></div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small">Date de naissance</label>
                        <input type="date" class="form-control" x-model="form.date_naissance" :class="cls('date_naissance')">
                        <div class="invalid-feedback d-block" x-text="errors.date_naissance?.[0]"></div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">Lieu de naissance</label>
                        <input type="text" class="form-control" x-model="form.lieu_naissance" :class="cls('lieu_naissance')">
                        <div class="invalid-feedback d-block" x-text="errors.lieu_naissance?.[0]"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Sexe</label>
                        <select class="form-select" x-model="form.sexe" :class="cls('sexe')">
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                        <div class="invalid-feedback d-block" x-text="errors.sexe?.[0]"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">Nationalité</label>
                        <select class="form-select" x-model="form.nationalite_id" :class="cls('nationalite_id')">
                            @foreach($nationalites as $n)
                                <option value="{{ $n->id }}">{{ $n->nom }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" x-text="errors.nationalite_id?.[0]"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Contact</h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Email</label>
                        <input type="email" class="form-control" x-model="form.email" :class="cls('email')">
                        <div class="invalid-feedback d-block" x-text="errors.email?.[0]"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Téléphone</label>
                        <input type="text" class="form-control" x-model="form.telephone" :class="cls('telephone')" placeholder="+241 6X XX XX XX">
                        <div class="invalid-feedback d-block" x-text="errors.telephone?.[0]"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Baccalauréat</h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="dejaBacToggle" x-model="form.deja_bac">
                            <label class="form-check-label small" for="dejaBacToggle">Bac obtenu</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Année</label>
                        <input type="number" min="1980" max="{{ date('Y') }}" class="form-control"
                               x-model.number="form.annee_bac" :class="cls('annee_bac')"
                               :disabled="!form.deja_bac">
                        <div class="invalid-feedback d-block" x-text="errors.annee_bac?.[0]"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Série</label>
                        <select class="form-select" x-model="form.serie_bac_id" :class="cls('serie_bac_id')">
                            @foreach($series as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->nom }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" x-text="errors.serie_bac_id?.[0]"></div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small">Libellé libre (si série « Autre »)</label>
                        <input type="text" class="form-control" x-model="form.bac_libelle_libre" :class="cls('bac_libelle_libre')">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Établissement fréquenté</label>
                        <input type="text" class="form-control" x-model="form.etablissement_frequente" :class="cls('etablissement_frequente')">
                        <div class="invalid-feedback d-block" x-text="errors.etablissement_frequente?.[0]"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Choix de formation</h2></div>
                @php $allowSecond = $candidat->session?->allowsSecondChoice() ?? true; @endphp
                <div class="card-body row g-3">
                    <div class="{{ $allowSecond ? 'col-md-6' : 'col-12' }}">
                        <label class="form-label small">Premier choix</label>
                        <select class="form-select" x-model="form.section_premier_choix_id" :class="cls('section_premier_choix_id')">
                            <option value="">—</option>
                            @foreach($sections as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->nom }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" x-text="errors.section_premier_choix_id?.[0]"></div>
                    </div>
                    @if($allowSecond)
                    <div class="col-md-6">
                        <label class="form-label small">Second choix (optionnel)</label>
                        <select class="form-select" x-model="form.section_second_choix_id" :class="cls('section_second_choix_id')">
                            <option value="">— Aucun —</option>
                            @foreach($sections as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->nom }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" x-text="errors.section_second_choix_id?.[0]"></div>
                    </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label small">Centre d'examen</label>
                        @if($canChangeCentre)
                            <select class="form-select" x-model="form.centre_id" :class="cls('centre_id')">
                                @foreach($centres as $c)
                                    <option value="{{ $c->id }}">{{ $c->selectLabel() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" x-text="errors.centre_id?.[0]"></div>
                        @else
                            <input type="text" class="form-control" disabled
                                   value="{{ $candidat->centre?->selectLabel() }}">
                            <div class="form-text small">
                                <i class="fas fa-lock me-1"></i>
                                Seuls le DG, DE et l'administrateur peuvent déplacer un candidat vers un autre centre.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column --}}
        <div class="col-lg-4">

            <div class="card border-warning mb-3">
                <div class="card-body">
                    <h3 class="h6"><i class="fas fa-folder-open me-2 text-warning"></i>Documents &amp; photo</h3>
                    <p class="small text-muted mb-0">
                        Cette page ne modifie pas les fichiers du dossier
                        (photo d'identité et pièces justificatives). Pour
                        remplacer un document, retournez sur le détail du
                        dossier et utilisez le module documents.
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Justification</h2></div>
                <div class="card-body">
                    <label class="form-label small">Raison de la modification (optionnel, journalisée)</label>
                    <textarea class="form-control" rows="3" x-model="reason"
                              placeholder="Ex : correction prénom suite à pièce d'identité fournie au centre"></textarea>
                    <p class="small text-muted mt-2 mb-0">
                        Toute modification est enregistrée dans l'historique
                        du dossier avec votre nom, l'IP et la liste des
                        champs concernés.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary"
                            :disabled="loading || dirtyCount() === 0">
                        <span x-show="!loading">
                            <i class="fas fa-floppy-disk me-2"></i>Enregistrer
                            <span x-show="dirtyCount() > 0" x-text="'(' + dirtyCount() + ')'"></span>
                        </span>
                        <span x-show="loading"><i class="fas fa-spinner fa-spin me-2"></i>Enregistrement…</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" @click="reset"
                            :disabled="loading">
                        <i class="fas fa-rotate-left me-2"></i>Annuler les modifications
                    </button>
                    <p x-show="globalError" x-text="globalError" class="small text-danger mb-0"></p>
                    <p x-show="successFields.length" class="small text-success mb-0">
                        <i class="fas fa-check-circle me-1"></i>
                        <span x-text="successFields.length + ' champ(s) mis à jour : ' + successFields.join(', ')"></span>
                    </p>
                </div>
            </div>
        </div>
    </form>
</section>
@endsection
