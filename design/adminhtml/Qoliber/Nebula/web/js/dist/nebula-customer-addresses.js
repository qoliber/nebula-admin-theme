"use strict";
(() => {
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

  // ts/pages/customer-addresses.ts
  function createCustomerAddresses(config) {
    const modalMixin = createModal({
      size: "lg",
      steps: [],
      onClose() {
        const self = this;
        self.resetForm();
        self.editIndex = null;
      }
    });
    const state = {
      ...modalMixin,
      addresses: config.existingAddresses ?? [],
      defaultBilling: config.defaultBilling ?? null,
      defaultShipping: config.defaultShipping ?? null,
      countries: config.countries ?? [],
      regionsByCountry: config.regionsByCountry ?? {},
      editIndex: null,
      formFirstname: "",
      formLastname: "",
      formCompany: "",
      formTelephone: "",
      formFax: "",
      formStreet0: "",
      formStreet1: "",
      formCity: "",
      formRegion: "",
      formRegionId: "",
      formPostcode: "",
      formCountryId: "",
      formDefaultBilling: false,
      formDefaultShipping: false,
      get currentRegions() {
        return this.regionsByCountry[this.formCountryId] ?? [];
      },
      get hasRegions() {
        return this.currentRegions.length > 0;
      },
      init() {
        const models = window.Alpine.store("nebulaModels");
        if (models) models.register("section:addresses", this);
      },
      destroy() {
        const models = window.Alpine.store("nebulaModels");
        if (models) models.unregister("section:addresses");
      },
      resetForm() {
        this.formFirstname = "";
        this.formLastname = "";
        this.formCompany = "";
        this.formTelephone = "";
        this.formFax = "";
        this.formStreet0 = "";
        this.formStreet1 = "";
        this.formCity = "";
        this.formRegion = "";
        this.formRegionId = "";
        this.formPostcode = "";
        this.formCountryId = "";
        this.formDefaultBilling = false;
        this.formDefaultShipping = false;
      },
      openEditor(editIdx) {
        this.editIndex = editIdx !== void 0 ? editIdx : null;
        if (this.editIndex !== null) {
          const addr = this.addresses[this.editIndex];
          if (!addr) {
            this.resetForm();
            this.openModal();
            return;
          }
          this.formFirstname = addr.firstname ?? "";
          this.formLastname = addr.lastname ?? "";
          this.formCompany = addr.company ?? "";
          this.formTelephone = addr.telephone ?? "";
          this.formFax = addr.fax ?? "";
          let street = [];
          if (Array.isArray(addr.street)) street = addr.street;
          else if (typeof addr.street === "string") street = addr.street.split("\n");
          this.formStreet0 = street[0] ?? "";
          this.formStreet1 = street[1] ?? "";
          this.formCity = addr.city ?? "";
          this.formRegion = addr.region ?? "";
          this.formRegionId = addr.region_id ? String(addr.region_id) : "";
          this.formPostcode = addr.postcode ?? "";
          this.formCountryId = addr.country_id ?? "";
          const addrKey = addr.id ?? this.editIndex;
          this.formDefaultBilling = this.defaultBilling !== null && String(this.defaultBilling) === String(addrKey);
          this.formDefaultShipping = this.defaultShipping !== null && String(this.defaultShipping) === String(addrKey);
        } else {
          this.resetForm();
        }
        this.openModal();
      },
      get canSave() {
        if (!this.formFirstname.trim() || !this.formLastname.trim() || !this.formStreet0.trim() || !this.formCity.trim() || !this.formCountryId || !this.formTelephone.trim()) {
          return false;
        }
        if (this.hasRegions && !this.formRegionId) {
          return false;
        }
        return true;
      },
      saveAddress() {
        const addr = {
          id: this.editIndex !== null ? this.addresses[this.editIndex]?.id ?? null : null,
          firstname: this.formFirstname,
          lastname: this.formLastname,
          company: this.formCompany,
          telephone: this.formTelephone,
          fax: this.formFax,
          street: [this.formStreet0, this.formStreet1],
          city: this.formCity,
          region: this.formRegion,
          region_id: this.formRegionId,
          postcode: this.formPostcode,
          country_id: this.formCountryId
        };
        if (this.editIndex !== null) {
          this.addresses[this.editIndex] = addr;
        } else {
          this.addresses.push(addr);
        }
        const addrKey = addr.id ?? (this.editIndex !== null ? this.editIndex : this.addresses.length - 1);
        if (this.formDefaultBilling) {
          this.defaultBilling = addrKey;
        } else if (String(this.defaultBilling) === String(addrKey)) {
          this.defaultBilling = null;
        }
        if (this.formDefaultShipping) {
          this.defaultShipping = addrKey;
        } else if (String(this.defaultShipping) === String(addrKey)) {
          this.defaultShipping = null;
        }
        this.closeModal();
      },
      removeAddress(idx) {
        const addr = this.addresses[idx];
        if (!addr) return;
        const addrKey = addr.id ?? idx;
        if (String(this.defaultBilling) === String(addrKey)) this.defaultBilling = null;
        if (String(this.defaultShipping) === String(addrKey)) this.defaultShipping = null;
        this.addresses.splice(idx, 1);
      },
      setDefaultBilling(idx) {
        const addr = this.addresses[idx];
        if (!addr) return;
        this.defaultBilling = addr.id ?? idx;
      },
      setDefaultShipping(idx) {
        const addr = this.addresses[idx];
        if (!addr) return;
        this.defaultShipping = addr.id ?? idx;
      },
      isDefaultBilling(idx) {
        const addr = this.addresses[idx];
        if (!addr) return false;
        const addrKey = addr.id ?? idx;
        return this.defaultBilling !== null && String(this.defaultBilling) === String(addrKey);
      },
      isDefaultShipping(idx) {
        const addr = this.addresses[idx];
        if (!addr) return false;
        const addrKey = addr.id ?? idx;
        return this.defaultShipping !== null && String(this.defaultShipping) === String(addrKey);
      },
      formatStreet(_addr) {
        const street = _addr.street ?? [];
        if (typeof street === "string") return street;
        return street.filter((s) => s).join(", ");
      },
      countryLabel(countryId) {
        const found = this.countries.find((c) => c.value === countryId);
        return found ? found.label : countryId;
      },
      serialize() {
        const pairs = [];
        for (let i = 0; i < this.addresses.length; i++) {
          const addr = this.addresses[i];
          if (!addr) continue;
          const prefix = "customer[address][" + String(i) + "]";
          const fields = [
            "firstname",
            "lastname",
            "company",
            "city",
            "region",
            "region_id",
            "postcode",
            "country_id",
            "telephone",
            "fax"
          ];
          for (const key of fields) {
            const raw = addr[key];
            const value = raw === void 0 || raw === null ? "" : typeof raw === "string" || typeof raw === "number" || typeof raw === "boolean" ? raw : String(raw);
            pairs.push({ name: prefix + "[" + key + "]", value });
          }
          let street = [];
          if (Array.isArray(addr.street)) street = addr.street;
          else if (typeof addr.street === "string") street = addr.street.split("\n");
          for (let si = 0; si < street.length; si++) {
            pairs.push({ name: prefix + "[street][]", value: street[si] ?? "" });
          }
          if (addr.id !== void 0 && addr.id !== null) {
            pairs.push({ name: prefix + "[id]", value: addr.id });
          }
        }
        if (this.defaultBilling !== null) {
          pairs.push({ name: "customer[default_billing]", value: this.defaultBilling });
        }
        if (this.defaultShipping !== null) {
          pairs.push({ name: "customer[default_shipping]", value: this.defaultShipping });
        }
        return pairs;
      }
    };
    return state;
  }
  function register() {
    window.Alpine.data(
      "nebulaCustomerAddresses",
      (config) => createCustomerAddresses(config)
    );
  }
  if (window.Alpine) {
    register();
  } else {
    document.addEventListener("alpine:init", register);
  }
})();
//# sourceMappingURL=nebula-customer-addresses.js.map
