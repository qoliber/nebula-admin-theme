<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Model;

use InvalidArgumentException;
use Magento\Cms\Helper\Wysiwyg\Images;
use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backend media browser facade for the Nebula media picker.
 *
 * Wraps Magento's WYSIWYG image storage to list directories/files, build the
 * folder tree and breadcrumbs, and handle upload / create-directory / delete
 * for the Nebula admin media picker UI. All client-supplied path identifiers
 * are resolved relative to the configured media storage root.
 */
class MediaPicker
{
    /** @var string Sentinel id representing the media storage root node. */
    public const ROOT_ID = '__root__';

    /**
     * @param \Magento\Cms\Helper\Wysiwyg\Images $imagesHelper
     * @param \Magento\Cms\Model\Wysiwyg\Images\Storage $storage
     */
    public function __construct(
        private readonly Images $imagesHelper,
        private readonly Storage $storage
    ) {
    }

    /**
     * Build the picker payload (current folder, breadcrumbs, sub-folders, files).
     *
     * @param string|null $pathId
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getContents(?string $pathId = null): array
    {
        $path = $this->resolvePath($pathId);

        return [
            'currentPath' => $this->buildDirectoryData($path),
            'breadcrumbs' => $this->buildBreadcrumbs($path),
            'directories' => $this->buildDirectoryEntries($path),
            'files' => $this->buildFileEntries($path),
        ];
    }

    /**
     * Build the full directory tree starting at the storage root.
     *
     * @return array<int, array<string, mixed>>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTree(): array
    {
        return [$this->buildTree($this->imagesHelper->getStorageRoot())];
    }

    /**
     * Upload an image into the given folder and return the new file plus listing.
     *
     * @param string|null $pathId
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function upload(?string $pathId = null): array
    {
        $path = $this->resolvePath($pathId);
        $uploaded = $this->storage->uploadFile($path, 'image');
        $fileName = isset($uploaded['file']) ? (string) $uploaded['file'] : '';

        if ($fileName === '') {
            throw new LocalizedException(__('The uploaded file name is missing.'));
        }

        return [
            'file' => $this->findFileByName($path, $fileName),
            'contents' => $this->getContents($pathId),
        ];
    }

    /**
     * Create a sub-directory under the given folder and return the refreshed tree.
     *
     * @param string|null $pathId
     * @param string $name
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createDirectory(?string $pathId, string $name): array
    {
        $path = $this->resolvePath($pathId);
        $result = $this->storage->createDirectory($name, $path);
        $directoryPath = isset($result['path']) ? (string) $result['path'] : '';

        if ($directoryPath === '') {
            throw new LocalizedException(__('The new directory path is missing.'));
        }

        return [
            'directory' => $this->buildDirectoryData($directoryPath),
            'contents' => $this->getContents($pathId),
            'tree' => $this->getTree(),
        ];
    }

    /**
     * Delete a media file identified by its (base64) id and return the listing.
     *
     * @param string $fileId
     * @param string|null $pathId
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteFile(string $fileId, ?string $pathId = null): array
    {
        $absolutePath = $this->resolveFilePath($fileId);
        $this->storage->deleteFile($absolutePath);

        return [
            'contents' => $this->getContents($pathId),
        ];
    }

    /**
     * Recursively build a directory tree node and its children.
     *
     * @param string $path
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function buildTree(string $path): array
    {
        $node = $this->buildDirectoryData($path);
        $children = [];

        foreach ($this->storage->getDirsCollection($path) as $directory) {
            $directoryPath = (string) $directory->getFilename();
            $children[] = $this->buildTree($directoryPath);
        }

        $node['children'] = $children;

        return $node;
    }

    /**
     * Build the immediate sub-directory entries for a folder.
     *
     * @param string $path
     * @return array<int, array<string, mixed>>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function buildDirectoryEntries(string $path): array
    {
        $directories = [];

        foreach ($this->storage->getDirsCollection($path) as $directory) {
            $directories[] = $this->buildDirectoryData((string) $directory->getFilename());
        }

        return $directories;
    }

    /**
     * Build the image-file entries for a folder.
     *
     * @param string $path
     * @return array<int, array<string, mixed>>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function buildFileEntries(string $path): array
    {
        $files = [];

        foreach ($this->storage->getFilesCollection($path, 'image') as $file) {
            $files[] = $this->buildFileData($file);
        }

        return $files;
    }

    /**
     * Build the metadata payload describing a single directory.
     *
     * @param string $path
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function buildDirectoryData(string $path): array
    {
        return [
            'id' => $this->toPathId($path),
            'name' => $path === $this->imagesHelper->getStorageRoot() ? 'Media' : basename($path),
            'relativePath' => $this->toRelativePath($path),
        ];
    }

    /**
     * Build the metadata payload describing a single media file.
     *
     * @param \Magento\Framework\DataObject $file
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function buildFileData(DataObject $file): array
    {
        $absolutePath = (string) $file->getFilename();
        $relativePath = $this->toRelativePath($absolutePath);
        $fileUrl = rtrim($this->imagesHelper->getBaseUrl(), '/') . '/' . ltrim($relativePath, '/');

        return [
            'id' => base64_encode($relativePath),
            'name' => (string) $file->getName(),
            'shortName' => (string) $file->getShortName(),
            'relativePath' => $relativePath,
            'url' => $fileUrl,
            'thumbUrl' => (string) $file->getThumbUrl(),
            'width' => (int) $file->getWidth(),
            'height' => (int) $file->getHeight(),
            'size' => (int) $file->getSize(),
            'mimeType' => (string) $file->getMimeType(),
        ];
    }

    /**
     * Build the breadcrumb trail from the storage root down to the given folder.
     *
     * @param string $path
     * @return array<int, array<string, mixed>>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function buildBreadcrumbs(string $path): array
    {
        $breadcrumbs = [
            [
                'id' => self::ROOT_ID,
                'name' => 'Media',
                'relativePath' => '/',
            ],
        ];

        $relativePath = trim($this->toRelativePath($path), '/');
        if ($relativePath === '') {
            return $breadcrumbs;
        }

        $currentPath = $this->imagesHelper->getStorageRoot();
        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '') {
                continue;
            }

            $currentPath = rtrim($currentPath, '/') . '/' . $segment;
            $breadcrumbs[] = [
                'id' => $this->toPathId($currentPath),
                'name' => $segment,
                'relativePath' => $this->toRelativePath($currentPath),
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Resolve a client path id to an absolute filesystem path under the root.
     *
     * @param string|null $pathId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function resolvePath(?string $pathId): string
    {
        if ($pathId === null || $pathId === '' || $pathId === self::ROOT_ID) {
            return $this->imagesHelper->getStorageRoot();
        }

        try {
            return $this->imagesHelper->convertIdToPath($pathId);
        } catch (InvalidArgumentException $exception) {
            throw new LocalizedException(__('The requested media path is invalid.'), $exception);
        }
    }

    /**
     * Convert an absolute path to its storage path id (root maps to ROOT_ID).
     *
     * @param string $path
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function toPathId(string $path): string
    {
        $pathId = $this->imagesHelper->convertPathToId($path);

        return $pathId === '' ? self::ROOT_ID : $pathId;
    }

    /**
     * Convert an absolute path to a media-root-relative path (leading slash).
     *
     * @param string $path
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function toRelativePath(string $path): string
    {
        $root = rtrim($this->imagesHelper->getStorageRoot(), '/');
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = str_replace('\\', '/', $root);
        $relativePath = ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');

        return $relativePath === '' ? '/' : '/' . $relativePath;
    }

    /**
     * Locate an uploaded file by name within a folder and return its metadata.
     *
     * @param string $path
     * @param string $fileName
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function findFileByName(string $path, string $fileName): array
    {
        foreach ($this->storage->getFilesCollection($path, 'image') as $file) {
            if ((string) $file->getName() === $fileName) {
                return $this->buildFileData($file);
            }
        }

        throw new LocalizedException(__('The uploaded file could not be loaded.'));
    }

    /**
     * Resolve a client-supplied (base64) file id to a safe absolute path.
     *
     * Rejects any traversal segment and proves the resolved path stays inside
     * the configured media root, so a crafted id can never escape it.
     *
     * @param string $fileId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function resolveFilePath(string $fileId): string
    {
        $decoded = base64_decode($fileId, true);
        if ($decoded === false || $decoded === '') {
            throw new LocalizedException(__('The requested file is invalid.'));
        }

        $root = rtrim(str_replace('\\', '/', $this->imagesHelper->getStorageRoot()), '/');
        $relativePath = ltrim(str_replace('\\', '/', $decoded), '/');

        // Reject any traversal segment so a crafted (base64) file id can never
        // escape the configured media root.
        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '..') {
                throw new LocalizedException(__('The requested file is invalid.'));
            }
        }

        $absolutePath = $root . '/' . $relativePath;

        // Defence in depth: prove the resolved path stays inside the media root.
        if (strpos($absolutePath, $root . '/') !== 0) {
            throw new LocalizedException(__('The requested file is invalid.'));
        }

        return $absolutePath;
    }
}
