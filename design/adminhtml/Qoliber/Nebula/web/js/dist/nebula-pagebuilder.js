"use strict";
(() => {
  // ts/pages/pagebuilder.ts
  function createPageBuilder() {
    const state = {
      contentTypes: {},
      renderUrl: "",
      parseUrl: "",
      fieldName: "content",
      formKey: "",
      tree: [],
      masterHtml: "",
      editingNodeId: null,
      syncing: false,
      editorOpen: false,
      _submitPending: false,
      dragType: null,
      dragNodeId: null,
      dropTarget: null,
      initPageBuilder() {
        const configEl = this.$refs?.["pbConfig"];
        if (configEl) {
          try {
            const cfg = JSON.parse(configEl.textContent ?? "{}");
            this.contentTypes = cfg.contentTypes ?? {};
            this.renderUrl = cfg.renderUrl ?? "";
            this.parseUrl = cfg.parseUrl ?? "";
            this.fieldName = cfg.fieldName ?? "content";
            this.masterHtml = cfg.initialHtml ?? "";
            this.formKey = cfg.formKey ?? "";
            if (!this.formKey) {
              const fkInput = document.querySelector(
                'input[name="form_key"]'
              );
              if (fkInput) this.formKey = fkInput.value;
            }
          } catch (e) {
            console.error("PageBuilder: config parse failed", e);
          }
        }
        if (this.masterHtml && this.masterHtml.trim() !== "" && this.masterHtml.indexOf("data-content-type") !== -1) {
          void this.parseHtml(this.masterHtml);
        }
        const form = this.$el?.closest("form");
        if (form) {
          form.addEventListener("submit", (e) => {
            if (state._submitPending) return;
            if (state.tree.length === 0) {
              state.masterHtml = "";
              return;
            }
            e.preventDefault();
            void state.syncMasterFormat().then(() => {
              state._submitPending = true;
              form.submit();
            });
          });
        }
      },
      generateId() {
        return "pb_" + Math.random().toString(36).substring(2, 14);
      },
      getContentType(type) {
        return this.contentTypes[type] ?? null;
      },
      panelDragStart(e, type) {
        this.dragType = type;
        this.dragNodeId = null;
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = "copy";
          e.dataTransfer.setData("text/plain", "pb:" + type);
        }
        const target = e.target;
        if (target && typeof target.cloneNode === "function") {
          const ghost = target.cloneNode(true);
          ghost.style.width = "80px";
          ghost.style.opacity = "0.8";
          document.body.appendChild(ghost);
          e.dataTransfer?.setDragImage(ghost, 40, 20);
          setTimeout(() => {
            document.body.removeChild(ghost);
          }, 0);
        }
      },
      panelDragEnd() {
        this.dragType = null;
        this.dropTarget = null;
      },
      nodeDragStart(e, nodeId) {
        this.dragNodeId = nodeId;
        this.dragType = null;
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = "move";
          e.dataTransfer.setData("text/plain", "pb-node:" + nodeId);
        }
        const target = e.target;
        if (target) target.style.opacity = "0.5";
      },
      nodeDragEnd(e) {
        this.dragNodeId = null;
        this.dropTarget = null;
        const target = e.target;
        if (target) target.style.opacity = "";
      },
      zoneEnter(e, parentType, parentId) {
        e.preventDefault();
        e.stopPropagation();
        this.dropTarget = { parentType, parentId };
      },
      zoneOver(e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) {
          e.dataTransfer.dropEffect = this.dragNodeId ? "move" : "copy";
        }
      },
      zoneLeave(e, _parentType, parentId) {
        const related = e.relatedTarget;
        const current = e.currentTarget;
        if (related && current?.contains(related)) return;
        if (this.dropTarget && this.dropTarget.parentId === parentId) {
          this.dropTarget = null;
        }
      },
      zoneDrop(e, parentType, parentId) {
        e.preventDefault();
        e.stopPropagation();
        this.dropTarget = null;
        if (this.dragType) {
          const type = this.dragType;
          this.dragType = null;
          if (parentType === "stage") {
            this.addToStage(type);
          } else if (parentId) {
            this.addNodeToParent(type, this.findNode(parentId));
          }
        } else if (this.dragNodeId) {
          const id = this.dragNodeId;
          this.dragNodeId = null;
          const info = this.findParentAndIndex(id);
          if (!info) return;
          const node = info.siblings.splice(info.index, 1)[0];
          if (!node) return;
          if (parentType === "stage") {
            this.tree.push(node);
          } else {
            const p = this.findNode(parentId);
            if (p) {
              if (!p.children) p.children = [];
              p.children.push(node);
            }
          }
        }
      },
      isDropTarget(parentType, parentId) {
        if (!this.dropTarget) return false;
        return this.dropTarget.parentType === parentType && this.dropTarget.parentId === parentId;
      },
      createNode(type) {
        const ct = this.getContentType(type);
        const defaults = ct?.defaults ?? {};
        const appearance = defaults["appearance"] ?? "default";
        const node = {
          id: this.generateId(),
          type,
          appearance,
          data: { ...defaults, appearance },
          children: []
        };
        if (type === "column-group") {
          node.children = [
            {
              id: this.generateId(),
              type: "column-line",
              appearance: "default",
              data: { appearance: "default" },
              children: [
                {
                  id: this.generateId(),
                  type: "column",
                  appearance: "full-height",
                  data: { appearance: "full-height", width: "50%" },
                  children: []
                },
                {
                  id: this.generateId(),
                  type: "column",
                  appearance: "full-height",
                  data: { appearance: "full-height", width: "50%" },
                  children: []
                }
              ]
            }
          ];
        }
        if (type === "buttons") {
          node.children = [
            {
              id: this.generateId(),
              type: "button-item",
              appearance: "default",
              data: {
                appearance: "default",
                button_text: "Button",
                link_url: "#",
                button_type: "primary"
              },
              children: []
            }
          ];
        }
        return node;
      },
      addToStage(type) {
        const ct = this.getContentType(type);
        if (!ct) return;
        const rules = ct.parents ?? {};
        if (rules.defaultPolicy === "deny" && !(rules.allow ?? []).includes("stage")) {
          const last = this.tree[this.tree.length - 1];
          if (this.tree.length > 0 && last?.type === "row") {
            this.addNodeToParent(type, last);
          }
          return;
        }
        this.tree.push(this.createNode(type));
      },
      addNodeToParent(type, parent) {
        if (!parent) return;
        if (!parent.children) parent.children = [];
        parent.children.push(this.createNode(type));
      },
      findNode(id, nodes) {
        const ns = nodes ?? this.tree;
        for (const n of ns) {
          if (n.id === id) return n;
          if (n.children) {
            const f = this.findNode(id, n.children);
            if (f) return f;
          }
        }
        return null;
      },
      findParentAndIndex(id, nodes, parent = null) {
        const ns = nodes ?? this.tree;
        for (let i = 0; i < ns.length; i++) {
          const node = ns[i];
          if (!node) continue;
          if (node.id === id) return { parent, index: i, siblings: ns };
          if (node.children) {
            const f = this.findParentAndIndex(id, node.children, node);
            if (f) return f;
          }
        }
        return null;
      },
      deleteNode(id) {
        const info = this.findParentAndIndex(id);
        if (info) {
          info.siblings.splice(info.index, 1);
          if (this.editingNodeId === id) this.editingNodeId = null;
        }
      },
      duplicateNode(id) {
        const info = this.findParentAndIndex(id);
        if (!info) return;
        const source = info.siblings[info.index];
        if (!source) return;
        const clone = JSON.parse(JSON.stringify(source));
        this.reassignIds(clone);
        info.siblings.splice(info.index + 1, 0, clone);
      },
      reassignIds(node) {
        node.id = this.generateId();
        if (node.children) {
          for (const child of node.children) this.reassignIds(child);
        }
      },
      editNode(id) {
        this.editingNodeId = id;
      },
      getEditingNode() {
        return this.editingNodeId ? this.findNode(this.editingNodeId) : null;
      },
      updateNodeData(id, key, value) {
        const n = this.findNode(id);
        if (n) {
          if (!n.data) n.data = {};
          n.data[key] = value;
        }
      },
      closeEditPanel() {
        this.editingNodeId = null;
      },
      closeEditor() {
        this.editingNodeId = null;
        this.editorOpen = false;
        if (this.tree.length > 0) {
          void this.syncMasterFormat();
        } else {
          this.masterHtml = "";
        }
      },
      getNodePreviewStyles(_node) {
        const d = _node.data ?? {};
        const s = [];
        if (d["background_color"]) s.push("background-color:" + String(d["background_color"]));
        if (d["min_height"]) s.push("min-height:" + String(d["min_height"]));
        if (d["text_align"]) s.push("text-align:" + String(d["text_align"]));
        return s.join(";");
      },
      getHeadingClass(tag) {
        const map = {
          h1: "text-3xl font-bold",
          h2: "text-2xl font-bold",
          h3: "text-xl font-semibold",
          h4: "text-lg font-semibold",
          h5: "text-base font-medium",
          h6: "text-sm font-medium"
        };
        return map[tag] ?? "text-2xl font-bold";
      },
      async syncMasterFormat() {
        this.syncing = true;
        try {
          const url = this.renderUrl + (this.renderUrl.includes("?") ? "&" : "?") + "form_key=" + encodeURIComponent(this.formKey);
          const r = await fetch(url, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify(this.tree)
          });
          if (!r.ok) throw new Error("Render " + String(r.status));
          const d = await r.json();
          if (d.html !== void 0) this.masterHtml = d.html;
        } catch (e) {
          console.error("Sync error:", e);
        } finally {
          this.syncing = false;
        }
      },
      async parseHtml(html) {
        try {
          const url = this.parseUrl + (this.parseUrl.includes("?") ? "&" : "?") + "form_key=" + encodeURIComponent(this.formKey);
          const r = await fetch(url, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({ html })
          });
          if (!r.ok) throw new Error("Parse " + String(r.status));
          const d = await r.json();
          if (d.tree) this.tree = d.tree;
        } catch (e) {
          console.error("Parse error:", e);
        }
      }
    };
    return state;
  }
  function registerPageBuilder() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaPageBuilder", () => createPageBuilder());
    });
  }
  registerPageBuilder();
})();
//# sourceMappingURL=nebula-pagebuilder.js.map
