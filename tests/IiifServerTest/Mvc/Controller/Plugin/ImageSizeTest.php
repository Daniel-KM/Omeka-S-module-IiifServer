<?php declare(strict_types=1);

namespace IiifServerTest\Mvc\Controller\Plugin;

use IiifServerTest\Controller\IiifServerControllerTestCase;

/**
 * Tests for the ImageSize controller plugin, focusing on:
 * - Automatic caching of dimensions into media.data via JSON_SET
 * - Correct JSON typing (integers, not strings)
 * - Non-destructive merge with existing media data
 */
class ImageSizeTest extends IiifServerControllerTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSize;

    /**
     * @var int|null
     */
    protected $testMediaId;

    /**
     * @var string|null Original media data to restore in tearDown.
     */
    protected $originalMediaData;

    public function setUp(): void
    {
        parent::setUp();
        $services = $this->getApplicationServiceLocator();
        $this->connection = $services->get('Omeka\Connection');
        $this->imageSize = $services->get('ControllerPluginManager')
            ->get('imageSize');

        // Create an item with media so we have a media row to test against.
        $item = $this->createItemWithHtmlMedia('Test item for ImageSize');
        $media = $item->media();
        $media = reset($media);
        $this->testMediaId = $media->id();

        // Backup original media data.
        $this->originalMediaData = $this->connection->fetchOne(
            'SELECT `data` FROM `media` WHERE `id` = ?',
            [$this->testMediaId]
        );
    }

    public function tearDown(): void
    {
        // Restore original media data.
        if ($this->testMediaId) {
            $this->connection->executeStatement(
                'UPDATE `media` SET `data` = ? WHERE `id` = ?',
                [$this->originalMediaData, $this->testMediaId]
            );
        }
        parent::tearDown();
    }

    public function testCacheOnNullData(): void
    {
        $this->connection->executeStatement(
            'UPDATE `media` SET `data` = NULL WHERE `id` = ?',
            [$this->testMediaId]
        );

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 800,
            'height' => 600,
        ]);

        $data = $this->getMediaData($this->testMediaId);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('dimensions', $data);
        $this->assertArrayHasKey('original', $data['dimensions']);
        $this->assertSame(800, $data['dimensions']['original']['width']);
        $this->assertSame(600, $data['dimensions']['original']['height']);
    }

    public function testCachePreservesExistingData(): void
    {
        $existingData = json_encode([
            'tileArgs' => ['source' => 'test', 'format' => 'deepzoom'],
        ]);
        $this->connection->executeStatement(
            'UPDATE `media` SET `data` = ? WHERE `id` = ?',
            [$existingData, $this->testMediaId]
        );

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 1024,
            'height' => 768,
        ]);

        $data = $this->getMediaData($this->testMediaId);
        $this->assertArrayHasKey('tileArgs', $data);
        $this->assertSame('test', $data['tileArgs']['source']);
        $this->assertSame(1024, $data['dimensions']['original']['width']);
        $this->assertSame(768, $data['dimensions']['original']['height']);
    }

    public function testCachePreservesOtherDimensionTypes(): void
    {
        $existingData = json_encode([
            'dimensions' => [
                'large' => ['width' => 400, 'height' => 300],
            ],
        ]);
        $this->connection->executeStatement(
            'UPDATE `media` SET `data` = ? WHERE `id` = ?',
            [$existingData, $this->testMediaId]
        );

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 1600,
            'height' => 1200,
        ]);

        $data = $this->getMediaData($this->testMediaId);
        $this->assertSame(400, $data['dimensions']['large']['width']);
        $this->assertSame(300, $data['dimensions']['large']['height']);
        $this->assertSame(1600, $data['dimensions']['original']['width']);
        $this->assertSame(1200, $data['dimensions']['original']['height']);
    }

    public function testCacheStoresIntegers(): void
    {
        $this->connection->executeStatement(
            'UPDATE `media` SET `data` = NULL WHERE `id` = ?',
            [$this->testMediaId]
        );

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 611,
            'height' => 980,
        ]);

        $raw = $this->connection->fetchOne(
            'SELECT `data` FROM `media` WHERE `id` = ?',
            [$this->testMediaId]
        );
        // Verify integer typing in raw JSON (not string "611").
        $this->assertStringContainsString('"width":611', $raw);
        $this->assertStringContainsString('"height":980', $raw);
        $this->assertStringNotContainsString('"611"', $raw);
    }

    public function testCacheRejectsUnsafeType(): void
    {
        $this->connection->executeStatement(
            'UPDATE `media` SET `data` = NULL WHERE `id` = ?',
            [$this->testMediaId]
        );

        // Attempt SQL injection via type parameter.
        $this->invokeCacheMediaDimensions(
            $this->testMediaId,
            "original', '1'); --",
            ['width' => 100, 'height' => 100]
        );

        // Data should remain NULL (injection rejected by regex).
        $raw = $this->connection->fetchOne(
            'SELECT `data` FROM `media` WHERE `id` = ?',
            [$this->testMediaId]
        );
        $this->assertEmpty($raw);
    }

    /**
     * getWidthAndHeightLocal() must return dimensions for a valid image.
     */
    public function testLocalSizeValidImage(): void
    {
        // Create a minimal 1x1 GIF.
        $tmpFile = tempnam(sys_get_temp_dir(), 'iiif_test_');
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        file_put_contents($tmpFile, $gif);

        $method = new \ReflectionMethod($this->imageSize, 'getWidthAndHeightLocal');
        $method->setAccessible(true);
        $result = $method->invoke($this->imageSize, $tmpFile);

        unlink($tmpFile);

        $this->assertSame(1, $result['width']);
        $this->assertSame(1, $result['height']);
    }

    /**
     * getWidthAndHeightLocal() must return emptySize for a missing file
     * without emitting any warning (the @ operator suppresses it).
     */
    public function testLocalSizeMissingFile(): void
    {
        $method = new \ReflectionMethod($this->imageSize, 'getWidthAndHeightLocal');
        $method->setAccessible(true);
        $result = $method->invoke(
            $this->imageSize,
            '/tmp/nonexistent_iiif_test_' . uniqid() . '.jpg'
        );

        $this->assertNull($result['width']);
        $this->assertNull($result['height']);
    }

    /**
     * getWidthAndHeightLocal() must return emptySize for a non-image file.
     */
    public function testLocalSizeNonImageFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'iiif_test_');
        file_put_contents($tmpFile, 'This is plain text, not an image.');

        $method = new \ReflectionMethod($this->imageSize, 'getWidthAndHeightLocal');
        $method->setAccessible(true);
        $result = $method->invoke($this->imageSize, $tmpFile);

        unlink($tmpFile);

        $this->assertNull($result['width']);
        $this->assertNull($result['height']);
    }

    /**
     * getWidthAndHeight() with a non-existent local path must return
     * emptySize without error (delegates to getWidthAndHeightLocal).
     */
    public function testGetWidthAndHeightMissingLocalPath(): void
    {
        $method = new \ReflectionMethod($this->imageSize, 'getWidthAndHeight');
        $method->setAccessible(true);
        $result = $method->invoke(
            $this->imageSize,
            '/tmp/nonexistent_iiif_test_' . uniqid() . '.png'
        );

        $this->assertNull($result['width']);
        $this->assertNull($result['height']);
    }

    protected function getMediaData(int $mediaId): ?array
    {
        $raw = $this->connection->fetchOne(
            'SELECT `data` FROM `media` WHERE `id` = ?',
            [$mediaId]
        );
        return $raw ? json_decode($raw, true) : null;
    }

    protected function invokeCacheMediaDimensions(
        int $mediaId,
        string $type,
        array $dimensions
    ): void {
        $method = new \ReflectionMethod($this->imageSize, 'cacheMediaDimensions');
        $method->setAccessible(true);
        $method->invoke($this->imageSize, $mediaId, $type, $dimensions);
    }
}
