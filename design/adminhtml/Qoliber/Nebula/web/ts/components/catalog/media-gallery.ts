/**
 * `nebulaMediaGallery` Alpine component — drag-drop image gallery with
 * role assignment (base/small/thumb/swatch) and server upload. Extracted
 * from the inline `<script>` in
 * `NebulaCatalog/view/adminhtml/templates/eav/snippet/media_gallery.phtml`.
 */

interface GalleryImage {
    value_id: string | null;
    file: string;
    url: string;
    label: string;
    position: number;
    disabled: boolean;
    removed: boolean;
    media_type: string;
    new: boolean;
}

interface MediaGalleryConfig {
    images?: GalleryImage[];
    baseImage?: string;
    smallImage?: string;
    thumbnailImage?: string;
    swatchImage?: string;
    uploadUrl?: string;
    formKey?: string;
}

interface UploadResult {
    error?: boolean;
    message?: string;
    file?: string;
    url?: string;
}

export function registerMediaGallery(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaMediaGallery', (config: MediaGalleryConfig = {}) => ({
            images: (config.images ?? []).slice(),
            baseImage: config.baseImage ?? '',
            smallImage: config.smallImage ?? '',
            thumbnailImage: config.thumbnailImage ?? '',
            swatchImage: config.swatchImage ?? '',
            uploadUrl: config.uploadUrl ?? '',
            formKey: config.formKey ?? '',
            uploadDragging: false,
            uploading: false,
            dragIndex: null as number | null,
            dragOverIndex: null as number | null,

            get visibleImages(): GalleryImage[] {
                return this.images.filter((i) => !i.removed);
            },

            get removedImages(): GalleryImage[] {
                return this.images.filter((i) => i.removed && i.value_id);
            },

            getRealIndex(img: GalleryImage): number {
                return this.images.indexOf(img);
            },

            startDrag(index: number, event: DragEvent): void {
                this.dragIndex = index;
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(index));
                }
            },

            endDrag(): void {
                this.dragIndex = null;
                this.dragOverIndex = null;
            },

            dragOver(index: number): void {
                if (this.dragIndex === null || this.dragIndex === index) return;
                this.dragOverIndex = index;
            },

            drop(index: number): void {
                if (this.dragIndex === null || this.dragIndex === index) {
                    this.dragIndex = null;
                    this.dragOverIndex = null;
                    return;
                }
                const item = this.images.splice(this.dragIndex, 1)[0];
                if (item) {
                    this.images.splice(index, 0, item);
                }
                this.images.forEach((img, i) => { img.position = i + 1; });
                this.dragIndex = null;
                this.dragOverIndex = null;
            },

            handleDrop(event: DragEvent): void {
                this.uploadDragging = false;
                if (event.dataTransfer?.files.length) {
                    void this.uploadFiles(event.dataTransfer.files);
                }
            },

            handleFiles(event: Event): void {
                const input = event.target as HTMLInputElement;
                if (input.files) {
                    void this.uploadFiles(input.files);
                }
                input.value = '';
            },

            async uploadFiles(files: FileList): Promise<void> {
                this.uploading = true;
                for (const file of Array.from(files)) {
                    await this.uploadFile(file);
                }
                this.uploading = false;
            },

            async uploadFile(file: File): Promise<void> {
                const formData = new FormData();
                formData.append('image', file);
                formData.append('form_key', this.formKey);

                try {
                    const resp = await fetch(this.uploadUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!resp.ok) {
                        throw new Error('HTTP ' + String(resp.status));
                    }
                    const result = await resp.json() as UploadResult;
                    if (!result.error && result.file && result.url) {
                        const newImg: GalleryImage = {
                            value_id: null,
                            file: result.file,
                            url: result.url,
                            label: '',
                            position: this.images.length + 1,
                            disabled: false,
                            removed: false,
                            media_type: 'image',
                            new: true,
                        };
                        this.images.push(newImg);
                        if (this.visibleImages.length === 1) {
                            this.baseImage = result.file;
                            this.smallImage = result.file;
                            this.thumbnailImage = result.file;
                            this.swatchImage = result.file;
                        }
                    } else {
                        window.nebulaToast?.('error', 'Upload error: ' + (result.message ?? 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Upload failed:', e);
                    window.nebulaToast?.('error', 'Upload failed. Check console for details.');
                }
            },

            removeImage(index: number): void {
                const img = this.images[index];
                if (!img) return;
                img.removed = true;
                const file = img.file;
                if (this.baseImage === file) this.baseImage = '';
                if (this.smallImage === file) this.smallImage = '';
                if (this.thumbnailImage === file) this.thumbnailImage = '';
                if (this.swatchImage === file) this.swatchImage = '';
            },
        }));
    };

    document.addEventListener('alpine:init', install);
}
