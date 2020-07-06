<?php

/*
 * Copyright 2020 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace IiifServer\Iiif\Annotation;

use IiifServer\Iiif\AbstractResourceType;
use IiifServer\Iiif\TraitMedia;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * @todo The body should be created according or by the image server.
 */
class Body extends AbstractResourceType
{
    use TraitMedia;

    protected $keys = [
        // Types for annotation body are not iiif.

        '@context' => self::NOT_ALLOWED,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'format' => self::REQUIRED,
        // These keys are required or not allowed according to the type (image,
        // audio, or video). See construct.
        'service' => self::RECOMMENDED,
        'height' => self::RECOMMENDED,
        'width' => self::RECOMMENDED,
        'duration' => self::RECOMMENDED,
    ];

    /**
     * @var \IiifServer\View\Helper\IiifImageUrl
     */
    protected $iiifImageUrl;

    /**
     * @var \IiifServer\View\Helper\ImageSize
     */
    protected $imageSize;

    /**
     * @var \ImageServer\Mvc\Controller\Plugin\TileInfo|null
     */
    protected $tileInfo;

    /**
     * @var string
     */
    protected $imageApiVersion;

    /**
     * @var \IiifServer\Iiif\ContentResource
     */
    protected $contentResource;

    /**
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options
     * @return self
     */
    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        $this->contentResource = $options['content'];
        unset($options['content']);

        if ($this->contentResource->isImage()) {
            $this->keys['height'] = self::REQUIRED;
            $this->keys['width'] = self::REQUIRED;
            $this->keys['duration'] = self::NOT_ALLOWED;
        } elseif ($this->contentResource->isVideo()) {
            $this->keys['height'] = self::REQUIRED;
            $this->keys['width'] = self::REQUIRED;
            $this->keys['duration'] = self::REQUIRED;
        } elseif ($this->contentResource->isAudio()) {
            $this->keys['height'] = self::NOT_ALLOWED;
            $this->keys['width'] = self::NOT_ALLOWED;
            $this->keys['duration'] = self::REQUIRED;
        }

        parent::__construct($resource, $options);

        $this->initMedia();

        $viewHelpers = $this->resource->getServiceLocator()->get('ViewHelperManager');
        $this->iiifImageUrl = $viewHelpers->get('iiifImageUrl');
        $this->imageSize = $viewHelpers->get('imageSize');

        // Module Image Server is required to get specific data about the image
        $plugins = $this->resource->getServiceLocator()->get('ControllerPluginManager');
        $this->tileInfo = $plugins->has('tileInfo') ? $plugins->get('tileInfo') : null;

        $setting = $this->setting;
        $this->imageApiVersion = $setting('imageserver_info_default_version', '3');
    }

    public function getId()
    {
        if ($this->contentResource->isImage()) {
            // According to https://iiif.io/api/presentation/3.0/#57-content-resources,
            // "the URL may be the complete URL to a particular size of the image
            // content", so the large one here, and it's always a jpeg.
            // It's not needed to use the full original size.
            $helper = $this->imageSize;
            $imageSize = $helper($this->resource, 'large');
            list($widthLarge, $heightLarge) = $imageSize ? array_values($imageSize) : [null, null];
            $imageUrl = $this->iiifImageUrl;
            return $imageUrl($this->resource, 'imageserver/media', $this->imageApiVersion, [
                'region' => 'full',
                'size' => $widthLarge . ',' . $heightLarge,
                'rotation' => 0,
                'quality' => 'default',
                'format' => 'jpg',
            ]);
        } elseif ($this->contentResource->isAudioVideo()) {
            $imageUrl = $this->iiifImageUrl;
            return $imageUrl($this->resource, 'mediaserver/media', $this->imageApiVersion, [
                'format' => $this->resource->extension(),
            ]);
        } else {
            return $this->contentResource->getId();
        }
    }

    public function getType()
    {
        return $this->contentResource->getType();
    }

    public function getFormat()
    {
        return $this->contentResource->getFormat();
    }

    public function getService()
    {
        // TODO Move this in ContentResource or TraitMedia.
        if ($this->contentResource->isImage()) {
            // TODO Use the json from the image server.
            $helper = $this->iiifImageUrl;
            $id = $helper($this->resource, 'imageserver/id', $this->imageApiVersion);

            $imageResourceService = [];
            switch ($this->imageApiVersion) {
                case '2':
                    $imageResourceService = [
                        '@id' => $id,
                        '@type' => 'ImageService2',
                        'profile' => 'http://iiif.io/api/image/2/level2.json',
                    ];
                case '3':
                default:
                    $imageResourceService = [
                        'id' => $id,
                        'type' => 'ImageService3',
                        'profile' => 'level2',
                    ];
            }

            if ($this->tileInfo) {
                $helper = $this->tileInfo;
                $tilingData = $helper($this->resource);
                $iiifTileInfo = $tilingData ? $this->iiifTileInfo($tilingData) : null;
                if ($iiifTileInfo) {
                    $tiles = [];
                    $tiles[] = $iiifTileInfo;
                    $imageResourceService['tiles'] = $tiles;
                    $imageResourceService['height'] = $this->getHeight();
                    $imageResourceService['width'] = $this->getWidth();
                }
            }

            return (object) $imageResourceService;
        }

        return null;
    }

    public function getHeight()
    {
        return method_exists($this->contentResource, 'getHeight')
            ? $this->contentResource->getHeight()
            : null;
    }

    public function getWidth()
    {
        return method_exists($this->contentResource, 'getWidth')
        ? $this->contentResource->getWidth()
        : null;
    }

    public function getDuration()
    {
        return method_exists($this->contentResource, 'getDuration')
            ? $this->contentResource->getDuration()
            : null;
    }

    /**
     * Create the data for a IIIF tile object.
     *
     * @param array $tileInfo
     * @return array|null
     */
    protected function iiifTileInfo($tileInfo)
    {
        $tile = [];

        $squaleFactors = [];
        $maxSize = max($tileInfo['source']['width'], $tileInfo['source']['height']);
        $tileSize = $tileInfo['size'];
        $total = (int) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $squaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($squaleFactors) <= 1) {
            return null;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;
        return $tile;
    }
}
