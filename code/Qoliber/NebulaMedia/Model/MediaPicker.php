<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Model;

use InvalidArgumentException;
use Magento\Cms\Helper\Wysiwyg\Images;
use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class MediaPicker
{
    public const ROOT_ID = '__root__';

    public function __construct(
        private readonly Images $imagesHelper,
        private readonly Storage $storage
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    public function getTree(): array
    {
        return [$this->buildTree($this->imagesHelper->getStorageRoot())];
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
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
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
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
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
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
     * @throws LocalizedException
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
     * @throws LocalizedException
     */
    private function toPathId(string $path): string
    {
        $pathId = $this->imagesHelper->convertPathToId($path);

        return $pathId === '' ? self::ROOT_ID : $pathId;
    }

    /**
     * @throws LocalizedException
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
     * @return array<string, mixed>
     * @throws LocalizedException
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
     * @throws LocalizedException
     */
    private function resolveFilePath(string $fileId): string
    {
        $decoded = base64_decode($fileId, true);
        if ($decoded === false || $decoded === '') {
            throw new LocalizedException(__('The requested file is invalid.'));
        }

        $root = rtrim(str_replace('\\', '/', $this->imagesHelper->getStorageRoot()), '/');
        $relativePath = ltrim(str_replace('\\', '/', $decoded), '/');

        return $root . '/' . $relativePath;
    }
}
