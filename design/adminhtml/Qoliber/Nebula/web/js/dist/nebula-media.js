"use strict";
(() => {
  // ts/components/media/picker.ts
  function flattenTree(nodes, depth = 0) {
    const flattened = [];
    nodes.forEach((node) => {
      flattened.push({
        ...node,
        depth
      });
      if (Array.isArray(node.children) && node.children.length > 0) {
        flattened.push(...flattenTree(node.children, depth + 1));
      }
    });
    return flattened;
  }
  function buildDisplayAlt(file) {
    return file.name.replace(/\.[^.]+$/, "").replace(/[-_]+/g, " ").trim();
  }
  async function parseResponse(response) {
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.data) {
      throw new Error(payload.message ?? "Media request failed.");
    }
    return payload.data;
  }
  function registerMediaPicker() {
    const install = () => {
      window.Alpine?.data("nebulaMediaPicker", (config) => ({
        isOpen: false,
        loading: false,
        errorMessage: "",
        tree: [],
        currentPath: { id: "__root__", name: "Media", relativePath: "/" },
        breadcrumbs: [],
        directories: [],
        files: [],
        selectedFile: null,
        selectedAlt: "",
        selectedWidth: "",
        selectedHeight: "",
        selectedAlignment: "left",
        createDirectoryName: "",
        treeOpen: true,
        deleteDialogOpen: false,
        deleteCandidate: null,
        resolver: null,
        scrollLockCount: 0,
        get flatTree() {
          return flattenTree(this.tree);
        },
        init() {
          window.NebulaMedia = {
            open: async (options) => this.open(options)
          };
        },
        async open(options) {
          this.isOpen = true;
          this.errorMessage = "";
          this.lockPageScroll();
          const initialPathId = this.currentPath.id === "__root__" ? config.defaultPathId : this.currentPath.id;
          if (options?.resetSelection ?? true) {
            this.clearSelection();
          }
          await Promise.all([
            this.tree.length === 0 ? this.reloadTree() : Promise.resolve(),
            this.reloadContents(initialPathId)
          ]);
          if (options?.selection) {
            this.selectVirtualFile(options.selection);
          }
          return await new Promise((resolve) => {
            this.resolver = resolve;
          });
        },
        close() {
          this.isOpen = false;
          this.closeDeleteDialog();
          this.unlockPageScroll();
          const resolver = this.resolver;
          this.resolver = null;
          if (resolver) {
            resolver(null);
          }
        },
        async reloadTree() {
          try {
            const url = new URL(config.treeUrl, window.location.origin);
            const response = await fetch(url.toString(), {
              method: "GET",
              credentials: "same-origin",
              headers: {
                "X-Requested-With": "XMLHttpRequest"
              }
            });
            const data = await parseResponse(response);
            this.tree = data.tree;
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to load folders.";
          }
        },
        async reloadContents(pathId) {
          this.loading = true;
          this.errorMessage = "";
          try {
            const url = new URL(config.contentsUrl, window.location.origin);
            if (pathId) {
              url.searchParams.set("path", pathId);
            }
            const response = await fetch(url.toString(), {
              method: "GET",
              credentials: "same-origin",
              headers: {
                "X-Requested-With": "XMLHttpRequest"
              }
            });
            const data = await parseResponse(response);
            this.applyContents(data);
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to load media contents.";
          } finally {
            this.loading = false;
          }
        },
        async openPath(pathId) {
          await this.reloadContents(pathId);
        },
        applyContents(data) {
          this.currentPath = data.currentPath;
          this.breadcrumbs = data.breadcrumbs;
          this.directories = data.directories;
          this.files = data.files;
          if (!this.selectedFile || !this.files.some((file) => file.id === this.selectedFile?.id)) {
            this.selectedFile = null;
            this.selectedAlt = "";
            this.selectedWidth = "";
            this.selectedHeight = "";
          }
        },
        clearSelection() {
          this.selectedFile = null;
          this.selectedAlt = "";
          this.selectedWidth = "";
          this.selectedHeight = "";
          this.selectedAlignment = "left";
        },
        openDeleteDialog(file) {
          this.deleteCandidate = file;
          this.deleteDialogOpen = true;
        },
        closeDeleteDialog() {
          this.deleteDialogOpen = false;
          this.deleteCandidate = null;
        },
        lockPageScroll() {
          if (this.scrollLockCount > 0) {
            this.scrollLockCount += 1;
            return;
          }
          document.documentElement.style.overflow = "hidden";
          document.body.style.overflow = "hidden";
          this.scrollLockCount = 1;
        },
        unlockPageScroll() {
          if (this.scrollLockCount === 0) {
            return;
          }
          this.scrollLockCount -= 1;
          if (this.scrollLockCount > 0) {
            return;
          }
          document.documentElement.style.overflow = "";
          document.body.style.overflow = "";
        },
        selectFile(file) {
          this.selectedFile = file;
          this.selectedAlt = buildDisplayAlt(file);
          this.selectedWidth = String(file.width || "");
          this.selectedHeight = String(file.height || "");
          this.selectedAlignment = "left";
        },
        selectVirtualFile(selection) {
          this.selectedFile = {
            id: selection.src,
            name: selection.src.split("/").pop() ?? selection.src,
            shortName: selection.src.split("/").pop() ?? selection.src,
            relativePath: selection.src,
            url: selection.src,
            thumbUrl: selection.src,
            width: selection.width ?? 0,
            height: selection.height ?? 0,
            size: 0,
            mimeType: "image"
          };
          this.selectedAlt = selection.alt;
          this.selectedWidth = selection.width === null ? "" : String(selection.width);
          this.selectedHeight = selection.height === null ? "" : String(selection.height);
          this.selectedAlignment = selection.alignment;
        },
        async handleUpload(event) {
          const input = event.target;
          const file = input.files?.item(0);
          input.value = "";
          if (!file) {
            return;
          }
          if (this.currentPath.id === "__root__") {
            this.errorMessage = "Select an allowed folder such as /wysiwyg before uploading.";
            return;
          }
          this.loading = true;
          this.errorMessage = "";
          try {
            const formData = new FormData();
            formData.append("image", file);
            formData.append("form_key", config.formKey);
            formData.append("path", this.currentPath.id);
            const response = await fetch(config.uploadUrl, {
              method: "POST",
              body: formData,
              credentials: "same-origin",
              headers: {
                "X-Requested-With": "XMLHttpRequest"
              }
            });
            const data = await parseResponse(response);
            this.applyContents(data.contents);
            this.selectFile(data.file);
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to upload the image.";
          } finally {
            this.loading = false;
          }
        },
        async createDirectory() {
          const name = this.createDirectoryName.trim();
          if (name === "") {
            return;
          }
          if (this.currentPath.id === "__root__") {
            this.errorMessage = "Select an allowed folder such as /wysiwyg before creating a subfolder.";
            return;
          }
          this.loading = true;
          this.errorMessage = "";
          try {
            const formData = new FormData();
            formData.append("form_key", config.formKey);
            formData.append("path", this.currentPath.id);
            formData.append("name", name);
            const response = await fetch(config.createDirectoryUrl, {
              method: "POST",
              body: formData,
              credentials: "same-origin",
              headers: {
                "X-Requested-With": "XMLHttpRequest"
              }
            });
            const data = await parseResponse(response);
            this.tree = data.tree;
            this.applyContents(data.contents);
            this.createDirectoryName = "";
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to create the folder.";
          } finally {
            this.loading = false;
          }
        },
        async confirmDeleteFile() {
          const file = this.deleteCandidate;
          if (!file) {
            return;
          }
          this.loading = true;
          this.errorMessage = "";
          try {
            const formData = new FormData();
            formData.append("form_key", config.formKey);
            formData.append("path", this.currentPath.id);
            formData.append("file", file.id);
            const response = await fetch(config.deleteFileUrl, {
              method: "POST",
              body: formData,
              credentials: "same-origin",
              headers: {
                "X-Requested-With": "XMLHttpRequest"
              }
            });
            const data = await parseResponse(response);
            if (this.selectedFile?.id === file.id) {
              this.clearSelection();
            }
            this.applyContents(data.contents);
            this.closeDeleteDialog();
          } catch (error) {
            this.errorMessage = error instanceof Error ? error.message : "Unable to delete the image.";
          } finally {
            this.loading = false;
          }
        },
        insertSelected() {
          if (!this.selectedFile || !this.resolver) {
            return;
          }
          const resolver = this.resolver;
          this.resolver = null;
          this.isOpen = false;
          this.closeDeleteDialog();
          this.unlockPageScroll();
          resolver({
            src: this.selectedFile.url,
            alt: this.selectedAlt.trim(),
            width: this.selectedWidth === "" ? null : Number(this.selectedWidth),
            height: this.selectedHeight === "" ? null : Number(this.selectedHeight),
            alignment: this.selectedAlignment
          });
        }
      }));
    };
    if (window.Alpine) {
      install();
    }
    document.addEventListener("alpine:init", install);
  }

  // ts/pages/media.ts
  registerMediaPicker();
})();
//# sourceMappingURL=nebula-media.js.map
