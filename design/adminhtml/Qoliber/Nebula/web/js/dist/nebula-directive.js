"use strict";
(() => {
  // ts/components/directive/variable-picker.ts
  async function parseDirectiveResponse(response) {
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.data) {
      throw new Error(payload.message ?? "Directive request failed.");
    }
    return payload.data;
  }
  function getDirectiveAttribute(directive, name) {
    const match = directive.match(new RegExp(name + '=(?:"([^"]*)"|([^\\s}]+))'));
    if (!match) {
      return null;
    }
    const value = match[1] ?? match[2] ?? "";
    try {
      return decodeURIComponent(value);
    } catch {
      return value;
    }
  }
  function getVariableSelectionKey(directive) {
    if (directive.startsWith("{{config")) {
      const path = getDirectiveAttribute(directive, "path");
      return path ? "default:" + path : null;
    }
    if (directive.startsWith("{{customVar")) {
      const code = getDirectiveAttribute(directive, "code");
      return code ? "custom:" + code : null;
    }
    return null;
  }
  function registerVariablePicker() {
    const install = () => {
      window.Alpine?.data("nebulaVariablePicker", (config) => ({
        isOpen: false,
        loading: false,
        variables: [],
        search: "",
        selected: null,
        resolver: null,
        get filtered() {
          const q = this.search.toLowerCase();
          if (!q) {
            return this.variables;
          }
          return this.variables.filter(
            (v) => v.label.toLowerCase().includes(q) || v.group.toLowerCase().includes(q) || v.code.toLowerCase().includes(q)
          );
        },
        init() {
          const directiveApi = {
            ...window.NebulaDirective ?? {},
            openVariable: (options) => this.open(options)
          };
          const meta = config.meta ?? window.NebulaDirective?.meta;
          if (meta) {
            directiveApi.meta = meta;
          }
          window.NebulaDirective = directiveApi;
        },
        async open(options) {
          this.isOpen = true;
          this.search = "";
          this.selected = null;
          this.lockPageScroll();
          if (this.variables.length === 0) {
            await this.loadVariables();
          }
          if (options?.directive) {
            const key = getVariableSelectionKey(options.directive);
            if (key) {
              this.selected = this.variables.find((variable) => key === variable.type + ":" + variable.code) ?? null;
            }
          }
          return new Promise((resolve) => {
            this.resolver = resolve;
          });
        },
        close() {
          this.isOpen = false;
          this.unlockPageScroll();
          const resolver = this.resolver;
          this.resolver = null;
          if (resolver) {
            resolver(null);
          }
        },
        select(variable) {
          this.selected = variable;
        },
        insertSelected() {
          if (!this.selected || !this.resolver) {
            return;
          }
          const resolver = this.resolver;
          this.resolver = null;
          this.isOpen = false;
          this.unlockPageScroll();
          resolver(this.selected.directive);
        },
        async loadVariables() {
          this.loading = true;
          try {
            const response = await fetch(config.variableUrl, {
              method: "GET",
              credentials: "same-origin",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const data = await parseDirectiveResponse(response);
            this.variables = data.variables;
          } catch {
          } finally {
            this.loading = false;
          }
        },
        lockPageScroll() {
          document.documentElement.style.overflow = "hidden";
          document.body.style.overflow = "hidden";
        },
        unlockPageScroll() {
          document.documentElement.style.overflow = "";
          document.body.style.overflow = "";
        }
      }));
    };
    if (window.Alpine) {
      install();
    }
    document.addEventListener("alpine:init", install);
  }

  // ts/rule-editor.ts
  var OPERATORS_BY_INPUT = {
    string: ["==", "!=", ">=", ">", "<=", "<", "{}", "!{}", "()", "!()"],
    numeric: ["==", "!=", ">=", ">", "<=", "<", "()", "!()"],
    date: ["==", ">=", "<="],
    select: ["==", "!=", "<=>"],
    boolean: ["==", "!=", "<=>"],
    multiselect: ["{}", "!{}", "()", "!()"],
    category: ["==", "!=", "()", "!()", "<=>"],
    grid: ["()", "!()"]
  };
  var OPERATOR_LABELS = {
    "==": "is",
    "!=": "is not",
    ">=": "equals or greater than",
    ">": "greater than",
    "<=": "equals or less than",
    "<": "less than",
    "{}": "contains",
    "!{}": "does not contain",
    "()": "is one of",
    "!()": "is not one of",
    "<=>": "is undefined"
  };
  var _moduleIdCounter = 0;
  function _moduleNextId() {
    return "n" + String(++_moduleIdCounter);
  }
  function getInputType(code, attrs) {
    if (!code) return "string";
    const a = attrs.find((x) => x.value === code);
    return a?.inputType ?? "string";
  }
  function getOperatorsForType(type) {
    return (OPERATORS_BY_INPUT[type] ?? OPERATORS_BY_INPUT.string).map((c) => ({
      value: c,
      label: OPERATOR_LABELS[c] ?? c
    }));
  }
  function buildNode(data, attrs, nextIdFn = _moduleNextId) {
    const isCombine = Array.isArray(data.conditions);
    return {
      id: nextIdFn(),
      type: isCombine ? "combine" : "leaf",
      className: data.type ?? "",
      aggregator: data.aggregator ?? "all",
      attribute: data.attribute ?? null,
      operator: data.operator ?? "==",
      value: data.value != null ? String(data.value) : "",
      inputType: isCombine ? "string" : getInputType(data.attribute, attrs),
      children: isCombine ? (data.conditions ?? []).map((c) => buildNode(c, attrs, nextIdFn)) : []
    };
  }
  function serializeNode(node, path, prefix, pairs) {
    const p = prefix + "[conditions][" + path + "]";
    pairs.push({ name: p + "[type]", value: node.className });
    if (node.type === "combine") {
      pairs.push({ name: p + "[aggregator]", value: node.aggregator });
      pairs.push({ name: p + "[value]", value: "1" });
      node.children.forEach((child, i) => {
        serializeNode(child, path + "--" + String(i + 1), prefix, pairs);
      });
    } else {
      pairs.push({ name: p + "[attribute]", value: node.attribute ?? "" });
      pairs.push({ name: p + "[operator]", value: node.operator });
      pairs.push({ name: p + "[value]", value: node.value });
    }
  }
  function findNodeByPath(root, pathStr) {
    if (pathStr === "root") return root;
    const parts = pathStr.split(".").filter((p) => p !== "root");
    let node = root;
    for (const part of parts) {
      const m = part.match(/^children\[(\d+)\]$/);
      if (m && node && node.children) {
        const idx = parseInt(m[1] ?? "0", 10);
        node = node.children[idx] ?? null;
      } else {
        return null;
      }
    }
    return node;
  }
  var _escEl = document.createElement("div");
  function esc(str) {
    _escEl.textContent = str == null ? "" : String(str);
    return _escEl.innerHTML;
  }
  function defaultCombineClass(ruleType) {
    return ruleType === "sales" ? "Magento\\SalesRule\\Model\\Rule\\Condition\\Combine" : "Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine";
  }
  function createRuleEditor(config = {}) {
    let idCounter = 0;
    function nextId() {
      return "n" + String(++idCounter);
    }
    const availableAttributes = config.availableAttributes ?? [];
    const conditionTypes = config.conditionTypes ?? [];
    const ruleType = config.ruleType ?? "catalog";
    const fieldPrefix = config.fieldPrefix ?? "rule";
    const state = {
      rootNode: null,
      availableAttributes,
      conditionTypes,
      ruleType,
      fieldPrefix,
      init() {
        if (config.conditions && config.conditions.type) {
          this.rootNode = buildNode(config.conditions, this.availableAttributes, nextId);
        } else {
          this.rootNode = {
            id: nextId(),
            type: "combine",
            className: defaultCombineClass(this.ruleType),
            aggregator: "all",
            attribute: null,
            operator: "==",
            value: "1",
            inputType: "string",
            children: []
          };
        }
        const models = window.Alpine?.store("nebulaModels");
        if (config.registerModel !== false && models) {
          models.register("section:conditions", {
            serialize: () => this.serialize()
          });
        }
        const self = this;
        this.$nextTick?.(() => {
          const el = self.$el;
          if (!el) return;
          el.addEventListener("change", (e) => self._handleChange(e));
          el.addEventListener("click", (e) => self._handleClick(e));
          el.addEventListener("input", (e) => self._handleInput(e));
        });
      },
      serialize() {
        const pairs = [];
        if (this.rootNode) serializeNode(this.rootNode, "1", this.fieldPrefix, pairs);
        return pairs;
      },
      _rerender() {
        this.rootNode = JSON.parse(JSON.stringify(this.rootNode));
      },
      _handleClick(e) {
        if (!this.rootNode) return;
        const target = e.target;
        if (!target) return;
        const btn = target.closest("[data-action]");
        if (!btn) return;
        const action = btn.dataset["action"];
        const path = btn.dataset["path"] ?? "root";
        const parent = findNodeByPath(this.rootNode, path === "root" ? "root" : path);
        if (action === "add-condition" && parent) {
          const cls = this.conditionTypes.length ? this.conditionTypes[0].value : "";
          parent.children.push({
            id: nextId(),
            type: "leaf",
            className: cls,
            aggregator: "all",
            attribute: null,
            operator: "==",
            value: "",
            inputType: "string",
            children: []
          });
          this._rerender();
        } else if (action === "add-group" && parent) {
          parent.children.push({
            id: nextId(),
            type: "combine",
            className: defaultCombineClass(this.ruleType),
            aggregator: "all",
            attribute: null,
            operator: "==",
            value: "1",
            inputType: "string",
            children: []
          });
          this._rerender();
        } else if (action === "remove") {
          const parentPath = btn.dataset["parent"];
          const idx = parseInt(btn.dataset["index"] ?? "NaN", 10);
          if (!parentPath) return;
          const parentNode = findNodeByPath(
            this.rootNode,
            parentPath === "root" ? "root" : parentPath
          );
          if (parentNode && !isNaN(idx)) {
            parentNode.children.splice(idx, 1);
            this._rerender();
          }
        }
      },
      _handleChange(e) {
        if (!this.rootNode) return;
        const el = e.target;
        if (!el) return;
        const role = el.dataset["role"];
        if (!role) return;
        const path = el.dataset["path"];
        if (!path) return;
        const node = findNodeByPath(this.rootNode, path === "root" ? "root" : path);
        if (!node) return;
        if (role === "aggregator") {
          node.aggregator = el.value === "any" ? "any" : "all";
          this._rerender();
        } else if (role === "attribute") {
          node.attribute = el.value;
          node.inputType = getInputType(el.value, this.availableAttributes);
          const attrDef = this.availableAttributes.find((a) => a.value === el.value);
          if (attrDef && attrDef.conditionClass) {
            node.className = attrDef.conditionClass;
          }
          const ops = getOperatorsForType(node.inputType);
          node.operator = ops.length ? ops[0].value : "==";
          node.value = "";
          this._rerender();
        } else if (role === "operator") {
          node.operator = el.value;
          if (el.value === "<=>") node.value = "";
          this._rerender();
        } else if (role === "value") {
          if (el instanceof HTMLSelectElement && el.multiple) {
            node.value = Array.from(el.selectedOptions).map((o) => o.value).join(",");
          } else {
            node.value = el.value;
          }
        }
      },
      _handleInput(e) {
        if (!this.rootNode) return;
        const el = e.target;
        if (!el) return;
        if (el.dataset["role"] === "value") {
          const path = el.dataset["path"];
          if (!path) return;
          const node = findNodeByPath(this.rootNode, path === "root" ? "root" : path);
          if (node) node.value = el.value;
        }
      },
      renderTree() {
        if (!this.rootNode) return '<div class="text-sm text-gray-400">Loading conditions...</div>';
        return this._renderCombine(this.rootNode, 0, "root");
      },
      _renderCombine(node, depth, path) {
        const indent = depth > 0 ? "ml-6 mt-2" : "";
        const bg = depth === 0 ? "bg-gray-50 border-gray-200" : "bg-indigo-50/30 border-indigo-200/50";
        let h = '<div class="' + indent + " p-4 rounded-lg border " + bg + ' space-y-2">';
        h += '<div class="flex items-center gap-2 text-sm flex-wrap">';
        h += '<span class="font-medium text-gray-700">If</span>';
        h += '<select data-role="aggregator" data-path="' + esc(path) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"><option value="all"' + (node.aggregator === "all" ? " selected" : "") + '>ALL</option><option value="any"' + (node.aggregator === "any" ? " selected" : "") + ">ANY</option></select>";
        h += '<span class="text-gray-600">of these conditions are</span>';
        h += '<span class="font-semibold text-gray-900">TRUE</span>';
        h += '<span class="text-gray-400">:</span>';
        if (depth > 0) {
          const parentPath = path.substring(0, path.lastIndexOf("."));
          const idxMatch = path.match(/\[(\d+)\]$/);
          const idx = idxMatch ? idxMatch[1] : void 0;
          if (idx !== void 0) {
            h += '<button type="button" data-action="remove" data-parent="' + esc(parentPath) + '" data-index="' + idx + '" class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer" title="Remove group"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';
          }
        }
        h += "</div>";
        for (let i = 0; i < node.children.length; i++) {
          const childPath = path + ".children[" + i + "]";
          const child = node.children[i];
          if (!child) continue;
          if (child.type === "combine") {
            h += this._renderCombine(child, depth + 1, childPath);
          } else {
            h += this._renderLeaf(child, i, path, childPath);
          }
        }
        h += '<div class="flex items-center gap-2 pt-1">';
        h += '<button type="button" data-action="add-condition" data-path="' + esc(path) + '" class="inline-flex items-center gap-1 rounded-md bg-white border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition cursor-pointer"><svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg> Condition</button>';
        h += '<button type="button" data-action="add-group" data-path="' + esc(path) + '" class="inline-flex items-center gap-1 rounded-md bg-white border border-dashed border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:border-gray-400 transition cursor-pointer"><svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg> Group</button>';
        h += "</div></div>";
        return h;
      },
      _renderLeaf(node, index, parentPath, nodePath) {
        let h = '<div class="ml-6 mt-1 flex items-center gap-2 rounded-md bg-white border border-gray-200 px-3 py-2 text-sm flex-wrap shadow-sm">';
        h += '<select data-role="attribute" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"><option value="">-- select --</option>';
        this.availableAttributes.forEach((a) => {
          h += '<option value="' + esc(a.value) + '"' + (a.value === node.attribute ? " selected" : "") + ">" + esc(a.label) + "</option>";
        });
        h += "</select>";
        if (node.attribute) {
          const ops = getOperatorsForType(node.inputType);
          h += '<select data-role="operator" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">';
          ops.forEach((op) => {
            h += '<option value="' + esc(op.value) + '"' + (op.value === node.operator ? " selected" : "") + ">" + esc(op.label) + "</option>";
          });
          h += "</select>";
          if (node.operator !== "<=>") {
            const attr = this.availableAttributes.find((a) => a.value === node.attribute);
            const opts = attr?.options ?? [];
            const mode = this.getValueMode(node);
            if (mode === "select" && opts.length) {
              h += '<select data-role="value" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"><option value="">--</option>';
              opts.forEach((o) => {
                h += '<option value="' + esc(o.value) + '"' + (String(o.value) === String(node.value) ? " selected" : "") + ">" + esc(o.label) + "</option>";
              });
              h += "</select>";
            } else if (mode === "multiselect" && opts.length) {
              const sel = node.value ? String(node.value).split(",") : [];
              h += '<select multiple data-role="value" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none min-h-[50px]">';
              opts.forEach((o) => {
                h += '<option value="' + esc(o.value) + '"' + (sel.includes(String(o.value)) ? " selected" : "") + ">" + esc(o.label) + "</option>";
              });
              h += "</select>";
            } else {
              h += '<input type="text" data-role="value" data-path="' + esc(nodePath) + '" value="' + esc(node.value) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none w-36">';
            }
          }
        }
        h += '<button type="button" data-action="remove" data-parent="' + esc(parentPath) + '" data-index="' + String(index) + '" class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer flex-shrink-0" title="Remove"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';
        h += "</div>";
        return h;
      },
      getValueMode(_node) {
        if (_node.operator === "<=>") return "hidden";
        const t = _node.inputType;
        if (t === "category") {
          return _node.operator === "()" || _node.operator === "!()" ? "multiselect" : "select";
        }
        if (t === "select" || t === "boolean") return "select";
        if (t === "multiselect") return "multiselect";
        return "text";
      }
    };
    return state;
  }

  // ts/components/directive/widget-picker.ts
  async function parseWidgetResponse(response) {
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.data) {
      throw new Error(payload.message ?? "Widget request failed.");
    }
    return payload.data;
  }
  function getDirectiveAttribute2(directive, name) {
    const match = directive.match(new RegExp(name + '=(?:"([^"]*)"|([^\\s}]+))'));
    if (!match) {
      return null;
    }
    const value = match[1] ?? match[2] ?? "";
    try {
      return decodeURIComponent(value);
    } catch {
      return value;
    }
  }
  function parseWidgetDirective(directive) {
    if (!directive.startsWith("{{widget")) {
      return null;
    }
    const type = getDirectiveAttribute2(directive, "type");
    if (!type) {
      return null;
    }
    const values = {};
    const attributePattern = /([a-zA-Z0-9_]+)=(?:"([^"]*)"|([^\s}]+))/g;
    let match = attributePattern.exec(directive);
    while (match) {
      const key = match[1] ?? "";
      if (key !== "" && key !== "type") {
        const rawValue = match[2] ?? match[3] ?? "";
        try {
          values[key] = decodeURIComponent(rawValue);
        } catch {
          values[key] = rawValue;
        }
      }
      match = attributePattern.exec(directive);
    }
    return { type, values };
  }
  function registerWidgetPicker() {
    const install = () => {
      window.Alpine?.data("nebulaWidgetPicker", (config) => ({
        isOpen: false,
        loading: false,
        errorMessage: "",
        types: [],
        selectedType: "",
        params: [],
        values: {},
        chooserLabels: {},
        pendingValues: {},
        ruleEditors: {},
        ruleEditorVersion: 0,
        resolver: null,
        init() {
          const directiveApi = {
            ...window.NebulaDirective ?? {},
            openWidget: (options) => this.open(options)
          };
          const meta = config.meta ?? window.NebulaDirective?.meta;
          if (meta) {
            directiveApi.meta = meta;
          }
          window.NebulaDirective = directiveApi;
        },
        async open(options) {
          this.isOpen = true;
          this.errorMessage = "";
          this.selectedType = "";
          this.params = [];
          this.values = {};
          this.chooserLabels = {};
          this.pendingValues = {};
          this.ruleEditors = {};
          this.ruleEditorVersion = 0;
          this.lockPageScroll();
          if (this.types.length === 0) {
            await this.loadTypes();
          }
          if (options?.directive) {
            const parsed = parseWidgetDirective(options.directive);
            if (parsed) {
              this.selectedType = parsed.type;
              this.pendingValues = parsed.values;
              await this.onTypeChange();
            }
          }
          return new Promise((resolve) => {
            this.resolver = resolve;
          });
        },
        close() {
          this.isOpen = false;
          this.unlockPageScroll();
          const resolver = this.resolver;
          this.resolver = null;
          if (resolver) {
            resolver(null);
          }
        },
        async loadTypes() {
          this.loading = true;
          try {
            const response = await fetch(config.widgetTypesUrl, {
              method: "GET",
              credentials: "same-origin",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const data = await parseWidgetResponse(response);
            this.types = data.types;
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to load widget types.";
          } finally {
            this.loading = false;
          }
        },
        async onTypeChange() {
          if (!this.selectedType) {
            this.params = [];
            this.values = {};
            this.chooserLabels = {};
            this.pendingValues = {};
            this.ruleEditors = {};
            return;
          }
          this.loading = true;
          this.errorMessage = "";
          this.params = [];
          this.values = {};
          this.chooserLabels = {};
          this.ruleEditors = {};
          this.ruleEditorVersion = 0;
          try {
            const url = new URL(config.widgetParamsUrl, window.location.origin);
            url.searchParams.set("type", this.selectedType);
            if (Object.keys(this.pendingValues).length > 0) {
              url.searchParams.set("current_values", JSON.stringify(this.pendingValues));
            }
            const response = await fetch(url.toString(), {
              method: "GET",
              credentials: "same-origin",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const data = await parseWidgetResponse(response);
            this.params = data.params;
            const defaults = {};
            for (const param of data.params) {
              defaults[param.name] = param.default;
              if (param.type === "chooser" && param.selectedLabel) {
                this.chooserLabels[param.name] = param.selectedLabel;
              }
            }
            this.values = {
              ...defaults,
              ...this.pendingValues
            };
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to load widget parameters.";
          } finally {
            this.loading = false;
          }
        },
        async insertSelected() {
          if (!this.selectedType || !this.resolver) {
            return;
          }
          this.loading = true;
          this.errorMessage = "";
          try {
            const formData = new FormData();
            formData.append("form_key", config.formKey);
            formData.append("widget_type", this.selectedType);
            for (const param of this.params) {
              if (param.type !== "conditions" && !this.shouldSubmitField(param)) {
                continue;
              }
              if (param.type === "conditions") {
                const editor = this.ruleEditors[param.name];
                if (!editor) {
                  continue;
                }
                const serialized = editor.serialize();
                if (serialized.length === 0) {
                  continue;
                }
                for (const pair of serialized) {
                  formData.append(pair.name, String(pair.value));
                }
                continue;
              }
              const value = this.values[param.name] ?? "";
              if (value !== "") {
                formData.append("parameters[" + param.name + "]", value);
              }
            }
            const response = await fetch(config.widgetBuildUrl, {
              method: "POST",
              body: formData,
              credentials: "same-origin",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const data = await parseWidgetResponse(response);
            const resolver = this.resolver;
            this.resolver = null;
            this.isOpen = false;
            this.unlockPageScroll();
            resolver(data.directive);
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to build the widget directive.";
          } finally {
            this.loading = false;
          }
        },
        lockPageScroll() {
          document.documentElement.style.overflow = "hidden";
          document.body.style.overflow = "hidden";
        },
        unlockPageScroll() {
          document.documentElement.style.overflow = "";
          document.body.style.overflow = "";
        },
        isFieldVisible(param) {
          if (param.visible === false) {
            return false;
          }
          if (!param.depends || param.depends.length === 0) {
            return true;
          }
          return param.depends.every(
            (depend) => String(this.values[depend.param] ?? "") === depend.value
          );
        },
        shouldSubmitField(param) {
          if (param.visible === false) {
            return true;
          }
          return this.isFieldVisible(param);
        },
        isChooserAvailable(alias) {
          return typeof alias === "string" && alias !== "" && alias !== "generic";
        },
        getFieldValue(name) {
          return String(this.values[name] ?? "");
        },
        setFieldValue(name, value) {
          this.values = {
            ...this.values,
            [name]: value
          };
        },
        getMultiValues(name) {
          return this.getFieldValue(name).split(",").map((value) => value.trim()).filter((value) => value !== "");
        },
        isMultiValueChecked(name, optionValue) {
          return this.getMultiValues(name).includes(String(optionValue));
        },
        toggleMultiValue(name, optionValue, checked) {
          const nextValues = this.getMultiValues(name).filter((value) => value !== String(optionValue));
          if (checked) {
            nextValues.push(String(optionValue));
          }
          this.setFieldValue(name, nextValues.join(","));
        },
        isSelectOptionSelected(name, optionValue, multiple = false) {
          const currentValue = this.getFieldValue(name);
          if (!multiple) {
            return currentValue === String(optionValue);
          }
          return currentValue.split(",").map((value) => value.trim()).filter((value) => value !== "").includes(String(optionValue));
        },
        getChooserLabel(param) {
          return this.chooserLabels[param.name] ?? this.getFieldValue(param.name) ?? "";
        },
        openChooser(param) {
          if (!this.isChooserAvailable(param.chooser)) {
            return;
          }
          window.dispatchEvent(
            new CustomEvent("open-chooser-" + String(param.chooser).replace(/_/g, "-"), {
              detail: {
                fieldName: param.name
              }
            })
          );
        },
        handleChooserSelected(detail) {
          if (!detail.fieldName || !this.params.some((param) => param.name === detail.fieldName)) {
            return;
          }
          this.setFieldValue(detail.fieldName, String(detail.value ?? ""));
          this.chooserLabels = {
            ...this.chooserLabels,
            [detail.fieldName]: String(detail.label ?? detail.value ?? "")
          };
        },
        mountRuleEditor(element, param) {
          if (param.type !== "conditions" || !param.ruleEditorConfig) {
            return;
          }
          if (this.ruleEditors[param.name]) {
            return;
          }
          const editor = createRuleEditor({
            ...param.ruleEditorConfig,
            registerModel: false
          });
          const originalRerender = editor._rerender.bind(editor);
          editor._rerender = () => {
            originalRerender();
            this.ruleEditorVersion += 1;
          };
          editor.$el = element;
          editor.$nextTick = (callback) => callback();
          editor.init();
          this.ruleEditors[param.name] = editor;
          this.ruleEditorVersion += 1;
        },
        renderRuleEditor(name) {
          this.ruleEditorVersion;
          return this.ruleEditors[name]?.renderTree() ?? "";
        }
      }));
    };
    if (window.Alpine) {
      install();
    }
    document.addEventListener("alpine:init", install);
  }

  // ts/pages/directive.ts
  registerVariablePicker();
  registerWidgetPicker();
})();
//# sourceMappingURL=nebula-directive.js.map
