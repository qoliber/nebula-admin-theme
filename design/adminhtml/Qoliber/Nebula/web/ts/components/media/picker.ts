interface MediaPickerConfig {
    treeUrl: string;
    contentsUrl: string;
    uploadUrl: string;
    createDirectoryUrl: string;
    deleteFileUrl: string;
    formKey: string;
    defaultPathId: string;
}

interface MediaDirectory {
    id: string;
    name: string;
    relativePath: string;
    children?: MediaDirectory[];
}

interface MediaDirectoryFlat extends MediaDirectory {
    depth: number;
}

interface MediaFile {
    id: string;
    name: string;
    shortName: string;
    relativePath: string;
    url: string;
    thumbUrl: string;
    width: number;
    height: number;
    size: number;
    mimeType: string;
}

interface MediaBreadcrumb {
    id: string;
    name: string;
    relativePath: string;
}

interface MediaContentsResponse {
    currentPath: MediaDirectory;
    breadcrumbs: MediaBreadcrumb[];
    directories: MediaDirectory[];
    files: MediaFile[];
}

interface MediaPickerResponse<T> {
    success: boolean;
    message?: string;
    data?: T;
}

interface MediaSelection {
    src: string;
    alt: string;
    width: number | null;
    height: number | null;
    alignment: 'left' | 'center' | 'right';
}

interface MediaOpenOptions {
    resetSelection?: boolean;
    selection?: MediaSelection | null;
}

interface MediaOpenHandle {
    open(options?: MediaOpenOptions): Promise<MediaSelection | null>;
}

declare global {
    interface Window {
        NebulaMedia?: MediaOpenHandle;
    }
}

function flattenTree(nodes: MediaDirectory[], depth = 0): MediaDirectoryFlat[] {
    const flattened: MediaDirectoryFlat[] = [];

    nodes.forEach((node) => {
        flattened.push({
            ...node,
            depth,
        });

        if (Array.isArray(node.children) && node.children.length > 0) {
            flattened.push(...flattenTree(node.children, depth + 1));
        }
    });

    return flattened;
}

function buildDisplayAlt(file: MediaFile): string {
    return file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
}

async function parseResponse<T>(response: Response): Promise<T> {
    const payload = await response.json() as MediaPickerResponse<T>;

    if (!response.ok || !payload.success || !payload.data) {
        throw new Error(payload.message ?? 'Media request failed.');
    }

    return payload.data;
}

