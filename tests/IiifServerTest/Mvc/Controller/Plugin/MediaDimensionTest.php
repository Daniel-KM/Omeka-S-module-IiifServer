<?php declare(strict_types=1);

namespace IiifServerTest\Mvc\Controller\Plugin;

use IiifServerTest\Controller\IiifServerControllerTestCase;

/**
 * Tests for the MediaDimension controller plugin, focusing on:
 * - Dimension caching with width, height, and duration
 * - Proper JSON typing (integers for w/h, float for duration, null)
 * - Non-destructive merge with existing media data
 */
class MediaDimensionTest extends IiifServerControllerTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\MediaDimension
     */
    protected $mediaDimension;

    /**
     * @var int|null
     */
    protected $testMediaId;

    /**
     * @var string|null
     */
    protected $originalMediaData;

    public function setUp(): void
    {
        parent::setUp();
        $services = $this->getApplicationServiceLocator();
        $this->connection = $services->get('Omeka\Connection');
        $this->mediaDimension = $services->get('ControllerPluginManager')
            ->get('mediaDimension');

        $item = $this->createItemWithHtmlMedia('Test item for MediaDimension');
        $media = $item->media();
        $media = reset($media);
        $this->testMediaId = $media->id();

        $this->originalMediaData = $this->connection->fetchOne(
            'SELECT `data` FROM `media` WHERE `id` = ?',
            [$this->testMediaId]
        );
    }

    public function tearDown(): void
    {
        if ($this->testMediaId) {
            $this->connection->executeStatement(
                'UPDATE `media` SET `data` = ? WHERE `id` = ?',
                [$this->originalMediaData, $this->testMediaId]
            );
        }
        parent::tearDown();
    }

    public function testCacheImageDimensions(): void
    {
        $this->resetMediaData();

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 1920,
            'height' => 1080,
            'duration' => null,
        ]);

        $data = $this->getMediaData($this->testMediaId);
        $this->assertSame(1920, $data['dimensions']['original']['width']);
        $this->assertSame(1080, $data['dimensions']['original']['height']);
        $this->assertNull($data['dimensions']['original']['duration']);
    }

    public function testCacheVideoDimensionsWithDuration(): void
    {
        $this->resetMediaData();

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 1920,
            'height' => 1080,
            'duration' => 125.5,
        ]);

        $data = $this->getMediaData($this->testMediaId);
        $this->assertSame(1920, $data['dimensions']['original']['width']);
        $this->assertSame(1080, $data['dimensions']['original']['height']);
        $this->assertSame(125.5, $data['dimensions']['original']['duration']);
    }

    public function testCacheAudioDimensionsNullWidthHeight(): void
    {
        $this->resetMediaData();

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => null,
            'height' => null,
            'duration' => 302.7,
        ]);

        $data = $this->getMediaData($this->testMediaId);
        $this->assertNull($data['dimensions']['original']['width']);
        $this->assertNull($data['dimensions']['original']['height']);
        $this->assertSame(302.7, $data['dimensions']['original']['duration']);
    }

    public function testCacheMultipleTypesSequentially(): void
    {
        $this->resetMediaData();

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 4000, 'height' => 3000, 'duration' => null,
        ]);
        $this->invokeCacheMediaDimensions($this->testMediaId, 'large', [
            'width' => 800, 'height' => 600, 'duration' => null,
        ]);
        $this->invokeCacheMediaDimensions($this->testMediaId, 'medium', [
            'width' => 400, 'height' => 300, 'duration' => null,
        ]);

        $data = $this->getMediaData($this->testMediaId);
        $this->assertCount(3, $data['dimensions']);
        $this->assertSame(4000, $data['dimensions']['original']['width']);
        $this->assertSame(800, $data['dimensions']['large']['width']);
        $this->assertSame(400, $data['dimensions']['medium']['width']);
    }

    public function testCacheRawJsonTyping(): void
    {
        $this->resetMediaData();

        $this->invokeCacheMediaDimensions($this->testMediaId, 'original', [
            'width' => 800,
            'height' => 600,
            'duration' => 45.3,
        ]);

        $raw = $this->connection->fetchOne(
            'SELECT `data` FROM `media` WHERE `id` = ?',
            [$this->testMediaId]
        );
        $this->assertStringContainsString('"width":800', $raw);
        $this->assertStringNotContainsString('"800"', $raw);
        $this->assertStringContainsString('"duration":45.3', $raw);
    }

    /**
     * getDimensionsLocal() with mainMediaType='image' must return dimensions
     * for a valid image file.
     */
    public function testLocalDimensionsValidImage(): void
    {
        // Create a minimal 1x1 GIF.
        $tmpFile = tempnam(sys_get_temp_dir(), 'iiif_test_');
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        file_put_contents($tmpFile, $gif);

        $method = new \ReflectionMethod($this->mediaDimension, 'getDimensionsLocal');
        $method->setAccessible(true);
        $result = $method->invoke($this->mediaDimension, $tmpFile, 'image');

        unlink($tmpFile);

        $this->assertSame(1, $result['width']);
        $this->assertSame(1, $result['height']);
        $this->assertNull($result['duration']);
    }

    /**
     * getDimensionsLocal() with mainMediaType='image' must return
     * emptyDimensions for a missing file without emitting any warning.
     */
    public function testLocalDimensionsMissingImageFile(): void
    {
        $method = new \ReflectionMethod($this->mediaDimension, 'getDimensionsLocal');
        $method->setAccessible(true);
        $result = $method->invoke(
            $this->mediaDimension,
            '/tmp/nonexistent_iiif_test_' . uniqid() . '.jpg',
            'image'
        );

        // @getimagesize() returns false → falls through to GetId3 which
        // also returns empty data for a missing file.
        $this->assertNull($result['width']);
        $this->assertNull($result['duration']);
    }

    /**
     * getDimensionsLocal() with mainMediaType='image' on a non-image file
     * must return emptyDimensions (no width/height).
     */
    public function testLocalDimensionsNonImageFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'iiif_test_');
        file_put_contents($tmpFile, 'Plain text, not an image.');

        $method = new \ReflectionMethod($this->mediaDimension, 'getDimensionsLocal');
        $method->setAccessible(true);
        $result = $method->invoke($this->mediaDimension, $tmpFile, 'image');

        unlink($tmpFile);

        // @getimagesize() returns false for non-image content.
        $this->assertNull($result['width']);
        $this->assertNull($result['height']);
    }

    protected function resetMediaData(): void
    {
        $this->connection->executeStatement(
            'UPDATE `media` SET `data` = NULL WHERE `id` = ?',
            [$this->testMediaId]
        );
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
        $method = new \ReflectionMethod(
            $this->mediaDimension,
            'cacheMediaDimensions'
        );
        $method->setAccessible(true);
        $method->invoke($this->mediaDimension, $mediaId, $type, $dimensions);
    }
}
