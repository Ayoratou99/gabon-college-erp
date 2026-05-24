/**
 * Alpine component driving the 3-step admission selection wizard.
 *
 *   1. Recompute moyennes for the active session.
 *   2. Review the per-section proposal: deselect rows or change orientation.
 *   3. Confirm + publish (refused by the server if already published — the
 *      partial unique index on `result_publications` is the last line).
 */
export function selectionWizard({ sessionId, sections }) {
    return {
        sessionId,
        sections,                  // [{ id, code, nom }]
        step: 1,
        loading: false,
        message: '',
        proposal: {},              // { sectionId: { section, candidats: [...] } }
        chosen: {},                // { candidatId: { kept: bool, orientationSectionId: string } }
        communique: '',

        async recompute() {
            this.loading = true;
            this.message = '';
            try {
                await window.axios.post('/api/admin/concours/notes/recompute', {
                    concours_session_id: this.sessionId,
                });
                await this.fetchProposal();
                this.step = 2;
            } catch (e) {
                this.message = e.response?.data?.message ?? 'Erreur de recalcul.';
            } finally {
                this.loading = false;
            }
        },

        async fetchProposal() {
            const { data } = await window.axios.get('/api/admin/concours/selection/suggest', {
                params: { concours_session_id: this.sessionId },
            });
            this.proposal = data;
            this.chosen = {};
            for (const sectionId in data) {
                for (const c of data[sectionId].candidats) {
                    this.chosen[c.id] = {
                        kept: true,
                        orientationSectionId: sectionId,
                    };
                }
            }
        },

        toggleKept(candidatId) {
            this.chosen[candidatId].kept = !this.chosen[candidatId].kept;
        },

        get totalChosen() {
            return Object.values(this.chosen).filter(x => x.kept).length;
        },

        async confirm() {
            this.loading = true;
            this.message = '';
            const admis = Object.entries(this.chosen)
                .filter(([, v]) => v.kept)
                .map(([candidat_id, v]) => ({
                    candidat_id,
                    orientation_section_id: v.orientationSectionId,
                }));

            try {
                const { data } = await window.axios.post('/api/admin/concours/selection/confirm', {
                    concours_session_id: this.sessionId,
                    admis,
                    communique: this.communique || null,
                });
                this.message = `Publication créée — ${data.total_admis} admis.`;
                this.step = 3;
            } catch (e) {
                this.message = e.response?.data?.error
                    ?? e.response?.data?.message
                    ?? 'Erreur lors de la publication.';
            } finally {
                this.loading = false;
            }
        },
    };
}
