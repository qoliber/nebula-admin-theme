/**
 * Nebula Modal Mixin - Composable modal/wizard behavior for Alpine.js components.
 *
 * Usage: spread into any Alpine.data component to add modal capabilities.
 *
 * @example
 *   Alpine.data('myComponent', (config) => ({
 *       ...Nebula.modal({ size: 'lg', steps: ['Select', 'Configure'] }),
 *       // ... your component properties
 *   }));
 *
 * HTML pattern:
 *   <div x-show="modalOpen" x-cloak
 *        class="fixed inset-0 z-[9999] flex items-center justify-center"
 *        @keydown.escape.window="closeModal()">
 *       <!-- Backdrop -->
 *       <div x-show="modalOpen"
 *            x-transition:enter="transition ease-out duration-200"
 *            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
 *            x-transition:leave="transition ease-in duration-150"
 *            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
 *            class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal()"></div>
 *       <!-- Card -->
 *       <div :class="modalSizeClass"
 *            class="relative w-full max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl"
 *            x-transition:enter="transition ease-out duration-300"
 *            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
 *            x-transition:leave="transition ease-in duration-200"
 *            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
 *            @click.stop>
 *           <!-- Header with step indicators, content area, footer -->
 *       </div>
 *   </div>
 */
(function () {
    'use strict';

    if (window._nebulaModalRegistered) {
        return;
    }
    window._nebulaModalRegistered = true;

    var sizeClasses = {
        sm: 'max-w-md',
        md: 'max-w-xl',
        lg: 'max-w-3xl',
        xl: 'max-w-5xl',
        full: 'max-w-7xl'
    };

    window.Nebula = window.Nebula || {};

    /**
     * Create modal mixin properties to spread into an Alpine.data component.
     *
     * @param {Object} [config] - Modal configuration.
     * @param {string} [config.title=''] - Modal title (static string).
     * @param {string} [config.size='lg'] - Size key: sm, md, lg, xl, full.
     * @param {string[]} [config.steps=[]] - Step labels for wizard mode. Empty array for simple modal.
     * @param {Function|null} [config.onOpen=null] - Callback invoked after modal opens. Receives the component as `this`.
     * @param {Function|null} [config.onClose=null] - Callback invoked after modal closes. Receives the component as `this`.
     * @returns {Object} Properties and methods to spread into Alpine.data.
     */
    Nebula.modal = function (config) {
        config = config || {};

        var steps = config.steps || [];
        var size = config.size || 'lg';
        var onOpen = config.onOpen || null;
        var onClose = config.onClose || null;

        return {
            modalOpen: false,
            modalStep: 0,
            modalSteps: steps,
            modalSize: size,
            modalTitle: config.title || '',

            /**
             * Open the modal and reset to first step.
             * Dispatches 'nebula-modal:open' on the root element.
             */
            openModal: function () {
                this.modalStep = 0;
                this.modalOpen = true;

                if (onOpen) {
                    onOpen.call(this);
                }

                var self = this;
                this.$nextTick(function () {
                    if (self.$el) {
                        self.$el.dispatchEvent(new CustomEvent('nebula-modal:open', { bubbles: true }));
                    }

                    // Basic focus trap: focus first visible input
                    var modal = self.$el.querySelector('[x-show="modalOpen"] input, [x-show="modalOpen"] select, [x-show="modalOpen"] textarea');
                    if (modal) {
                        modal.focus();
                    }
                });
            },

            /**
             * Close the modal.
             * Dispatches 'nebula-modal:close' on the root element.
             */
            closeModal: function () {
                this.modalOpen = false;

                if (onClose) {
                    onClose.call(this);
                }

                if (this.$el) {
                    this.$el.dispatchEvent(new CustomEvent('nebula-modal:close', { bubbles: true }));
                }
            },

            /**
             * Advance to the next wizard step.
             *
             * @param {Function} [canProceed] - Optional guard callback. Must return true to proceed.
             */
            nextModalStep: function (canProceed) {
                if (canProceed && !canProceed.call(this)) {
                    return;
                }

                if (this.modalStep < this.modalSteps.length - 1) {
                    this.modalStep++;
                }
            },

            /**
             * Go back to the previous wizard step.
             */
            prevModalStep: function () {
                if (this.modalStep > 0) {
                    this.modalStep--;
                }
            },

            /**
             * Go to a specific wizard step.
             *
             * @param {number} index - Zero-based step index.
             */
            goToModalStep: function (index) {
                if (index >= 0 && index < this.modalSteps.length) {
                    this.modalStep = index;
                }
            },

            /**
             * Get the Tailwind max-width class for the current modal size.
             *
             * @returns {string}
             */
            get modalSizeClass() {
                return sizeClasses[this.modalSize] || sizeClasses.lg;
            },

            /**
             * Whether the modal is on the first wizard step.
             *
             * @returns {boolean}
             */
            get isFirstModalStep() {
                return this.modalStep === 0;
            },

            /**
             * Whether the modal is on the last wizard step.
             *
             * @returns {boolean}
             */
            get isLastModalStep() {
                return this.modalSteps.length === 0 || this.modalStep === this.modalSteps.length - 1;
            },

            /**
             * Whether the modal has wizard steps configured.
             *
             * @returns {boolean}
             */
            get hasModalSteps() {
                return this.modalSteps.length > 0;
            },

            /**
             * The current step label (empty string if no steps).
             *
             * @returns {string}
             */
            get currentModalStepLabel() {
                if (this.modalSteps.length === 0) {
                    return '';
                }

                return this.modalSteps[this.modalStep] || '';
            },

            /**
             * Total number of wizard steps.
             *
             * @returns {number}
             */
            get modalStepCount() {
                return this.modalSteps.length;
            }
        };
    };
})();
