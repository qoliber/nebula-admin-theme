"use strict";
(() => {
  // ts/form.ts
  function createForm() {
    return {
      saving: false,
      saveAndContinue: false,
      saveWithBack(form, back) {
        if (!form) {
          console.error("Nebula: form element not found");
          return;
        }
        if (window.Nebula && typeof window.Nebula.validateForm === "function") {
          if (!window.Nebula.validateForm(form)) {
            this.saving = false;
            this.saveAndContinue = false;
            return;
          }
        }
        const models = window.Alpine.store("nebulaModels");
        if (models && typeof models.validateAll === "function" && !models.validateAll()) {
          this.saving = false;
          this.saveAndContinue = false;
          return;
        }
        this.saving = true;
        this.saveAndContinue = Boolean(back);
        if (window.Nebula?.showSaveLoader) {
          window.Nebula.showSaveLoader();
        }
        if (models && typeof models.serializeAll === "function") {
          models.serializeAll(form);
        }
        const existing = form.querySelector('input[name="back"]');
        const shouldSend = typeof back === "string" ? back !== "" : Boolean(back);
        if (shouldSend) {
          const backInput = existing ?? Object.assign(document.createElement("input"), {
            type: "hidden",
            name: "back"
          });
          backInput.value = typeof back === "string" ? back : "1";
          if (!existing) form.appendChild(backInput);
        } else if (existing) {
          existing.remove();
        }
        form.requestSubmit();
      }
    };
  }
  function registerForm() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaForm", () => createForm());
    });
    document.addEventListener("keydown", (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === "s") {
        e.preventDefault();
        const form = document.querySelector(
          '[x-ref="nebulaEavForm"], [x-ref="nebulaForm"]'
        );
        if (form) {
          const wrapper = form.closest("[x-data]");
          const saveBtn = wrapper?.querySelector(
            'button[data-nebula-save-action="saveWithBack"]'
          );
          if (saveBtn) saveBtn.click();
        }
      }
    });
    installSaveLoader();
  }
  function installSaveLoader() {
    const g = window;
    if (g.__nebulaSaveLoaderInstalled) return;
    g.__nebulaSaveLoaderInstalled = true;
    let overlay = null;
    window.Nebula = Object.assign({}, window.Nebula ?? {}, {
      showSaveLoader() {
        if (overlay) return;
        overlay = document.createElement("div");
        overlay.id = "nebula-save-overlay";
        overlay.innerHTML = '<div style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.8);backdrop-filter:blur(2px)"><div style="text-align:center"><svg style="width:48px;height:48px;margin:0 auto 16px;animation:nebula-spin 1s linear infinite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><p style="font-size:14px;font-weight:500;color:#374151">Saving...</p></div></div>';
        document.body.appendChild(overlay);
        if (!document.getElementById("nebula-spin-style")) {
          const s = document.createElement("style");
          s.id = "nebula-spin-style";
          s.textContent = "@keyframes nebula-spin { to { transform: rotate(360deg) } }";
          document.head.appendChild(s);
        }
      },
      hideSaveLoader() {
        if (overlay) {
          overlay.remove();
          overlay = null;
        }
      }
    });
    window.addEventListener("pageshow", () => window.Nebula?.hideSaveLoader?.());
  }

  // ts/nebula-form.ts
  registerForm();
})();
//# sourceMappingURL=nebula-form.js.map
