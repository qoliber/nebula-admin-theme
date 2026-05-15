/**
 * Nebula Component - Alpine.js shared utilities and base renderers.
 *
 * Provides field renderers, column renderers, and validation utilities
 * for the Nebula UI component system.
 */
document.addEventListener('alpine:init', () => {
    'use strict';

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Validate a value against a set of rules.
     *
     * @param {*} value - The value to validate.
     * @param {Object} rules - Validation rules object.
     * @param {boolean} [rules.required] - Value must not be empty.
     * @param {number} [rules.minLength] - Minimum string length.
     * @param {number} [rules.maxLength] - Maximum string length.
     * @param {string} [rules.pattern] - Regex pattern the value must match.
     * @param {number} [rules.min] - Minimum numeric value.
     * @param {number} [rules.max] - Maximum numeric value.
     * @param {boolean} [rules.email] - Value must be a valid email address.
     * @returns {string|null} Error message or null if valid.
     */
    const nebulaValidate = (value, rules) => {
        if (!rules || typeof rules !== 'object') {
            return null;
        }

        const strValue = value != null ? String(value) : '';

        if (rules.required && (value == null || strValue.trim() === '')) {
            return 'This field is required.';
        }

        if (strValue === '') {
            return null;
        }

        if (rules.minLength != null && strValue.length < rules.minLength) {
            return 'Minimum length is ' + rules.minLength + ' characters.';
        }

        if (rules.maxLength != null && strValue.length > rules.maxLength) {
            return 'Maximum length is ' + rules.maxLength + ' characters.';
        }

        if (rules.pattern) {
            const regex = new RegExp(rules.pattern);

            if (!regex.test(strValue)) {
                return 'Value does not match the required pattern.';
            }
        }

        if (rules.min != null && Number(value) < rules.min) {
            return 'Value must be at least ' + rules.min + '.';
        }

        if (rules.max != null && Number(value) > rules.max) {
            return 'Value must be at most ' + rules.max + '.';
        }

        if (rules.email) {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailPattern.test(strValue)) {
                return 'Please enter a valid email address.';
            }
        }

        return null;
    };

    // =========================================================================
    // Field Renderers
    // =========================================================================

    /**
     * Base field data factory. Provides common field properties.
     *
     * @param {Object} config - Field configuration from definition.
     * @returns {Object} Alpine.data component definition.
     */
    const baseFieldData = (config) => {
        return {
            value: config.value ?? config.default ?? '',
            label: config.label ?? '',
            name: config.name ?? '',
            fieldName: config.fieldName ?? '',
            fieldType: config.fieldType ?? 'text',
            disabled: config.disabled ?? false,
            required: config.required ?? false,
            placeholder: config.placeholder ?? '',
            error: null,
            _modelKey: null,

            /**
             * Auto-register with the model store on init.
             * Called automatically by Alpine when the component mounts.
             */
            init() {
                if (this.fieldName) {
                    this._modelKey = 'field:' + this.fieldName;
                    const models = Alpine.store('nebulaModels');
                    if (models) {
                        models.register(this._modelKey, this);
                    }
                }
            },

            /**
             * Unregister on destroy.
             */
            destroy() {
                if (this._modelKey) {
                    const models = Alpine.store('nebulaModels');
                    if (models) {
                        models.unregister(this._modelKey);
                    }
                }
            },

            /**
             * Serialize this field for form submission.
             * Returns array of {name, value} pairs.
             *
             * @returns {Array<{name: string, value: string}>}
             */
            serialize() {
                if (!this.fieldName || this.disabled) return [];
                return [{ name: this.fieldName, value: this.value ?? '' }];
            },

            /**
             * Validate the current field value.
             *
             * @returns {boolean} True if valid.
             */
            validate() {
                this.error = nebulaValidate(this.value, config.validation ?? {});
                return this.error === null;
            }
        };
    };

    /** @type {string[]} */
    const fieldTypes = [
        'text',
        'textarea',
        'select',
        'multiselect',
        'toggle',
        'checkbox',
        'number',
        'hidden',
        'date',
        'color',
        'password'
    ];

    fieldTypes.forEach((type) => {
        Alpine.data('nebulaField_' + type, (config) => {
            config = config || {};
            const base = baseFieldData(config);

            switch (type) {
                case 'select':
                    base.options = config.options ?? [];
                    break;
                case 'multiselect':
                    base.options = config.options ?? [];
                    base.serialize = function () {
                        if (!this.fieldName || this.disabled) return [];
                        const values = Array.isArray(this.value) ? this.value : [];
                        return values.map(v => ({ name: this.fieldName + '[]', value: v }));
                    };
                    break;
                case 'toggle':
                case 'checkbox':
                    base.value = config.value ?? config.default ?? false;
                    /** @returns {boolean} */
                    base.isChecked = function () {
                        return !!this.value;
                    };
                    base.serialize = function () {
                        if (!this.fieldName || this.disabled) return [];
                        return [{ name: this.fieldName, value: this.value ? '1' : '0' }];
                    };
                    break;
                case 'number':
                    base.min = config.min ?? null;
                    base.max = config.max ?? null;
                    base.step = config.step ?? 1;
                    break;
                case 'textarea':
                    base.rows = config.rows ?? 4;
                    break;
            }

            return base;
        });
    });

    // =========================================================================
    // Column Renderers
    // =========================================================================

    /**
     * Base column data factory. Provides common column properties.
     *
     * @param {Object} config - Column configuration from definition.
     * @returns {Object} Alpine.data component definition.
     */
    const baseColumnData = (config) => {
        return {
            value: config.value ?? '',
            label: config.label ?? '',
            name: config.name ?? '',
            sortable: config.sortable ?? false,
            visible: config.visible ?? true
        };
    };

    /**
     * Column type definitions with type-specific properties.
     *
     * @type {Object.<string, function(Object): Object>}
     */
    const columnDefinitions = {
        text: function (config) {
            return baseColumnData(config);
        },

        badge: function (config) {
            const base = baseColumnData(config);
            base.variant = config.variant ?? 'default';
            base.map = config.map ?? {};

            /**
             * Get the CSS class for the current badge value.
             *
             * @returns {string}
             */
            base.getBadgeClass = function () {
                return this.map[this.value] ?? this.variant;
            };

            return base;
        },

        actions: function (config) {
            const base = baseColumnData(config);
            base.actions = config.actions ?? [];
            return base;
        },

        boolean: function (config) {
            const base = baseColumnData(config);

            /**
             * Get display text for the boolean value.
             *
             * @returns {string}
             */
            base.getDisplayValue = function () {
                return this.value ? (config.trueLabel ?? 'Yes') : (config.falseLabel ?? 'No');
            };

            return base;
        },

        date: function (config) {
            const base = baseColumnData(config);
            base.format = config.format ?? 'YYYY-MM-DD';

            /**
             * Get the formatted date string.
             *
             * @returns {string}
             */
            base.getFormattedDate = function () {
                if (!this.value) {
                    return '';
                }

                try {
                    return new Date(this.value).toLocaleDateString();
                } catch (e) {
                    return String(this.value);
                }
            };

            return base;
        },

        price: function (config) {
            const base = baseColumnData(config);
            base.currency = config.currency ?? 'USD';

            /**
             * Get the formatted price string.
             *
             * @returns {string}
             */
            base.getFormattedPrice = function () {
                const num = parseFloat(this.value);

                if (isNaN(num)) {
                    return '';
                }

                try {
                    return num.toLocaleString(undefined, {
                        style: 'currency',
                        currency: this.currency
                    });
                } catch (e) {
                    return num.toFixed(2);
                }
            };

            return base;
        },

        link: function (config) {
            const base = baseColumnData(config);
            base.href = config.href ?? '#';
            base.target = config.target ?? '_self';
            return base;
        },

        thumbnail: function (config) {
            const base = baseColumnData(config);
            base.src = config.src ?? '';
            base.alt = config.alt ?? '';
            base.width = config.width ?? 50;
            base.height = config.height ?? 50;
            return base;
        }
    };

    Object.keys(columnDefinitions).forEach((type) => {
        Alpine.data('nebulaColumn_' + type, (config) => {
            return columnDefinitions[type](config || {});
        });
    });

    // =========================================================================
    // Public API
    // =========================================================================

    window.NebulaComponent = {
        validate: nebulaValidate
    };

    // =========================================================================
    // Layout Renderer
    // =========================================================================

    const widthClassMap = {
        '1/2': 'nebula-col--6',
        '1/3': 'nebula-col--4',
        '2/3': 'nebula-col--8',
        '1/4': 'nebula-col--3',
        '3/4': 'nebula-col--9',
        'full': 'nebula-col--12'
    };

    /**
     * Alpine.data registration for layout rendering.
     *
     * @param {Object} layoutTree - Processed layout tree from LayoutProcessor::toAlpineData().
     */
    Alpine.data('nebulaLayout', function (layoutTree) {
        return {
            layout: layoutTree || {},
            activeTab: 0,

            /**
             * Get CSS class for a width value.
             *
             * @param {string} width
             * @returns {string}
             */
            getWidthClass: function (width) {
                return widthClassMap[width] || 'nebula-col--12';
            },

            /**
             * Set the active tab index.
             *
             * @param {number} index
             */
            setActiveTab: function (index) {
                this.activeTab = index;
            },

            /**
             * Check if a tab is active.
             *
             * @param {number} index
             * @returns {boolean}
             */
            isActiveTab: function (index) {
                return this.activeTab === index;
            },

            /**
             * Render a layout node recursively.
             *
             * @param {Object} node - Node with type, props, children.
             * @returns {Object} Renderable node info.
             */
            renderNode: function (node) {
                if (!node || !node.type) {
                    return null;
                }

                const result = {
                    type: node.type,
                    props: node.props || {},
                    children: []
                };

                if (node.props && node.props.width) {
                    result.props.colClass = this.getWidthClass(node.props.width);
                }

                if (node.children && node.children.length) {
                    const self = this;

                    result.children = node.children.map((child) => {
                        return self.renderNode(child);
                    }).filter(Boolean);
                }

                return result;
            }
        };
    });
});
