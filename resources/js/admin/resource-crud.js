/**
 * Generic create/edit/delete frontend for the registry-backed admin pages
 * (Référentiels, Structure académique). Pairs with the existing
 * ResourceCrudController JSON API and the server-side DataTables endpoint.
 *
 * Each editable resource declares its own `fields` schema in Blade, e.g.:
 *   [
 *     { name: 'code',          label: 'Code',    type: 'text', required: true },
 *     { name: 'nom',           label: 'Nom',     type: 'text', required: true },
 *     { name: 'active',        label: 'Actif',   type: 'boolean' },
 *     { name: 'display_order', label: 'Ordre',   type: 'integer' },
 *   ]
 */
export function resourceCrud({ apiBase, fields, tableId, dtUrl, dtColumns, dtOrder, uploadUrl }) {
    return {
        apiBase,
        fields,
        tableId,
        uploadUrl: uploadUrl ?? null,
        editing: null,         // null = closed; object = open (create or edit)
        saving: false,
        uploading: false,      // an image_url upload is in flight
        errors: {},
        toast: '',
        dt: null,

        init() {
            // eslint-disable-next-line no-undef
            this.dt = window.cukDataTable(`#${this.tableId}`, {
                url: dtUrl,
                order: dtOrder ?? [],
                columns: [
                    ...dtColumns,
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: (data, type, row) => {
                            return `
                                <button class="btn btn-sm btn-outline-primary me-1" data-edit="${row.id}">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-delete="${row.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                        },
                    },
                ],
            });

            // Event delegation: row buttons trigger Alpine methods.
            document.getElementById(this.tableId).addEventListener('click', (e) => {
                const editId = e.target.closest('[data-edit]')?.dataset.edit;
                if (editId) { this.open(editId); return; }
                const delId = e.target.closest('[data-delete]')?.dataset.delete;
                if (delId)  { this.destroy(delId); }
            });
        },

        empty() {
            const init = {};
            for (const f of this.fields) {
                // Explicit `default` on the field schema wins; otherwise pick
                // a sensible type-aware empty. Booleans default to FALSE so
                // opt-in flags (e.g. ouvert_au_concours) don't accidentally
                // get turned on for every new entry.
                if (Object.prototype.hasOwnProperty.call(f, 'default')) {
                    init[f.name] = f.default;
                } else if (f.type === 'boolean') init[f.name] = false;
                else if (f.type === 'integer')   init[f.name] = 0;
                else                             init[f.name] = '';
            }
            return init;
        },

        create() {
            this.errors = {};
            this.editing = { id: null, data: this.empty() };
        },

        async open(id) {
            this.errors = {};
            try {
                const { data } = await window.axios.get(`${this.apiBase}/${id}`);
                const merged = { ...this.empty(), ...data };
                this.editing = { id, data: merged };
            } catch (e) {
                this.notify('Impossible de charger la ressource.');
            }
        },

        close() { this.editing = null; this.errors = {}; },

        async save() {
            this.saving = true;
            this.errors = {};
            try {
                if (this.editing.id) {
                    await window.axios.put(`${this.apiBase}/${this.editing.id}`, this.editing.data);
                    this.notify('Modifications enregistrées.');
                } else {
                    await window.axios.post(this.apiBase, this.editing.data);
                    this.notify('Ressource créée.');
                }
                this.close();
                this.dt?.ajax.reload(null, false);
            } catch (e) {
                const status = e.response?.status;
                if (status === 422) {
                    this.errors = e.response.data?.errors ?? {};
                    this.notify('Validation échouée.');
                } else {
                    this.notify(e.response?.data?.message ?? 'Erreur lors de l’enregistrement.');
                }
            } finally {
                this.saving = false;
            }
        },

        /**
         * Upload an image for an `image_url` field. Sends the file to
         * `uploadUrl` as multipart/form-data and writes the returned path back
         * into editing.data[fieldName] — the save() payload still carries a
         * plain string, so the JSON CRUD endpoint is unchanged.
         */
        async uploadImage(fieldName, event) {
            const input = event.target;
            const file = input.files && input.files[0];
            if (!file) return;
            if (!this.uploadUrl) { this.notify('Téléversement indisponible.'); return; }

            this.uploading = true;
            try {
                const fd = new FormData();
                fd.append('image', file);
                const { data } = await window.axios.post(this.uploadUrl, fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                if (this.editing) this.editing.data[fieldName] = data.url;
            } catch (e) {
                if (e.response?.status === 422) {
                    this.notify(e.response.data?.errors?.image?.[0] ?? 'Image invalide.');
                } else {
                    this.notify(e.response?.data?.message ?? 'Échec du téléversement.');
                }
            } finally {
                this.uploading = false;
                input.value = '';   // let the admin re-pick the same file
            }
        },

        async destroy(id) {
            if (!confirm('Supprimer cette entrée ?')) return;
            try {
                await window.axios.delete(`${this.apiBase}/${id}`);
                this.notify('Entrée supprimée.');
                this.dt?.ajax.reload(null, false);
            } catch (e) {
                this.notify(e.response?.data?.message ?? 'Suppression refusée.');
            }
        },

        notify(msg) {
            this.toast = msg;
            setTimeout(() => { if (this.toast === msg) this.toast = ''; }, 3500);
        },

        errorFor(name) {
            const arr = this.errors[name];
            return Array.isArray(arr) && arr.length ? arr[0] : '';
        },
    };
}
