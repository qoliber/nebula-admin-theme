"use strict";
(() => {
  // ts/components/catalog/attribute-options.ts
  function registerAttributeOptions() {
    const install = () => {
      window.Alpine?.data("nebulaAttributeOptions", (config = {}) => ({
        options: (config.options ?? []).slice(),
        inputType: config.inputType ?? "select",
        stores: config.stores ?? [],
        nextId: 0,
        addOption() {
          this.options.push({
            id: "option_" + String(this.nextId++),
            label: "",
            sort_order: this.options.length + 1,
            is_default: false,
            store_labels: {},
            is_new: true,
            is_delete: false
          });
        },
        removeOption(index) {
          const opt = this.options[index];
          if (!opt) return;
          if (opt.is_new) {
            this.options.splice(index, 1);
          } else {
            opt.is_delete = true;
          }
        },
        setDefault(index) {
          const opt = this.options[index];
          if (!opt) return;
          if (this.inputType === "select") {
            this.options.forEach((o, i) => {
              o.is_default = i === index;
            });
          } else {
            opt.is_default = !opt.is_default;
          }
        },
        get visibleOptions() {
          return this.options.filter((o) => !o.is_delete);
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/catalog/attribute-set-editor.ts
  function registerAttributeSetEditor() {
    const install = () => {
      window.Alpine?.data("attributeSetEditor", () => {
        const root = document.currentScript?.closest("[data-nebula-attribute-set]") ?? document.querySelector("[data-nebula-attribute-set]");
        const rawConfig = root?.dataset.nebulaAttributeSet ?? "{}";
        const config = JSON.parse(rawConfig);
        return {
          setName: config.setName,
          groups: [],
          unassigned: [],
          removedGroups: [],
          unassignedSearch: "",
          saving: false,
          message: "",
          messageError: false,
          init() {
            this.groups = config.groups.map((g) => ({
              ...g,
              collapsed: false,
              editing: false,
              addingAttribute: false,
              attrSearch: ""
            }));
            this.unassigned = config.unassigned.slice();
          },
          filteredUnassigned(search) {
            if (!search) return this.unassigned;
            const q = search.toLowerCase();
            return this.unassigned.filter(
              (a) => a.code.toLowerCase().includes(q) || a.label.toLowerCase().includes(q)
            );
          },
          addGroup() {
            const name = window.prompt(config.messages.promptGroup);
            if (!name || !name.trim()) return;
            if (this.groups.some((g) => g.name.toLowerCase() === name.trim().toLowerCase())) {
              window.alert(config.messages.groupExists);
              return;
            }
            this.groups.push({
              id: "gNEW_" + String(Date.now()),
              name: name.trim(),
              sort_order: this.groups.length + 1,
              attributes: [],
              collapsed: false,
              editing: false,
              addingAttribute: false,
              attrSearch: ""
            });
          },
          startEditGroupName(group) {
            group.editing = true;
            this.$nextTick(() => {
              const input = this.$el.querySelector('input[x-model="group.name"]');
              input?.focus();
            });
          },
          async deleteGroup(gIdx) {
            const group = this.groups[gIdx];
            if (!group) return;
            const hasSystem = group.attributes.some((a) => !a.is_unassignable);
            if (hasSystem) {
              window.alert(config.messages.systemAttrs);
              return;
            }
            if (group.attributes.length > 0 && !await window.Nebula.confirm({
              title: "Delete this group?",
              message: config.messages.confirmDeleteGroup,
              danger: true,
              confirmText: "Delete group"
            })) {
              return;
            }
            group.attributes.forEach((attr) => {
              if (attr.is_unassignable) {
                this.unassigned.push({
                  attribute_id: attr.attribute_id,
                  code: attr.code,
                  label: attr.label,
                  is_user_defined: attr.is_user_defined,
                  entity_id: attr.entity_id
                });
              }
            });
            if (typeof group.id === "number" || typeof group.id === "string" && !group.id.startsWith("gNEW_")) {
              this.removedGroups.push(group.id);
            }
            this.groups.splice(gIdx, 1);
          },
          moveGroup(idx, direction) {
            const newIdx = idx + direction;
            if (newIdx < 0 || newIdx >= this.groups.length) return;
            const a = this.groups[idx];
            const b = this.groups[newIdx];
            if (!a || !b) return;
            this.groups[idx] = b;
            this.groups[newIdx] = a;
            this.groups = this.groups.slice();
          },
          moveAttribute(group, idx, direction) {
            const newIdx = idx + direction;
            if (newIdx < 0 || newIdx >= group.attributes.length) return;
            const a = group.attributes[idx];
            const b = group.attributes[newIdx];
            if (!a || !b) return;
            group.attributes[idx] = b;
            group.attributes[newIdx] = a;
            group.attributes = group.attributes.slice();
          },
          assignAttribute(group, attr) {
            this.unassigned = this.unassigned.filter((a) => a.attribute_id !== attr.attribute_id);
            group.attributes.push({
              attribute_id: attr.attribute_id,
              code: attr.code,
              label: attr.label,
              entity_id: attr.entity_id ?? 0,
              is_user_defined: attr.is_user_defined,
              is_unassignable: true
            });
            group.addingAttribute = false;
            group.attrSearch = "";
          },
          unassignAttribute(group, aIdx) {
            const attr = group.attributes[aIdx];
            if (!attr) return;
            group.attributes.splice(aIdx, 1);
            this.unassigned.push({
              attribute_id: attr.attribute_id,
              code: attr.code,
              label: attr.label,
              is_user_defined: attr.is_user_defined,
              entity_id: attr.entity_id
            });
          },
          async confirmDelete() {
            if (await window.Nebula.confirm({
              title: "Delete this attribute set?",
              message: config.messages.confirmDeleteSet,
              danger: true,
              confirmText: "Delete set"
            })) {
              window.location.href = config.deleteUrl;
            }
          },
          showMessage(msg, isError) {
            this.message = msg;
            this.messageError = isError;
            window.setTimeout(() => {
              this.message = "";
            }, 4e3);
          },
          save() {
            if (!this.setName.trim()) {
              this.showMessage(config.messages.nameRequired, true);
              return;
            }
            this.saving = true;
            const reqGroups = [];
            const reqAttributes = [];
            const reqNotAttributes = [];
            this.groups.forEach((group, gIdx) => {
              reqGroups.push([group.id, group.name, gIdx + 1]);
              group.attributes.forEach((attr, aIdx) => {
                reqAttributes.push([attr.attribute_id, group.id, aIdx + 1, attr.entity_id ?? 0]);
              });
            });
            this.unassigned.forEach((attr) => {
              if (Number(attr.entity_id ?? 0) > 0) {
                reqNotAttributes.push(attr.entity_id);
              }
            });
            const data = {
              attribute_set_name: this.setName,
              groups: reqGroups,
              attributes: reqAttributes,
              not_attributes: reqNotAttributes,
              removeGroups: [...new Set(this.removedGroups)]
            };
            const formData = new FormData();
            formData.append("data", JSON.stringify(data));
            formData.append("form_key", config.formKey);
            fetch(config.saveUrl, {
              method: "POST",
              body: formData
            }).then((r) => r.json()).then((response) => {
              this.saving = false;
              if (response.error) {
                this.showMessage(response.message ?? config.messages.saveError, true);
              } else if (response.url) {
                this.showMessage(config.messages.saveOk, false);
                window.setTimeout(() => {
                  window.location.href = response.url;
                }, 1e3);
              }
            }).catch(() => {
              this.saving = false;
              this.showMessage(config.messages.saveError, true);
            });
          }
        };
      });
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/catalog/bundle-settings.ts
  function registerBundleSettings() {
    const install = () => {
      window.Alpine?.data("nebulaBundleSettings", (config) => ({
        skuType: config.skuType,
        priceType: config.priceType,
        weightType: config.weightType,
        shipmentType: config.shipmentType,
        weight: config.weight,
        isNew: config.isNew,
        get isDynamicSku() {
          return this.skuType === 1;
        },
        get isDynamicPrice() {
          return this.priceType === 0;
        },
        get isDynamicWeight() {
          return this.weightType === 1;
        },
        toggleSku() {
          this.skuType = this.skuType === 1 ? 0 : 1;
        },
        toggleWeight() {
          this.weightType = this.weightType === 1 ? 0 : 1;
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/catalog/category-nav-tree.ts
  function registerCategoryNavTree() {
    const install = () => {
      window.Alpine?.data("nebulaCategoryNavTree", (config = {}) => ({
        expanded: { ...config.expandedIds ?? {} },
        tree: config.tree ?? [],
        currentId: config.currentId ?? "",
        reorderUrl: config.reorderUrl ?? "",
        moveUrl: config.moveUrl ?? "",
        deleteBaseUrl: config.deleteBaseUrl ?? "",
        formKey: config.formKey ?? "",
        _sortableInstances: [],
        _sortSaving: false,
        sortMode: false,
        sortDirty: false,
        _savedContainerOrder: null,
        _pendingMoves: [],
        _deleteOpen: false,
        _deleteTarget: null,
        _deleteItems: [],
        init() {
        },
        toggleExpand(id) {
          const key = String(id);
          this.expanded[key] = !this.expanded[key];
        },
        expandAll() {
          const walk = (nodes) => {
            for (const n of nodes) {
              if (n.children && n.children.length) {
                this.expanded[String(n.id)] = true;
                walk(n.children);
              }
            }
          };
          walk(this.tree);
        },
        collapseAll() {
          this.expanded = {};
        },
        // --- Sort mode ---------------------------------------------------
        enableSortMode() {
          this._savedContainerOrder = this._captureContainerOrder();
          this.sortMode = true;
          this.$nextTick(() => this._initAllSortables());
        },
        disableSortMode() {
          this._destroyAllSortables();
          this.sortMode = false;
          this.sortDirty = false;
          this._savedContainerOrder = null;
          this._pendingMoves = [];
        },
        cancelSort() {
          window.location.reload();
        },
        saveSort() {
          this._sortSaving = true;
          this._savePendingChanges().then((success) => {
            this._sortSaving = false;
            if (success) {
              this.disableSortMode();
            }
          });
        },
        _captureContainerOrder() {
          const state = {};
          document.querySelectorAll("[data-sort-container]").forEach((el) => {
            const parentId = el.dataset["parentId"] ?? "";
            state[parentId] = Array.from(
              el.querySelectorAll(":scope > [data-cat-id]")
            ).map((child) => child.dataset["catId"] ?? "");
          });
          return state;
        },
        _savePendingChanges() {
          const moveChain = this._pendingMoves.reduce(
            (chain, move) => chain.then((ok) => ok ? this._moveCategoryAsync(move.id, move.pid, move.aid) : false),
            Promise.resolve(true)
          );
          return moveChain.then((movesOk) => {
            if (!movesOk) {
              window.nebulaToast?.("error", "Could not move category.");
              window.location.reload();
              return false;
            }
            const current = this._captureContainerOrder();
            const original = this._savedContainerOrder ?? {};
            const saves = [];
            document.querySelectorAll("[data-sort-container]").forEach((el) => {
              const parentId = el.dataset["parentId"] ?? "";
              const currentIds = current[parentId] ?? [];
              const originalIds = original[parentId] ?? [];
              if (JSON.stringify(currentIds) !== JSON.stringify(originalIds)) {
                saves.push(this._reorderSiblingsAsync(parentId, currentIds));
              }
            });
            if (saves.length === 0) {
              window.nebulaToast?.("success", "Category order saved.");
              return true;
            }
            return Promise.all(saves).then((results) => {
              const allOk = results.every(Boolean);
              if (allOk) {
                window.nebulaToast?.("success", "Category order saved.");
              } else {
                window.nebulaToast?.("error", "Some categories could not be saved.");
              }
              return allOk;
            });
          });
        },
        _moveCategoryAsync(id, pid, aid) {
          const body = new URLSearchParams({ id, pid, aid, form_key: this.formKey });
          return fetch(this.moveUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: body.toString()
          }).then((r) => r.json()).then((data) => {
            if (data.error && data.messages) {
              const tmp = document.createElement("div");
              tmp.innerHTML = data.messages;
              const text = (tmp.textContent ?? tmp.innerText ?? "").trim();
              if (text) window.nebulaToast?.("error", text);
            }
            return !data.error;
          }).catch(() => false);
        },
        _reorderSiblingsAsync(parentId, ids) {
          const url = this.reorderUrl + "?form_key=" + encodeURIComponent(this.formKey);
          return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({ parent_id: parentId, ids })
          }).then((r) => r.json()).then((data) => !!data.success).catch(() => false);
        },
        // --- Navigation guard --------------------------------------------
        async confirmCategorySwitch(url) {
          if (this.sortMode && this.sortDirty) {
            window.nebulaToast?.("error", "Save or cancel the reorder first.");
            return;
          }
          if (this.sortMode && !this.sortDirty) {
            this.disableSortMode();
          }
          if (!this.hasUnsavedChanges()) {
            this.closeCategoryDrawer();
            window.location.href = url;
            return;
          }
          const confirmed = await window.Nebula?.confirm?.({
            title: "Discard unsaved changes?",
            message: "You have edits that will be lost if you leave this page.",
            danger: true,
            confirmText: "Discard",
            cancelText: "Stay"
          });
          if (confirmed) {
            this.closeCategoryDrawer();
            window.location.href = url;
          }
        },
        hasUnsavedChanges() {
          if (window.Nebula && typeof window.Nebula.hasUnsavedCategoryChanges === "function") {
            return window.Nebula.hasUnsavedCategoryChanges();
          }
          return false;
        },
        closeCategoryDrawer() {
          const drawerRoot = document.querySelector("[data-nebula-category-drawer]");
          if (!drawerRoot || !window.Alpine || typeof Alpine.$data !== "function") return;
          try {
            const drawerData = Alpine.$data(
              drawerRoot
            );
            drawerData?.closeDrawer?.();
          } catch {
          }
        },
        // --- Drag-to-reorder (same-parent only) --------------------------
        _initAllSortables() {
          this._destroyAllSortables();
          document.querySelectorAll("[data-sort-container]").forEach((el) => {
            if (!window.Sortable) return;
            const instance = window.Sortable.create(el, {
              group: { name: "cat-tree", pull: true, put: true },
              handle: "[data-cat-drag-handle]",
              draggable: "[data-cat-id]",
              animation: 150,
              ghostClass: "opacity-40",
              onEnd: (evt) => this._onSortEnd(evt)
            });
            this._sortableInstances.push(instance);
          });
        },
        _destroyAllSortables() {
          for (const s of this._sortableInstances) {
            s?.destroy?.();
          }
          this._sortableInstances = [];
        },
        _onSortEnd(evt) {
          if (!evt.item || !evt.from || !evt.to) return;
          const movedId = evt.item.dataset["catId"];
          if (!movedId) return;
          const fromParentId = evt.from.dataset["parentId"];
          const toParentId = evt.to.dataset["parentId"] ?? "";
          if (fromParentId !== toParentId) {
            if (movedId === toParentId || this._isAncestorOf(movedId, toParentId)) {
              evt.from.insertBefore(
                evt.item,
                evt.from.children[evt.oldIndex ?? 0] ?? null
              );
              window.nebulaToast?.("error", "A category cannot be moved into one of its own subcategories.");
              return;
            }
            const siblings = Array.from(
              evt.to.querySelectorAll(":scope > [data-cat-id]")
            );
            const newIdx = siblings.indexOf(evt.item);
            const aid = newIdx > 0 ? siblings[newIdx - 1].dataset["catId"] ?? "0" : "0";
            this._pendingMoves.push({ id: movedId, pid: toParentId, aid });
          }
          this.sortDirty = true;
        },
        _findNodeInTree(id, nodes) {
          const list = nodes ?? this.tree;
          for (const node of list) {
            if (String(node.id) === String(id)) return node;
            if (node.children?.length) {
              const found = this._findNodeInTree(id, node.children);
              if (found) return found;
            }
          }
          return null;
        },
        _countDescendants(id) {
          const node = this._findNodeInTree(id);
          if (!node) return 0;
          const count = (n) => {
            let total = 0;
            for (const child of n.children ?? []) {
              total += 1 + count(child);
            }
            return total;
          };
          return count(node);
        },
        _isAncestorOf(ancestorId, descendantId) {
          const ancestor = this._findNodeInTree(ancestorId);
          if (!ancestor) return false;
          const has = (n, targetId) => {
            for (const child of n.children ?? []) {
              if (String(child.id) === targetId) return true;
              if (has(child, targetId)) return true;
            }
            return false;
          };
          return has(ancestor, String(descendantId));
        },
        confirmDeleteCategory(id, name) {
          const items = [];
          const flatten = (node2, depth) => {
            items.push({
              id: node2.id,
              name: node2.name ?? "",
              depth,
              isActive: node2.is_active !== false
            });
            for (const child of node2.children ?? []) {
              flatten(child, depth + 1);
            }
          };
          const node = this._findNodeInTree(id);
          if (node) flatten(node, 0);
          this._deleteTarget = { id, name };
          this._deleteItems = items;
          this._deleteOpen = true;
        },
        _cancelDelete() {
          this._deleteOpen = false;
          this._deleteTarget = null;
          this._deleteItems = [];
        },
        _executeDelete() {
          if (this._deleteTarget) {
            window.location.href = this.deleteBaseUrl + "id/" + this._deleteTarget.id + "/";
          }
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/catalog/category-products.ts
  function registerCategoryProducts() {
    const install = () => {
      window.Alpine?.data("nebulaCategoryProducts", (config = {}) => ({
        products: (config.products ?? []).slice(),
        productsJson: { ...config.productsJson ?? {} },
        syncJson() {
          const json = {};
          this.products.forEach((p, i) => {
            json[String(p.id)] = typeof p.position === "number" ? p.position : i;
          });
          this.productsJson = json;
        },
        addProduct(product) {
          if (this.products.find((p) => p.id === product.id)) return;
          product.position = this.products.length;
          this.products.push(product);
          this.syncJson();
        },
        removeProduct(id) {
          this.products = this.products.filter((p) => p.id !== id);
          this.syncJson();
        },
        updatePosition(id, pos) {
          const p = this.products.find((x) => x.id === id);
          if (p) {
            p.position = typeof pos === "number" ? pos : parseInt(pos, 10) || 0;
          }
          this.syncJson();
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/catalog/category-tree-modal.ts
  function registerCategoryTreeModal() {
    const install = () => {
      window.Alpine?.data("nebulaCategoryTreeModal", (config = {}) => ({
        treeOpen: false,
        treeSearch: "",
        expanded: { ...config.expandedIds ?? {} },
        tree: config.tree ?? [],
        toggleExpand(id) {
          const key = String(id);
          this.expanded[key] = !this.expanded[key];
        },
        /**
         * Locate the sibling searchable-multiselect Alpine state. The
         * multiselect's factory is named `nebulaMultiselect` (an earlier
         * version looked for `nebulaSearchMultiselect`, which never
         * matched anything → checkbox toggles in the modal didn't sync
         * back to the multiselect → category IDs were lost on save).
         * Walks up from $el looking for any ancestor that contains the
         * multiselect — works regardless of where the snippet sits.
         */
        getMultiselect() {
          let node = this.$el;
          while (node && node !== document.body.parentElement) {
            const ms = node.querySelector?.('[x-data^="nebulaMultiselect"]');
            if (ms) {
              return window.Alpine?.$data(ms);
            }
            node = node.parentElement;
          }
          return null;
        },
        isChecked(id) {
          const ms = this.getMultiselect();
          return !!ms?.selectedValues.includes(String(id));
        },
        toggleCategory(id) {
          const ms = this.getMultiselect();
          if (!ms) return;
          const key = String(id);
          if (ms.selectedValues.includes(key)) {
            ms.selectedValues = ms.selectedValues.filter((v) => v !== key);
          } else {
            ms.selectedValues.push(key);
          }
        },
        /**
         * Show a node when the search box is empty OR the node's subtree
         * text (data-search-text on the wrapping element, set server-side)
         * contains the lowercased query. Auto-expand matching branches so
         * hits in collapsed subtrees become visible.
         */
        matchesSearch(el) {
          if (!this.treeSearch) return true;
          const dataset = el.dataset;
          const q = this.treeSearch.toLowerCase();
          const text = (dataset.searchText ?? "").toLowerCase();
          const matched = text.indexOf(q) !== -1;
          if (matched && dataset.nodeId) {
            this.expanded[dataset.nodeId] = true;
          }
          return matched;
        },
        clearSearch() {
          this.treeSearch = "";
        },
        expandAll() {
          const walk = (nodes) => {
            for (const n of nodes) {
              if (n.children && n.children.length) {
                this.expanded[String(n.id)] = true;
                walk(n.children);
              }
            }
          };
          walk(this.tree);
        },
        collapseAll() {
          this.expanded = {};
        }
      }));
    };
    document.addEventListener("alpine:init", install);
  }

  // ts/components/catalog/config-wizard.ts
  function registerConfigWizard() {
    const install = () => {
      window.Alpine?.data("nebulaConfigWizard", (config = {}) => ({
        wizardOpen: false,
        currentStep: 0,
        availableAttributes: config.availableAttributes ?? [],
        variations: (config.existingVariations ?? []).slice(),
        selectedAttributeIds: [],
        selectedOptions: {},
        previewVariations: [],
        init() {
          const models = window.Alpine?.store("nebulaModels");
          models?.register("section:configurable", this);
          const codes = config.usedAttributeCodes ?? [];
          if (codes.length === 0) return;
          this.selectedAttributeIds = this.availableAttributes.filter((a) => codes.includes(a.code)).map((a) => a.id);
          this.selectedAttributeIds.forEach((attrId) => {
            const attr = this.availableAttributes.find((a) => a.id === attrId);
            if (!attr) return;
            const usedValues = /* @__PURE__ */ new Set();
            this.variations.forEach((v) => {
              const code = attr.code;
              const val = v.attributes[code];
              if (val) usedValues.add(val.value);
            });
            this.selectedOptions[attrId] = Array.from(usedValues);
          });
        },
        get usedAttrs() {
          const codes = /* @__PURE__ */ new Set();
          this.variations.forEach((v) => {
            Object.keys(v.attributes ?? {}).forEach((c) => codes.add(c));
          });
          return this.availableAttributes.filter((a) => codes.has(a.code));
        },
        get selectedAttributes() {
          const ids = this.selectedAttributeIds;
          return this.availableAttributes.filter((a) => ids.includes(a.id));
        },
        get canProceed() {
          if (this.currentStep === 0) return this.selectedAttributeIds.length > 0;
          if (this.currentStep === 1) {
            return this.selectedAttributes.every((attr) => (this.selectedOptions[attr.id] ?? []).length > 0);
          }
          return true;
        },
        get generatedVariations() {
          const attrs = this.selectedAttributes;
          if (attrs.length === 0) return [];
          let combos = [{}];
          const parentSku = document.querySelector("[name=sku]")?.value ?? "SKU";
          attrs.forEach((attr) => {
            const opts = this.selectedOptions[attr.id] ?? [];
            const newCombos = [];
            combos.forEach((combo) => {
              opts.forEach((optValue) => {
                const c = { ...combo };
                c[attr.code] = optValue;
                newCombos.push(c);
              });
            });
            combos = newCombos;
          });
          return combos.map((combo) => {
            const skuParts = [parentSku];
            attrs.forEach((attr) => {
              const opt = attr.options.find((o) => o.value === combo[attr.code]);
              if (opt) skuParts.push(opt.label.replace(/\s+/g, "-"));
            });
            combo.sku = combo.sku || skuParts.join("-");
            combo.price = combo.price || "";
            combo.qty = combo.qty || "0";
            combo.status = combo.status || "1";
            return combo;
          });
        },
        openWizard() {
          this.currentStep = 0;
          this.wizardOpen = true;
        },
        nextStep() {
          if (!this.canProceed || this.currentStep >= 2) return;
          if (this.currentStep === 0) {
            this.selectedAttributeIds.forEach((id) => {
              if (!this.selectedOptions[id]) this.selectedOptions[id] = [];
            });
          }
          this.currentStep++;
          if (this.currentStep === 2) {
            this.previewVariations = this.generatedVariations;
          }
        },
        isOptionSelected(attrId, optValue) {
          return (this.selectedOptions[attrId] ?? []).includes(optValue);
        },
        toggleOption(attrId, optValue) {
          const bucket = this.selectedOptions[attrId] ?? (this.selectedOptions[attrId] = []);
          const idx = bucket.indexOf(optValue);
          if (idx >= 0) bucket.splice(idx, 1);
          else bucket.push(optValue);
        },
        toggleAllOptions(attrId) {
          const attr = this.availableAttributes.find((a) => a.id === attrId);
          if (!attr) return;
          const allValues = attr.options.map((o) => o.value);
          if ((this.selectedOptions[attrId] ?? []).length === allValues.length) {
            this.selectedOptions[attrId] = [];
          } else {
            this.selectedOptions[attrId] = allValues.slice();
          }
        },
        getOptionLabel(attrId, optValue) {
          const attr = this.availableAttributes.find((a) => a.id === attrId);
          if (!attr) return optValue;
          const opt = attr.options.find((o) => o.value === optValue);
          return opt ? opt.label : optValue;
        },
        serialize() {
          const pairs = [];
          this.variations.forEach((variation, i) => {
            this.usedAttrs.forEach((attr) => {
              const vAttr = variation.attributes[attr.code];
              pairs.push({
                name: "configurable_variations[" + String(i) + "][attributes][" + attr.code + "]",
                value: vAttr ? vAttr.value : ""
              });
            });
            pairs.push({ name: "configurable_variations[" + String(i) + "][sku]", value: variation.sku });
            pairs.push({ name: "configurable_variations[" + String(i) + "][name]", value: variation.name });
            pairs.push({ name: "configurable_variations[" + String(i) + "][price]", value: variation.price });
            pairs.push({ name: "configurable_variations[" + String(i) + "][status]", value: variation.status });
            if (variation.id) {
              pairs.push({ name: "configurable_variations[" + String(i) + "][id]", value: variation.id });
            }
          });
          pairs.push({
            name: "associated_product_ids_serialized",
            value: JSON.stringify(this.variations.filter((v) => v.id).map((v) => v.id))
          });
          (config.usedAttributeIds ?? []).forEach((attrId) => {
            pairs.push({ name: "attributes[]", value: attrId });
          });
          pairs.push({ name: "affect_configurable_product_attributes", value: "1" });
          return pairs;
        },
        destroy() {
          const models = window.Alpine?.store("nebulaModels");
          models?.unregister("section:configurable");
        },
        applyVariations() {
          this.variations = this.previewVariations.map((v) => {
            const attrs = {};
            this.selectedAttributes.forEach((attr) => {
              attrs[attr.code] = {
                value: v[attr.code] ?? "",
                label: this.getOptionLabel(attr.id, v[attr.code] ?? "")
              };
            });
            return {
              id: null,
              sku: v.sku ?? "",
              name: v.sku ?? "",
              price: v.price ?? "",
              qty: v.qty ?? "0",
              status: parseInt(String(v.status), 10) || 1,
              attributes: attrs,
              isNew: true
            };
          });
          this.wizardOpen = false;
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/catalog/media-gallery.ts
  function registerMediaGallery() {
    const install = () => {
      window.Alpine?.data("nebulaMediaGallery", (config = {}) => ({
        images: (config.images ?? []).slice(),
        baseImage: config.baseImage ?? "",
        smallImage: config.smallImage ?? "",
        thumbnailImage: config.thumbnailImage ?? "",
        swatchImage: config.swatchImage ?? "",
        uploadUrl: config.uploadUrl ?? "",
        formKey: config.formKey ?? "",
        uploadDragging: false,
        uploading: false,
        dragIndex: null,
        dragOverIndex: null,
        get visibleImages() {
          return this.images.filter((i) => !i.removed);
        },
        get removedImages() {
          return this.images.filter((i) => i.removed && i.value_id);
        },
        getRealIndex(img) {
          return this.images.indexOf(img);
        },
        startDrag(index, event) {
          this.dragIndex = index;
          if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("text/plain", String(index));
          }
        },
        endDrag() {
          this.dragIndex = null;
          this.dragOverIndex = null;
        },
        dragOver(index) {
          if (this.dragIndex === null || this.dragIndex === index) return;
          this.dragOverIndex = index;
        },
        drop(index) {
          if (this.dragIndex === null || this.dragIndex === index) {
            this.dragIndex = null;
            this.dragOverIndex = null;
            return;
          }
          const item = this.images.splice(this.dragIndex, 1)[0];
          if (item) {
            this.images.splice(index, 0, item);
          }
          this.images.forEach((img, i) => {
            img.position = i + 1;
          });
          this.dragIndex = null;
          this.dragOverIndex = null;
        },
        handleDrop(event) {
          this.uploadDragging = false;
          if (event.dataTransfer?.files.length) {
            void this.uploadFiles(event.dataTransfer.files);
          }
        },
        handleFiles(event) {
          const input = event.target;
          if (input.files) {
            void this.uploadFiles(input.files);
          }
          input.value = "";
        },
        async uploadFiles(files) {
          this.uploading = true;
          for (const file of Array.from(files)) {
            await this.uploadFile(file);
          }
          this.uploading = false;
        },
        async uploadFile(file) {
          const formData = new FormData();
          formData.append("image", file);
          formData.append("form_key", this.formKey);
          try {
            const resp = await fetch(this.uploadUrl, {
              method: "POST",
              body: formData,
              credentials: "same-origin",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            if (!resp.ok) {
              throw new Error("HTTP " + String(resp.status));
            }
            const result = await resp.json();
            if (!result.error && result.file && result.url) {
              const newImg = {
                value_id: null,
                file: result.file,
                url: result.url,
                label: "",
                position: this.images.length + 1,
                disabled: false,
                removed: false,
                media_type: "image",
                new: true
              };
              this.images.push(newImg);
              if (this.visibleImages.length === 1) {
                this.baseImage = result.file;
                this.smallImage = result.file;
                this.thumbnailImage = result.file;
                this.swatchImage = result.file;
              }
            } else {
              window.nebulaToast?.("error", "Upload error: " + (result.message ?? "Unknown error"));
            }
          } catch (e) {
            console.error("Upload failed:", e);
            window.nebulaToast?.("error", "Upload failed. Check console for details.");
          }
        },
        removeImage(index) {
          const img = this.images[index];
          if (!img) return;
          img.removed = true;
          const file = img.file;
          if (this.baseImage === file) this.baseImage = "";
          if (this.smallImage === file) this.smallImage = "";
          if (this.thumbnailImage === file) this.thumbnailImage = "";
          if (this.swatchImage === file) this.swatchImage = "";
        }
      }));
    };
    document.addEventListener("alpine:init", install);
  }

  // ts/components/catalog/seo-preview.ts
  function createSeoPreview(config) {
    return {
      title: config.title,
      url: config.url,
      description: config.description,
      baseUrl: config.baseUrl,
      init() {
        const self = this;
        const listen = (attrCode, prop) => {
          const el = document.getElementById("nebula-eav-" + attrCode) ?? document.querySelector(
            "[name=" + attrCode + '], [name$="[' + attrCode + ']"]'
          );
          if (el) {
            el.addEventListener("input", (e) => {
              self[prop] = e.target.value;
            });
          }
        };
        listen("meta_title", "title");
        listen("url_key", "url");
        listen("meta_description", "description");
        listen("name", "title");
      }
    };
  }
  function registerSeoPreview() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaSeoPreview", (config) => createSeoPreview(config));
    });
  }

  // ts/components/catalog/stock-fields.ts
  function registerStockFields() {
    const install = () => {
      window.Alpine?.data("nebulaStockFields", (config = {}) => ({
        showAdvanced: false,
        fields: config
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/catalog/tier-prices.ts
  function registerTierPrices() {
    const install = () => {
      window.Alpine?.data("nebulaTierPrices", (initial = []) => ({
        rows: initial.slice(),
        addRow() {
          this.rows.push({
            website_id: "0",
            cust_group: "32000",
            price_qty: "",
            price: "",
            value_type: "fixed"
          });
        },
        removeRow(index) {
          this.rows.splice(index, 1);
        }
      }));
    };
    document.addEventListener("alpine:init", install);
  }

  // ts/pages/catalog-product.ts
  registerAttributeOptions();
  registerAttributeSetEditor();
  registerBundleSettings();
  registerCategoryNavTree();
  registerCategoryProducts();
  registerCategoryTreeModal();
  registerConfigWizard();
  registerMediaGallery();
  registerSeoPreview();
  registerStockFields();
  registerTierPrices();
})();
//# sourceMappingURL=nebula-catalog-product.js.map
