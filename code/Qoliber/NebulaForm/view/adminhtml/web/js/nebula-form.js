document.addEventListener("alpine:init", () => {
    Alpine.data("nebulaForm", (config) => ({
        saving: false,
        saveAndContinue: false,
        tabStorageKey: config.tabStorageKey || null,
        categoryDrawerStorageKey: config.categoryDrawerStorageKey || null,

        persistActiveTab() {
            if (!this.tabStorageKey) {
                return;
            }

            const activeTab = document.querySelector(
                '[data-nebula-tab-button][aria-selected="true"]',
            );
            if (!activeTab) {
                return;
            }

            try {
                window.localStorage.setItem(
                    this.tabStorageKey,
                    activeTab.dataset.nebulaTabButton || "0",
                );
            } catch (e) {
                // Ignore storage failures
            }
        },

        persistCategoryDrawerState() {
            if (!this.categoryDrawerStorageKey) {
                return;
            }

            const drawerRoot = document.querySelector(
                "[data-nebula-category-drawer]",
            );
            if (!drawerRoot) {
                return;
            }

            const drawerState =
                drawerRoot.dataset.nebulaCategoryDrawerOpen === "true"
                    ? "open"
                    : "closed";

            try {
                window.localStorage.setItem(
                    this.categoryDrawerStorageKey,
                    drawerState,
                );
            } catch (e) {
                // Ignore storage failures
            }
        },

        activateTabByIndex(index) {
            const tabButton = document.querySelector(
                '[data-nebula-tab-button="' + index + '"]',
            );
            if (tabButton) {
                tabButton.click();
            }
        },

        activateTabWithValidationError(form) {
            if (!form) {
                return false;
            }

            const invalidField = form.querySelector(
                '.nebula-field-error, [aria-invalid="true"], .mage-error, :invalid',
            );
            if (!invalidField) {
                return false;
            }

            const tabPanel = invalidField.closest("[data-nebula-tab-panel]");
            if (!tabPanel) {
                return false;
            }

            const tabIndex = tabPanel.dataset.nebulaTabPanel;
            if (typeof tabIndex === "undefined") {
                return false;
            }

            this.activateTabByIndex(tabIndex);

            const focusTarget = invalidField.matches(
                "input, select, textarea, button",
            )
                ? invalidField
                : invalidField.querySelector("input, select, textarea, button");

            if (focusTarget) {
                window.requestAnimationFrame(() => {
                    focusTarget.focus({ preventScroll: false });
                });
            }

            return true;
        },

        saveWithBack(form, back) {
            if (!form) {
                console.error("Nebula: form element not found");
                return;
            }

            // Run model validation first
            const models = Alpine.store("nebulaModels");
            if (models && !models.validateAll()) {
                this.saving = false;
                this.saveAndContinue = false;
                this.activateTabWithValidationError(form);
                return;
            }

            // Run legacy DOM validation
            if (window.Nebula && window.Nebula.validateForm) {
                if (!window.Nebula.validateForm(form)) {
                    this.saving = false;
                    this.saveAndContinue = false;
                    this.activateTabWithValidationError(form);
                    return;
                }
            }

            this.persistActiveTab();
            this.persistCategoryDrawerState();
            this.saving = true;
            this.saveAndContinue = !!back;

            // Show fullscreen loader
            Nebula.showSaveLoader();

            // Serialize all registered models into hidden inputs
            if (models) {
                models.serializeAll(form);
            }

            // Set the back param
            let backInput = form.querySelector('input[name="back"]');
            if (!backInput) {
                backInput = document.createElement("input");
                backInput.type = "hidden";
                backInput.name = "back";
                form.appendChild(backInput);
            }
            backInput.value = back || "";

            // Use a hidden submit button to properly submit
            const submitBtn = document.createElement("button");
            submitBtn.type = "submit";
            submitBtn.style.display = "none";
            form.appendChild(submitBtn);
            submitBtn.click();
            submitBtn.remove();
        },
    }));
});

document.addEventListener("keydown", (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === "s") {
        e.preventDefault();
        const form = document.querySelector(
            '[x-ref="nebulaEavForm"], [x-ref="nebulaForm"]',
        );
        if (form) {
            const saveBtn = form
                .closest("[x-data]")
                .querySelector('button[\\@click*="saveWithBack"]');
            if (saveBtn) saveBtn.click();
        }
    }
});

// Fullscreen save loader
(() => {
    window.Nebula = window.Nebula || {};

    let overlay = null;

    Nebula.showSaveLoader = () => {
        if (overlay) return;

        overlay = document.createElement("div");
        overlay.id = "nebula-save-overlay";
        overlay.innerHTML =
            '<div style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.8);backdrop-filter:blur(2px)">' +
            '<div style="text-align:center">' +
            '<svg style="width:48px;height:48px;margin:0 auto 16px;animation:nebula-spin 1s linear infinite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
            '<circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
            "</svg>" +
            '<p style="font-size:14px;font-weight:500;color:#374151">Saving...</p>' +
            "</div>" +
            "</div>";
        document.body.appendChild(overlay);

        if (!document.getElementById("nebula-spin-style")) {
            const style = document.createElement("style");
            style.id = "nebula-spin-style";
            style.textContent =
                "@keyframes nebula-spin { to { transform: rotate(360deg) } }";
            document.head.appendChild(style);
        }
    };

    Nebula.hideSaveLoader = () => {
        if (overlay) {
            overlay.remove();
            overlay = null;
        }
    };

    // Auto-hide on page load (covers the case where save redirects back with errors)
    window.addEventListener("pageshow", () => {
        Nebula.hideSaveLoader();
    });
})();