export function registerMediaPicker(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaMediaPicker', (config: MediaPickerConfig) => ({
            isOpen: false,
            loading: false,
            errorMessage: '',
            tree: [] as MediaDirectory[],
            currentPath: { id: '__root__', name: 'Media', relativePath: '/' } as MediaDirectory,
            breadcrumbs: [] as MediaBreadcrumb[],
            directories: [] as MediaDirectory[],
            files: [] as MediaFile[],
            selectedFile: null as MediaFile | null,
            selectedAlt: '',
            selectedWidth: '',
            selectedHeight: '',
            selectedAlignment: 'left' as 'left' | 'center' | 'right',
            createDirectoryName: '',
            treeOpen: true,
            deleteDialogOpen: false,
            deleteCandidate: null as MediaFile | null,
            resolver: null as ((selection: MediaSelection | null) => void) | null,
            scrollLockCount: 0,

            get flatTree(): MediaDirectoryFlat[] {
                return flattenTree(this.tree);
            },

            init(): void {
                window.NebulaMedia = {
                    open: async (options?: MediaOpenOptions): Promise<MediaSelection | null> => this.open(options),
                };
            },

            async open(options?: MediaOpenOptions): Promise<MediaSelection | null> {
                this.isOpen = true;
                this.errorMessage = '';
                this.lockPageScroll();
                const initialPathId = this.currentPath.id === '__root__' ? config.defaultPathId : this.currentPath.id;

                if (options?.resetSelection ?? true) {
                    this.clearSelection();
                }

                await Promise.all([
                    this.tree.length === 0 ? this.reloadTree() : Promise.resolve(),
                    this.reloadContents(initialPathId),
                ]);

                if (options?.selection) {
                    this.selectVirtualFile(options.selection);
                }

                return await new Promise<MediaSelection | null>((resolve) => {
                    this.resolver = resolve;
                });
            },

            close(): void {
                this.isOpen = false;
                this.closeDeleteDialog();
                this.unlockPageScroll();
                const resolver = this.resolver;
                this.resolver = null;
                if (resolver) {
                    resolver(null);
                }
            },

            async reloadTree(): Promise<void> {
                try {
                    const url = new URL(config.treeUrl, window.location.origin);
                    const response = await fetch(url.toString(), {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await parseResponse<{ tree: MediaDirectory[] }>(response);
                    this.tree = data.tree;
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to load folders.';
                }
            },

            async reloadContents(pathId?: string): Promise<void> {
                this.loading = true;
                this.errorMessage = '';

                try {
                    const url = new URL(config.contentsUrl, window.location.origin);
                    if (pathId) {
                        url.searchParams.set('path', pathId);
                    }

                    const response = await fetch(url.toString(), {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await parseResponse<MediaContentsResponse>(response);
                    this.applyContents(data);
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to load media contents.';
                } finally {
                    this.loading = false;
                }
            },

            async openPath(pathId: string): Promise<void> {
                await this.reloadContents(pathId);
            },

            applyContents(data: MediaContentsResponse): void {
                this.currentPath = data.currentPath;
                this.breadcrumbs = data.breadcrumbs;
                this.directories = data.directories;
                this.files = data.files;

                if (!this.selectedFile || !this.files.some((file) => file.id === this.selectedFile?.id)) {
                    this.selectedFile = null;
                    this.selectedAlt = '';
                    this.selectedWidth = '';
                    this.selectedHeight = '';
                }
            },

            clearSelection(): void {
                this.selectedFile = null;
                this.selectedAlt = '';
                this.selectedWidth = '';
                this.selectedHeight = '';
                this.selectedAlignment = 'left';
            },

            openDeleteDialog(file: MediaFile): void {
                this.deleteCandidate = file;
                this.deleteDialogOpen = true;
            },

            closeDeleteDialog(): void {
                this.deleteDialogOpen = false;
                this.deleteCandidate = null;
            },

            lockPageScroll(): void {
                if (this.scrollLockCount > 0) {
                    this.scrollLockCount += 1;
                    return;
                }

                document.documentElement.style.overflow = 'hidden';
                document.body.style.overflow = 'hidden';
                this.scrollLockCount = 1;
            },

            unlockPageScroll(): void {
                if (this.scrollLockCount === 0) {
                    return;
                }

                this.scrollLockCount -= 1;
                if (this.scrollLockCount > 0) {
                    return;
                }

                document.documentElement.style.overflow = '';
                document.body.style.overflow = '';
            },

            selectFile(file: MediaFile): void {
                this.selectedFile = file;
                this.selectedAlt = buildDisplayAlt(file);
                this.selectedWidth = String(file.width || '');
                this.selectedHeight = String(file.height || '');
                this.selectedAlignment = 'left';
            },

            selectVirtualFile(selection: MediaSelection): void {
                this.selectedFile = {
                    id: selection.src,
                    name: selection.src.split('/').pop() ?? selection.src,
                    shortName: selection.src.split('/').pop() ?? selection.src,
                    relativePath: selection.src,
                    url: selection.src,
                    thumbUrl: selection.src,
                    width: selection.width ?? 0,
                    height: selection.height ?? 0,
                    size: 0,
                    mimeType: 'image',
                };
                this.selectedAlt = selection.alt;
                this.selectedWidth = selection.width === null ? '' : String(selection.width);
                this.selectedHeight = selection.height === null ? '' : String(selection.height);
                this.selectedAlignment = selection.alignment;
            },

            async handleUpload(event: Event): Promise<void> {
                const input = event.target as HTMLInputElement;
                const file = input.files?.item(0);
                input.value = '';

                if (!file) {
                    return;
                }

                 if (this.currentPath.id === '__root__') {
                    this.errorMessage = 'Select an allowed folder such as /wysiwyg before uploading.';
                    return;
                }

                this.loading = true;
                this.errorMessage = '';

                try {
                    const formData = new FormData();
                    formData.append('image', file);
                    formData.append('form_key', config.formKey);
                    formData.append('path', this.currentPath.id);

                    const response = await fetch(config.uploadUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await parseResponse<{ file: MediaFile; contents: MediaContentsResponse }>(response);
                    this.applyContents(data.contents);
                    this.selectFile(data.file);
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to upload the image.';
                } finally {
                    this.loading = false;
                }
            },

            async createDirectory(): Promise<void> {
                const name = this.createDirectoryName.trim();
                if (name === '') {
                    return;
                }

                if (this.currentPath.id === '__root__') {
                    this.errorMessage = 'Select an allowed folder such as /wysiwyg before creating a subfolder.';
                    return;
                }

                this.loading = true;
                this.errorMessage = '';

                try {
                    const formData = new FormData();
                    formData.append('form_key', config.formKey);
                    formData.append('path', this.currentPath.id);
                    formData.append('name', name);

                    const response = await fetch(config.createDirectoryUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await parseResponse<{ contents: MediaContentsResponse; tree: MediaDirectory[] }>(response);
                    this.tree = data.tree;
                    this.applyContents(data.contents);
                    this.createDirectoryName = '';
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to create the folder.';
                } finally {
                    this.loading = false;
                }
            },

            async confirmDeleteFile(): Promise<void> {
                const file = this.deleteCandidate;

                if (!file) {
                    return;
                }

                this.loading = true;
                this.errorMessage = '';

                try {
                    const formData = new FormData();
                    formData.append('form_key', config.formKey);
                    formData.append('path', this.currentPath.id);
                    formData.append('file', file.id);

                    const response = await fetch(config.deleteFileUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await parseResponse<{ contents: MediaContentsResponse }>(response);
                    if (this.selectedFile?.id === file.id) {
                        this.clearSelection();
                    }
                    this.applyContents(data.contents);
                    this.closeDeleteDialog();
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to delete the image.';
                } finally {
                    this.loading = false;
                }
            },

            insertSelected(): void {
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
                    width: this.selectedWidth === '' ? null : Number(this.selectedWidth),
                    height: this.selectedHeight === '' ? null : Number(this.selectedHeight),
                    alignment: this.selectedAlignment,
                });
            },
        }));
    };

    if (window.Alpine) {
        install();
    }

    document.addEventListener('alpine:init', install);
}
