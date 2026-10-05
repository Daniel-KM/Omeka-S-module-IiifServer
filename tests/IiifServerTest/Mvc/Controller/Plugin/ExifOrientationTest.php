<?php declare(strict_types=1);

namespace IiifServerTest\Mvc\Controller\Plugin;

use IiifServerTest\Controller\IiifServerControllerTestCase;

/**
 * EXIF orientation tests for the MediaDimension plugin.
 *
 * The fixture test-exif6.jpg is 100x60 raw pixels with EXIF
 * orientation 6 (90° CW). After auto-orient the visual dimensions
 * are 60x100.
 */
class ExifOrientationTest extends IiifServerControllerTestCase
{
    /**
     * @var \IiifServer\Mvc\Controller\Plugin\MediaDimension
     */
    protected $mediaDimension;

    public function setUp(): void
    {
        parent::setUp();
        $services = $this->getApplicationServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $this->mediaDimension = $plugins->get('mediaDimension');
    }

    protected function fixtureExif6Path(): string
    {
        return dirname(__DIR__, 3) . '/fixtures/test-exif6.jpg';
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

}
