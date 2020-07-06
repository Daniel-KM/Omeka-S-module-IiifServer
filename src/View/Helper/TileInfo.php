<?php

namespace IiifServer\View\Helper;

use Omeka\Api\Representation\MediaRepresentation;
use Zend\View\Helper\AbstractHelper;

class TileInfo extends AbstractHelper
{
    /**
     * @var \ImageServer\Mvc\Controller\Plugin\TileInfo
     */
    protected $tileInfoPlugin;

    /**
     * @param \ImageServer\Mvc\Controller\Plugin\TileInfo $tileInfoPlugin
     */
    public function __construct($tileInfoPlugin)
    {
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
        if (!$this->tileInfoPlugin) {
            throw new \Exception('The module image server is currently required to get data about image.'); // @translate
        }

        $tileInfo = $this->tileInfoPlugin;
        return $tileInfo($media);
    }
}
