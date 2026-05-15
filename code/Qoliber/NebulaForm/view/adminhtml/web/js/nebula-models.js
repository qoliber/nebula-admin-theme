/**
 * Nebula Form Models — Alpine.js model registry for form serialization.
 *
 * Every field and section component auto-registers on init().
 * On form submit, serializeAll() collects data from all models
 * and injects hidden inputs — templates have zero hidden inputs.
 *
 * Model contract:
 *   serialize()  → [{name: 'product[name]', value: 'Widget'}, ...]
 *   validate()   → true | false (optional)
 *   destroy()    → cleanup (called automatically via Alpine destroy)
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('nebulaModels', {
        _models: {},
        _counter: 0,

        /**
         * Register a model. Called automatically by field/section init().
         * @param {string} name — unique model key (e.g. 'field:product[name]' or 'section:customOptions')
         * @param {object} model — must implement serialize(), optionally validate()
         */
        register(name, model) {
            this._models[name] = model;
        },

        /**
         * Remove a model (called on Alpine component destroy).
         */
        unregister(name) {
            delete this._models[name];
        },

        /**
         * Generate a unique model name for anonymous fields.
         */
        nextId() {
            return '__model_' + (++this._counter);
        },

        /**
         * Get a model by name.
         */
        get(name) {
            return this._models[name] || null;
        },

        /**
         * Validate all models. Returns false if any fail.
         */
        validateAll() {
            let valid = true;
            for (const model of Object.values(this._models)) {
                if (typeof model.validate === 'function' && !model.validate()) {
                    valid = false;
                }
            }
            return valid;
        },

        /**
         * Serialize all models into hidden inputs inside the form.
         * Clears previously injected model inputs first.
         */
        serializeAll(form) {
            // Remove previously injected model inputs
            form.querySelectorAll('[data-nebula-model]').forEach(el => el.remove());

            for (const [name, model] of Object.entries(this._models)) {
                if (typeof model.serialize !== 'function') continue;

                const pairs = model.serialize();
                if (!Array.isArray(pairs)) continue;

                for (const pair of pairs) {
                    if (pair.name === undefined || pair.name === null) continue;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = pair.name;
                    input.value = pair.value !== undefined && pair.value !== null ? String(pair.value) : '';
                    input.setAttribute('data-nebula-model', name);
                    form.appendChild(input);
                }
            }
        },

        /**
         * Get all serialized data as a flat object (for debugging).
         */
        debug() {
            const result = {};
            for (const [name, model] of Object.entries(this._models)) {
                if (typeof model.serialize === 'function') {
                    result[name] = model.serialize();
                }
            }
            return result;
        }
    });
});
