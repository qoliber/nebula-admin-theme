"use strict";
(() => {
  // ts/pages/catalog-accessibility.ts
  function applyButtonAccessibility() {
    if (!document.body.classList.contains("catalog-product-edit")) {
      return;
    }
    document.querySelectorAll("button").forEach((button) => {
      if (button.hasAttribute("aria-label")) return;
      const visibleText = (button.textContent ?? "").replace(/\s+/g, " ").trim();
      if (visibleText.length > 0) return;
      const title = (button.getAttribute("title") ?? "").trim();
      const dataLabel = (button.getAttribute("data-label") ?? "").trim();
      const name = (button.getAttribute("name") ?? "").trim();
      const id = (button.getAttribute("id") ?? "").trim();
      const fallback = title || dataLabel || name || id || "Action button";
      button.setAttribute("aria-label", fallback);
      if (!button.hasAttribute("title")) {
        button.setAttribute("title", fallback);
      }
    });
  }
  document.addEventListener("DOMContentLoaded", applyButtonAccessibility);

  // ts/modal.ts
  var sizeClasses = {
    sm: "max-w-md",
    md: "max-w-xl",
    lg: "max-w-3xl",
    xl: "max-w-5xl",
    full: "max-w-7xl"
  };
  var FOCUSABLE_SELECTOR = 'a[href], area[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex^="-"]), [contenteditable=true]';
  function installFocusTrap(panel) {
    const handler = (event) => {
      if (event.key !== "Tab") return;
      const focusables = Array.from(
        panel.querySelectorAll(FOCUSABLE_SELECTOR)
      ).filter((el) => el.offsetParent !== null || el === document.activeElement);
      if (focusables.length === 0) {
        event.preventDefault();
        return;
      }
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      const active = document.activeElement;
      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    };
    panel.addEventListener("keydown", handler);
    return () => panel.removeEventListener("keydown", handler);
  }
  function createModal(config = {}) {
    const steps = config.steps ?? [];
    const size = config.size ?? "lg";
    const onOpen = config.onOpen ?? null;
    const onClose = config.onClose ?? null;
    let previouslyFocused = null;
    let trapTeardown = null;
    return {
      modalOpen: false,
      modalStep: 0,
      modalSteps: steps,
      modalSize: size,
      modalTitle: config.title ?? "",
      openModal() {
        this.modalStep = 0;
        this.modalOpen = true;
        previouslyFocused = document.activeElement;
        if (onOpen) {
          onOpen.call(this);
        }
        const self = this;
        this.$nextTick?.(() => {
          if (self.$el) {
            self.$el.dispatchEvent(new CustomEvent("nebula-modal:open", { bubbles: true }));
          }
          const panel = self.$el?.querySelector('[x-show="modalOpen"]');
          const focusTarget = panel?.querySelector(
            'input, select, textarea, button, [tabindex]:not([tabindex^="-"])'
          );
          if (focusTarget) focusTarget.focus();
          if (panel && trapTeardown === null) {
            trapTeardown = installFocusTrap(panel);
          }
        });
      },
      closeModal() {
        this.modalOpen = false;
        if (trapTeardown) {
          trapTeardown();
          trapTeardown = null;
        }
        if (onClose) {
          onClose.call(this);
        }
        if (this.$el) {
          this.$el.dispatchEvent(new CustomEvent("nebula-modal:close", { bubbles: true }));
        }
        const previous = previouslyFocused;
        previouslyFocused = null;
        if (previous && typeof previous.focus === "function") {
          requestAnimationFrame(() => {
            if (document.contains(previous)) previous.focus();
          });
        }
      },
      nextModalStep(canProceed) {
        if (canProceed && !canProceed.call(this)) return;
        if (this.modalStep < this.modalSteps.length - 1) {
          this.modalStep++;
        }
      },
      prevModalStep() {
        if (this.modalStep > 0) {
          this.modalStep--;
        }
      },
      goToModalStep(index) {
        if (index >= 0 && index < this.modalSteps.length) {
          this.modalStep = index;
        }
      },
      get modalSizeClass() {
        return sizeClasses[this.modalSize] ?? sizeClasses.lg;
      },
      get isFirstModalStep() {
        return this.modalStep === 0;
      },
      get isLastModalStep() {
        return this.modalSteps.length === 0 || this.modalStep === this.modalSteps.length - 1;
      },
      get hasModalSteps() {
        return this.modalSteps.length > 0;
      },
      get currentModalStepLabel() {
        if (this.modalSteps.length === 0) return "";
        return this.modalSteps[this.modalStep] ?? "";
      },
      get modalStepCount() {
        return this.modalSteps.length;
      }
    };
  }

  // ts/pages/custom-options.ts
  var TYPE_LABELS = {
    field: "Text Field",
    area: "Text Area",
    drop_down: "Dropdown",
    radio: "Radio Buttons",
    checkbox: "Checkboxes",
    multiple: "Multiple Select",
    file: "File Upload",
    date: "Date",
    date_time: "Date & Time",
    time: "Time"
  };
  var TYPE_ICONS = {
    field: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />',
    area: '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
    drop_down: '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />',
    radio: '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
    checkbox: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
    multiple: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />',
    file: '<path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />',
    date: '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />',
    date_time: '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />',
    time: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />'
  };
  function createCustomOptions(config) {
    const modalMixin = createModal({
      size: "lg",
      steps: ["Select Type", "Configure"],
      onClose() {
        const self = this;
        self.resetWizard();
        self.editIndex = null;
      }
    });
    const state = {
      ...modalMixin,
      options: config.existingOptions ?? [],
      messages: config.messages ?? {},
      editIndex: null,
      selectedType: "",
      optionTitle: "",
      optionRequired: true,
      optionPrice: "",
      optionPriceType: "fixed",
      optionSku: "",
      optionMaxChars: "",
      optionFileExt: "",
      optionValues: [],
      init() {
        const models = window.Alpine.store("nebulaModels");
        if (models) models.register("section:customOptions", this);
      },
      destroy() {
        const models = window.Alpine.store("nebulaModels");
        if (models) models.unregister("section:customOptions");
      },
      get isSelectType() {
        return ["drop_down", "radio", "checkbox", "multiple"].includes(this.selectedType);
      },
      _validateOptionValues() {
        if (!this.optionTitle.trim()) {
          return {
            valid: false,
            message: this.messages["titleRequired"] ?? "Option title is required"
          };
        }
        if (this.isSelectType) {
          const validValues = this.optionValues.filter(
            (v) => v.title && v.title.trim().length > 0
          );
          if (validValues.length === 0) {
            return {
              valid: false,
              message: this.messages["addValue"] ?? "Add at least one value with a title"
            };
          }
          const skus = validValues.map((v) => v.sku ? v.sku.trim() : "").filter((s) => s !== "");
          if (skus.length !== new Set(skus).size) {
            return {
              valid: false,
              message: this.messages["uniqueSkus"] ?? "Value SKUs must be unique"
            };
          }
        }
        return { valid: true, message: "" };
      },
      get canSave() {
        return this._validateOptionValues().valid;
      },
      get validationMessage() {
        return this._validateOptionValues().message;
      },
      get typeLabel() {
        return TYPE_LABELS[this.selectedType] ?? this.selectedType;
      },
      typeLabelFor(type) {
        return TYPE_LABELS[type] ?? type;
      },
      typeIconFor(type) {
        return '<svg class="h-5 w-5 text-indigo-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">' + (TYPE_ICONS[type] ?? "") + "</svg>";
      },
      openWizard(editIdx) {
        this.editIndex = editIdx !== void 0 ? editIdx : null;
        if (this.editIndex !== null) {
          const opt = this.options[this.editIndex];
          if (!opt) {
            this.resetWizard();
            this.openModal();
            return;
          }
          this.selectedType = opt.type;
          this.optionTitle = opt.title;
          this.optionRequired = Boolean(opt.is_require);
          this.optionPrice = opt.price;
          this.optionPriceType = opt.price_type || "fixed";
          this.optionSku = opt.sku || "";
          this.optionMaxChars = opt.max_characters || "";
          this.optionFileExt = opt.file_extension || "";
          this.optionValues = opt.values ? JSON.parse(JSON.stringify(opt.values)) : [];
          this.openModal();
          this.modalStep = 1;
        } else {
          this.resetWizard();
          this.openModal();
        }
      },
      resetWizard() {
        this.selectedType = "";
        this.optionTitle = "";
        this.optionRequired = true;
        this.optionPrice = "";
        this.optionPriceType = "fixed";
        this.optionSku = "";
        this.optionMaxChars = "";
        this.optionFileExt = "";
        this.optionValues = [];
      },
      selectType(type) {
        this.selectedType = type;
        if (this.isSelectType && this.optionValues.length === 0) {
          this.addValue();
        }
        this.modalStep = 1;
      },
      addValue() {
        this.optionValues.push({
          option_type_id: null,
          title: "",
          price: "",
          price_type: "fixed",
          sku: "",
          sort_order: this.optionValues.length + 1
        });
      },
      removeValue(idx) {
        this.optionValues.splice(idx, 1);
      },
      saveOption() {
        const existing = this.editIndex !== null ? this.options[this.editIndex] : null;
        const opt = {
          option_id: existing ? existing.option_id ?? null : null,
          title: this.optionTitle,
          type: this.selectedType,
          is_require: this.optionRequired ? 1 : 0,
          sort_order: existing ? existing.sort_order : this.options.length + 1,
          price: this.isSelectType ? "" : this.optionPrice,
          price_type: this.isSelectType ? "" : this.optionPriceType,
          sku: this.isSelectType ? "" : this.optionSku,
          max_characters: this.optionMaxChars,
          file_extension: this.optionFileExt,
          values: this.isSelectType ? JSON.parse(JSON.stringify(this.optionValues)) : []
        };
        if (this.editIndex !== null) {
          this.options[this.editIndex] = opt;
        } else {
          this.options.push(opt);
        }
        this.closeModal();
      },
      removeOption(idx) {
        this.options.splice(idx, 1);
      },
      serialize() {
        const pairs = [];
        for (let i = 0; i < this.options.length; i++) {
          const opt = this.options[i];
          if (!opt) continue;
          const prefix = "product[options][" + String(i) + "]";
          if (opt.option_id) {
            pairs.push({ name: prefix + "[option_id]", value: opt.option_id });
          }
          pairs.push({ name: prefix + "[title]", value: opt.title });
          pairs.push({ name: prefix + "[type]", value: opt.type });
          pairs.push({
            name: prefix + "[is_require]",
            value: typeof opt.is_require === "boolean" ? opt.is_require ? 1 : 0 : opt.is_require
          });
          pairs.push({ name: prefix + "[sort_order]", value: i + 1 });
          pairs.push({ name: prefix + "[price]", value: opt.price || "" });
          pairs.push({ name: prefix + "[price_type]", value: opt.price_type || "fixed" });
          pairs.push({ name: prefix + "[sku]", value: opt.sku || "" });
          pairs.push({ name: prefix + "[max_characters]", value: opt.max_characters || "" });
          pairs.push({ name: prefix + "[file_extension]", value: opt.file_extension || "" });
          pairs.push({ name: prefix + "[is_delete]", value: "0" });
          pairs.push({ name: prefix + "[previous_type]", value: opt.type });
          pairs.push({ name: prefix + "[previous_group]", value: "" });
          const values = opt.values ?? [];
          for (let vi = 0; vi < values.length; vi++) {
            const val = values[vi];
            if (!val) continue;
            const vprefix = prefix + "[values][" + String(vi) + "]";
            if (val.option_type_id) {
              pairs.push({ name: vprefix + "[option_type_id]", value: val.option_type_id });
            }
            pairs.push({ name: vprefix + "[title]", value: val.title });
            pairs.push({ name: vprefix + "[price]", value: val.price || "0" });
            pairs.push({ name: vprefix + "[price_type]", value: val.price_type || "fixed" });
            pairs.push({ name: vprefix + "[sku]", value: val.sku || "" });
            pairs.push({ name: vprefix + "[sort_order]", value: vi + 1 });
            pairs.push({ name: vprefix + "[is_delete]", value: "0" });
          }
        }
        pairs.push({ name: "affect_product_custom_options", value: "1" });
        return pairs;
      }
    };
    return state;
  }
  function register() {
    window.Alpine.data(
      "nebulaCustomOptions",
      (config) => createCustomOptions(config)
    );
  }
  if (window.Alpine) {
    register();
  } else {
    document.addEventListener("alpine:init", register);
  }
})();
//# sourceMappingURL=nebula-custom-options.js.map
