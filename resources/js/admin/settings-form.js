/**
 * Alpine components powering the Parametrage admin page.
 *
 *   settingsForm()  — outer container, holds the toast queue.
 *   settingsEditor({id, key, type, value, encrypted, hidden})
 *                   — one per setting card; tracks dirty state, validates
 *                     JSON locally, PUTs to /admin/parametrage/{id}.
 */
export function settingsForm() {
    return {
        toasts: [],
        notify(msg) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, msg });
            setTimeout(() => this.dismissToast(id), 3500);
        },
        dismissToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
    };
}

export function settingsEditor(initial) {
    return {
        id: initial.id,
        key: initial.key,
        type: initial.type,
        editing: { value: initial.value },
        textValue: initial.type === 'json'
            ? JSON.stringify(initial.value, null, 2)
            : (initial.value ?? ''),
        original: structuredClone(initial.value),
        dirty: false,
        saving: false,
        msg: '',
        msgClass: 'text-muted',
        jsonError: '',

        markDirty() {
            this.dirty = true;
            this.msg = '';
        },

        validateJson() {
            if (this.type !== 'json') { this.jsonError = ''; return; }
            try {
                JSON.parse(this.textValue || 'null');
                this.jsonError = '';
            } catch (e) {
                this.jsonError = 'JSON invalide : ' + e.message;
            }
        },

        async save() {
            // For text/json fields the source of truth is textValue, not editing.value.
            let value = this.editing.value;
            if (this.type === 'text') value = this.textValue;
            if (this.type === 'json') {
                try { value = JSON.parse(this.textValue || 'null'); }
                catch (e) { this.jsonError = 'JSON invalide : ' + e.message; return; }
            }
            if (this.type === 'integer') value = parseInt(value, 10);
            if (this.type === 'decimal') value = parseFloat(value);
            if (this.type === 'boolean') value = !!value;

            this.saving = true;
            this.msg = '';
            try {
                await window.axios.put(`/admin/parametrage/${this.id}`, { value });
                this.dirty = false;
                this.original = structuredClone(value);
                this.msg = 'Enregistré';
                this.msgClass = 'text-success';
                this.$root.notify(`✓ ${this.key} mis à jour`);
            } catch (e) {
                const data = e.response?.data;
                this.msg = data?.message
                    ?? Object.values(data?.errors ?? {}).flat().join(' ')
                    ?? 'Erreur lors de l\'enregistrement.';
                this.msgClass = 'text-danger';
            } finally {
                this.saving = false;
            }
        },
    };
}
