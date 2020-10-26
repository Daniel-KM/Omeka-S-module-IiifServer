<?php declare(strict_types=1);
namespace IiifServer\View\Helper;

// Plugin ImageSize may be overridden by module ImageServer or another one.
// use IiifServer\Mvc\Controller\Plugin\ImageSize as ImageSizePlugin;
use Laminas\View\Exception;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Mvc\Exception\RuntimeException;

class ImageSize extends AbstractHelper
{
    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize|\ImageServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSizePlugin;

    /**
     * @param \IiifServer\Mvc\Controller\Plugin\ImageSize|\ImageServer\Mvc\Controller\Plugin\ImageSize $imageSizePlugin
     */
    public function __construct($imageSizePlugin)
    {
        $this->imageSizePlugin = $imageSizePlugin;
    }

    /**
     * Get an array of the width and height of the image file from a media.
     *
     * @todo Store size in the data of the media. Or use numeric properties (with units).
     *
     * @param \Omeka\Api\Representation\MediaRepresentation|\Omeka\Api\Representation\AssetRepresentation|string $image Can
     * be a media, an asset, an url or a filepath.
     * @param string $imageType
     * @throws RuntimeException
     * @return array|null Associative array of width and height of the image
     * file, else null.
     */
    public function __invoke($image, $imageType = 'original')
    {
        $imageSize = $this->imageSizePlugin;
        try {
            return $imageSize($image, $imageType);
        } catch (RuntimeException $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
    }
}
