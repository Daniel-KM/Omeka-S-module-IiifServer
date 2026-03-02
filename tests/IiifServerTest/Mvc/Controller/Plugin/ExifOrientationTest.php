<?php declare(strict_types=1);

namespace IiifServerTest\Mvc\Controller\Plugin;

use IiifServerTest\Controller\IiifServerControllerTestCase;

/**
 * EXIF orientation tests for ImageSize and MediaDimension plugins.
 *
 * The fixture test-exif6.jpg is 100x60 raw pixels with EXIF
 * orientation 6 (90° CW). After auto-orient the visual dimensions
 * are 60x100.
 */
class ExifOrientationTest extends IiifServerControllerTestCase
{
    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSize;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\MediaDimension
     */
    protected $mediaDimension;

    public function setUp(): void
    {
        parent::setUp();
        $services = $this->getApplicationServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $this->imageSize = $plugins->get('imageSize');
        $this->mediaDimension = $plugins->get('mediaDimension');
    }

    protected function fixtureExif6Path(): string
    {
        return dirname(__DIR__, 3) . '/fixtures/test-exif6.jpg';
    }

    /**
     * ImageSize::getWidthAndHeightLocal() must return EXIF-oriented
     * dimensions (60x100) for a 100x60 image with EXIF orientation 6.
     */
    public function testImageSizeLocalReturnsOrientedDimensions(): void
    {
        $method = new \ReflectionMethod($this->imageSize, 'getWidthAndHeightLocal');
        $method->setAccessible(true);

        $result = $method->invoke($this->imageSize, $this->fixtureExif6Path());

        $this->assertSame(60, $result['width'],
            'Width should be 60 after EXIF auto-orient');
        $this->assertSame(100, $result['height'],
            'Height should be 100 after EXIF auto-orient');
    }

    /**
     * ImageSize::getWidthAndHeight() must return EXIF-oriented
     * dimensions when given a local path.
     */
    public function testImageSizeGetWidthAndHeightLocal(): void
    {
        $method = new \ReflectionMethod($this->imageSize, 'getWidthAndHeight');
        $method->setAccessible(true);

        $result = $method->invoke($this->imageSize, $this->fixtureExif6Path());

        $this->assertSame(60, $result['width']);
        $this->assertSame(100, $result['height']);
    }

    /**
     * MediaDimension::getDimensionsLocal() must return EXIF-oriented
     * dimensions for a JPEG with EXIF orientation 6.
     */
    public function testMediaDimensionLocalReturnsOrientedDimensions(): void
    {
        $method = new \ReflectionMethod($this->mediaDimension, 'getDimensionsLocal');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->mediaDimension,
            $this->fixtureExif6Path(),
            'image'
        );

        $this->assertSame(60, $result['width'],
            'Width should be 60 after EXIF auto-orient');
        $this->assertSame(100, $result['height'],
            'Height should be 100 after EXIF auto-orient');
    }

    /**
     * Verify that a normal image (no EXIF rotation) still returns
     * the correct raw dimensions.
     */
    public function testImageSizeLocalNormalImageUnchanged(): void
    {
        // Create a minimal 2x3 GIF (no EXIF).
        $tmpFile = tempnam(sys_get_temp_dir(), 'iiif_test_');
        $img = imagecreatetruecolor(2, 3);
        imagejpeg($img, $tmpFile);
        imagedestroy($img);

        $method = new \ReflectionMethod($this->imageSize, 'getWidthAndHeightLocal');
        $method->setAccessible(true);
        $result = $method->invoke($this->imageSize, $tmpFile);

        unlink($tmpFile);

        $this->assertSame(2, $result['width']);
        $this->assertSame(3, $result['height']);
    }
}
