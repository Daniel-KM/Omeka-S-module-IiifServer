<?php

namespace IiifServer\View\Helper;

use Omeka\Api\Representation\MediaRepresentation;
use Zend\View\Helper\AbstractHelper;

class TileInfo extends AbstractHelper
{
    /**
     * @var string
     */
    protected $serviceImage;

    /**
     * @var \ImageServer\Mvc\Controller\Plugin\TileInfo
     */
    protected $tileInfoPlugin;

    /**
     * @param string $serviceImage
     * @param \ImageServer\Mvc\Controller\Plugin\TileInfo $tileInfoPlugin
     */
    public function __construct($serviceImage, $tileInfoPlugin)
    {
        $this->serviceImage = $serviceImage;
        $this->tileInfoPlugin = $tileInfoPlugin;
    }

    /**
     * Retrieve info about the tiling of an image.
     *
     * @param MediaRepresentation $media
     * @return array|null
     */
    public function __invoke(MediaRepresentation $media)
    {
        if ($this->serviceImage) {
            throw new \Exception('External image server is not totally managed currently');
        }

        if (!$this->tileInfoPlugin) {
            throw new \Exception('The module image server is currently required.');
        }

        $tileInfo = $this->tileInfoPlugin;
        $tilingData = $tileInfo($media);
        return $tilingData;
    }
}
