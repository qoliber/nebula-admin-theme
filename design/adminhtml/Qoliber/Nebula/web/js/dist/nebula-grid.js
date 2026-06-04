"use strict";
(() => {
  // ts/grid.ts
  function createGrid(config) {
    let pendingFilters = {};
    try {
      pendingFilters = JSON.parse(config.filters ?? "{}");
    } catch {
      pendingFilters = {};
    }
    return {
      selected: [],
      loading: false,
      confirmAction: null,
      pendingFilters,
      toggleSelectAll(checked) {
        if (!this.$root) {
          this.selected = [];
          return;
        }
        if (checked) {
          this.selected = Array.from(
            this.$root.querySelectorAll(
              'tbody input[type="checkbox"][value]'
            )
          ).map((el) => el.value);
        } else {
          this.selected = [];
        }
      },
      applyAllFilters() {
        const url = new URL(window.location.href);
        Array.from(url.searchParams.keys()).forEach((key) => {
          if (key.startsWith("filters[")) {
            url.searchParams.delete(key);
          }
        });
        Object.entries(this.pendingFilters).forEach(([field, value]) => {
          if (value !== "" && value !== null && value !== void 0) {
            url.searchParams.set("filters[" + field + "]", String(value));
          }
        });
        url.searchParams.set("page", "1");
        window.location.href = url.toString();
      },
      confirmMassAction(url, label) {
        if (this.selected.length === 0) return;
        this.confirmAction = { url, label };
      },
      executeMassAction() {
        if (!this.confirmAction) return;
        this.submitMassAction(this.confirmAction.url);
        this.confirmAction = null;
      },
      submitMassAction(url) {
        if (this.selected.length === 0) return;
        this.loading = true;
        try {
          const form = document.createElement("form");
          form.method = "POST";
          form.action = url;
          const fkInput = document.createElement("input");
          fkInput.type = "hidden";
          fkInput.name = "form_key";
          fkInput.value = config.formKey;
          form.appendChild(fkInput);
          this.selected.forEach((id) => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = "ids[]";
            input.value = id;
            form.appendChild(input);
          });
          document.body.appendChild(form);
          form.submit();
        } catch (e) {
          console.error("Mass action error:", e);
          this.loading = false;
        }
      }
    };
  }
  function registerGrid() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaGrid", (config) => createGrid(config));
    });
  }

  // ts/nebula-grid.ts
  registerGrid();
})();
//# sourceMappingURL=nebula-grid.js.map
