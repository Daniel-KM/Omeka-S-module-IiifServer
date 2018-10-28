<?php
namespace IiifServer\View\Helper;

use IiifServer\Mvc\Controller\Plugin\ImageSize as ImageSizePlugin;
use Omeka\Api\Representation\MediaRepresentation;
use Zend\View\Helper\AbstractHelper;
use Omeka\Mvc\Exception\RuntimeException;
use Zend\View\Exception;

class ImageSize extends AbstractHelper
{
    /**
     * @var ImageSizePlugin
     */
    protected $imageSizePlugin;

    /**
     * @param ImageSizePlugin $imageSizePlugin
     */
    public function __construct(ImageSizePlugin $imageSizePlugin)
    {
        $this->imageSizePlugin = $imageSizePlugin;
    }

    /**
     * Get an array of the width and height of the image file from a media.
     *
     * @todo Store size in the data of the media.
     *
     * @param MediaRepresentation $media
     * @param string $imageType
     * @throws RuntimeException
     * @return array|null Associative array of width and height of the image
     * file, else null.
     */
    public function __invoke(MediaRepresentation $media, $imageType = 'original')
    {
        $imageSizePlugin = $this->imageSizePlugin;
        try {
            return $imageSizePlugin($media, $imageType);
        } catch (RuntimeException $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
    }
}
