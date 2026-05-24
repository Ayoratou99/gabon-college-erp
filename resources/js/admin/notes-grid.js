/**
 * Alpine component for the per-épreuve note entry grid.
 *
 * Data shape:
 *   epreuveId        — the épreuve being graded
 *   noteMax          — upper bound used for inline validation
 *   candidats        — [{ id, nom, prenom, matricule_public, note: { valeur, absent, locked, commentaire } | null }]
 *
 * Behaviour:
 *   - Tracks `dirty` per row; toolbar disables save until something changed.
 *   - Out-of-range values flagged client-side with `.is-invalid` (server
 *     re-validates either way).
 *   - Save POSTs to /api/admin/concours/notes/batch, supports `?lock=true`.
 *   - On success, freshly-saved rows clear their dirty flag and pick up
 *     server-returned `locked` state.
 */
export function notesGrid({ epreuveId, noteMax, candidats }) {
    return {
        epreuveId,
        noteMax,
        rows: candidats.map(c => ({
            id:                c.id,
            nom:               c.nom,
            prenom:            c.prenom,
            matricule_public:  c.matricule_public,
            valeur:            c.note?.valeur ?? null,
            absent:            !!c.note?.absent,
            locked:            !!c.note?.locked,
            commentaire:       c.note?.commentaire ?? '',
            dirty:             false,
        })),
        lock: false,
        saving: false,
        errors: {},
        statusMsg: '',

        markDirty(row) {
            row.dirty = true;
            this.errors[row.id] = this.validate(row);
            this.statusMsg = '';
        },

        validate(row) {
            if (row.absent) return null;
            if (row.valeur === '' || row.valeur === null) return null;
            const v = Number(row.valeur);
            if (Number.isNaN(v))          return 'Valeur invalide';
            if (v < 0)                    return 'Doit être ≥ 0';
            if (v > Number(this.noteMax)) return `Doit être ≤ ${this.noteMax}`;
            return null;
        },

        get dirtyCount() {
            return this.rows.filter(r => r.dirty).length;
        },

        get hasErrors() {
            return Object.values(this.errors).some(Boolean);
        },

        async save() {
            if (this.hasErrors) return;
            this.saving = true;

            const entries = this.rows
                .filter(r => r.dirty)
                .map(r => ({
                    candidat_id: r.id,
                    valeur:      r.absent ? null : (r.valeur === '' ? null : Number(r.valeur)),
                    absent:      r.absent,
                    commentaire: r.commentaire || null,
                }));

            try {
                const { data } = await window.axios.post('/api/admin/concours/notes/batch', {
                    epreuve_id: this.epreuveId,
                    entries,
                    lock: this.lock,
                });
                this.rows.forEach(r => { if (r.dirty) { r.dirty = false; r.locked = this.lock; } });
                this.statusMsg = `${data.saved} note(s) enregistrée(s).`;
            } catch (err) {
                this.statusMsg = err.response?.data?.message
                    ?? 'Erreur lors de l\'enregistrement.';
            } finally {
                this.saving = false;
            }
        },
    };
}
