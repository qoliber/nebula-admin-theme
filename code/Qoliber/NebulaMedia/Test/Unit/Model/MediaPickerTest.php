<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Test\Unit\Model;

use Magento\Cms\Helper\Wysiwyg\Images;
use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaMedia\Model\MediaPicker;

/**
 * Pins the RC1 path-traversal guard on MediaPicker::deleteFile().
 *
 * The file id is a client-supplied base64 string. A crafted value such as
 * base64("../../app/etc/env.php") must be rejected before Storage::deleteFile()
 * is ever called, and the resolved path must stay under the media storage root.
 */
class MediaPickerTest extends TestCase
{
    private const ROOT = '/var/www/pub/media/wysiwyg';

    /** @var \Magento\Cms\Helper\Wysiwyg\Images&\PHPUnit\Framework\MockObject\MockObject */
    private $imagesHelper;

    /** @var \Magento\Cms\Model\Wysiwyg\Images\Storage&\PHPUnit\Framework\MockObject\MockObject */
    private $storage;

    private MediaPicker $picker;

    protected function setUp(): void
    {
        $this->imagesHelper = $this->createMock(Images::class);
        $this->imagesHelper->method('getStorageRoot')->willReturn(self::ROOT);

        $this->storage = $this->createMock(Storage::class);

        $this->picker = new MediaPicker($this->imagesHelper, $this->storage);
    }

    /**
     * Traversal payloads — these contain a `..` segment and MUST be rejected
     * outright (the resolved path could otherwise escape the media root).
     *
     * @return array<string, array{0: string}>
     */
    public static function traversalFileIdProvider(): array
    {
        return [
            'parent traversal'    => ['../../app/etc/env.php'],
            'deep traversal'      => ['foo/../../../etc/passwd'],
            'single dotdot'       => ['..'],
            'backslash traversal' => ['..\\..\\app\\etc\\env.php'],
        ];
    }

    /**
     * @dataProvider traversalFileIdProvider
     */
    public function testDeleteRejectsTraversalAndNeverDeletes(string $rawPath): void
    {
        // The controller passes a base64-encoded file id.
        $fileId = base64_encode($rawPath);

        // The sink must never be reached for a traversal id.
        $this->storage->expects($this->never())->method('deleteFile');

        $this->expectException(LocalizedException::class);
        $this->picker->deleteFile($fileId);
    }

    public function testLeadingSlashIsNeutralisedNotEscaped(): void
    {
        // A leading-slash "absolute" path has no `..` segment, so it is not a
        // traversal — the leading slash is stripped and it resolves *under* the
        // media root (harmless), e.g. /etc/passwd -> <root>/etc/passwd. The
        // guarantee under test is that it never resolves OUTSIDE the root.
        $fileId = base64_encode('/etc/passwd');

        $this->imagesHelper->method('convertPathToId')->willReturn('root-id');
        $this->imagesHelper->method('getBaseUrl')->willReturn('http://example.test/media/wysiwyg');
        $this->storage->method('getDirsCollection')->willReturn([]);
        $this->storage->method('getFilesCollection')->willReturn([]);

        $this->storage->expects($this->once())
            ->method('deleteFile')
            ->with($this->callback(static fn (string $p): bool => str_starts_with($p, self::ROOT . '/')));

        $this->picker->deleteFile($fileId, MediaPicker::ROOT_ID);
    }

    public function testDeleteRejectsNonBase64Garbage(): void
    {
        $this->storage->expects($this->never())->method('deleteFile');

        $this->expectException(LocalizedException::class);
        // strict base64_decode fails → invalid.
        $this->picker->deleteFile('!!!not base64!!!');
    }

    public function testDeleteRejectsEmptyId(): void
    {
        $this->storage->expects($this->never())->method('deleteFile');

        $this->expectException(LocalizedException::class);
        $this->picker->deleteFile('');
    }

    public function testDeleteOfLegitimateFileResolvesUnderRootAndDeletes(): void
    {
        $fileId = base64_encode('subdir/photo.jpg');
        $expected = self::ROOT . '/subdir/photo.jpg';

        $this->storage->expects($this->once())
            ->method('deleteFile')
            ->with($expected);

        // deleteFile() also rebuilds the listing via getContents(); stub the
        // collections it walks so the happy path completes without a real DB.
        $this->imagesHelper->method('convertPathToId')->willReturn('root-id');
        $this->imagesHelper->method('getBaseUrl')->willReturn('http://example.test/media/wysiwyg');
        $this->storage->method('getDirsCollection')->willReturn([]);
        $this->storage->method('getFilesCollection')->willReturn([]);

        $result = $this->picker->deleteFile($fileId, MediaPicker::ROOT_ID);

        $this->assertArrayHasKey('contents', $result);
    }
}
