<?php declare(strict_types=1);

/*
 * Copyright 2020-2024 Daniel Berthereau
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

namespace IiifServer\Iiif;

use Omeka\Api\Representation\MediaRepresentation;

/**
 *@link https://iiif.io/api/image/3.0/
 */
class ImageService3 extends AbstractResourceType
{
    use TraitImage;
    use TraitRights;

    protected $type = 'ImageService3';

    protected $propertyRequirements = [
        '@context' => self::REQUIRED,

        // Technical properties.
        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'protocol' => self::REQUIRED,
        'profile' => self::REQUIRED,
        'width' => self::REQUIRED,
        'height' => self::REQUIRED,
        // The maxWidth and maxHeight are optional/required together.
        // If only maxWidth is set, maxHeight uses it, but the inverse is not possible.
        'maxWidth' => self::OPTIONAL,
        'maxHeight' => self::OPTIONAL,
        'maxArea' => self::OPTIONAL,

        // Sizes.
        'sizes' => self::OPTIONAL,

        // Tiles.
        'tiles' => self::OPTIONAL,

        // Preferred formats.
        'preferredFormats' => self::OPTIONAL,

        // Rights.
        'rights' => self::OPTIONAL,

        // Extra functionality.
        'extraQualities' => self::OPTIONAL,
        'extraFormats' => self::OPTIONAL,
        'extraFeatures' => self::OPTIONAL,

        // Linking properties.
        'partOf' => self::OPTIONAL,
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
    ];

    /**
     * @var bool
     */
    protected $hasImageServer;

    /**
     * @var ?\ImageServer\Mvc\Controller\Plugin\TileMediaInfo
     */
    protected $tileMediaInfo;

    /**
     * @var \Omeka\Api\Representation\MediaRepresentation
     */
    protected $resource;

    public function __construct(MediaRepresentation $resource, array $options = null)
    {
        parent::__construct($resource, $options);

        $plugins = $this->resource->getServiceLocator()->get('ControllerPluginManager');
        $this->hasImageServer = $plugins->has('tileMediaInfo');
        $this->tileMediaInfo = $this->hasImageServer ? $plugins->get('tileMediaInfo') : null;

        // TODO Use subclass to manage image or media. Currently, only image.
        $this->initImage();
    }

    public function isImage(): bool
    {
        return true;
    }

    /**
     * @todo Manage extensions.
     *
     * {@inheritDoc}
     * @see \IiifServer\Iiif\AbstractResourceType::context()
     */
    public function context(): ?string
    {
        return 'http://iiif.io/api/image/3/context.json';
    }

    public function label(): ?ValueLanguage
    {
        // There is no label for an image service.
        return null;
    }

    public function id(): ?string
    {
        $routeImage = $this->settings->get('iiifserver_media_api_identifier_infojson')
            ? 'imageserver/info'
            : 'imageserver/id';
        return $this->iiifMediaUrl->__invoke($this->resource, $routeImage, '3');
    }

    public function protocol(): ?string
    {
        return 'http://iiif.io/api/image';
    }

    public function profile(): ?string
    {
        return 'level2';
    }

    public function maxWidth(): ?int
    {
        return $this->width();
    }

    public function maxHeight(): ?int
    {
        return $this->height();
    }

    public function maxArea(): ?int
    {
        $size = $this->imageSize();
        return $size['width'] * $size['height'] ?: null;
    }

    public function sizes(): ?array
    {
        if (!$this->isImage()) {
            return null;
        }

        $sizes = [];

        // TODO Use the config for specific types.
        $imageTypes = ['medium', 'large', 'original'];
        foreach ($imageTypes as $imageType) {
            $imageSize = new Size($this->resource, ['image_type' => $imageType]);
            if ($imageSize->hasSize()) {
                $sizes[] = $imageSize;
            }
        }

        return $sizes;
    }

    public function tiles(): ?array
    {
        if (!$this->hasImageServer || !$this->isImage()) {
            return null;
        }

        $tiles = [];

        // TODO Use a standard json-serializable TileInfo.
        $tilingData = $this->tileMediaInfo->__invoke($this->resource);
        if ($tilingData) {
            $iiifTileInfo = new \ImageServer\Iiif\Tile($this->resource, ['tilingData' => $tilingData]);
            if ($iiifTileInfo->hasTilingInfo()) {
                $tiles[] = $iiifTileInfo;
            }
        }

        return $tiles;
    }

    /**
     * The preferred format is jpeg, since the image server uses it by default.
     *
     * @todo Allow to create tiles with webp, gif, and png and add them here.
     */
    public function preferredFormats(): ?array
    {
        return [
            'jpg',
        ];
    }

    public function rights(): ?string
    {
        $url = null;
        $orUrl = false;

        $param = $this->settings->get($this->hasImageServer ? 'imageserver_info_rights' : 'iiifserver_manifest_rights');
        switch ($param) {
            case 'url':
                if ($this->hasImageServer) {
                    $url = $this->settings->get('imageserver_info_rights_uri') ?: $this->settings->get('imageserver_info_rights_url');
                } else {
                    $url = $this->settings->get('iiifserver_manifest_rights_uri') ?: $this->settings->get('iiifserver_manifest_rights_url');
                }
                break;
            case 'property_or_url':
                $orUrl = true;
                // no break.
            case 'property':
                $property = $this->settings->get($this->hasImageServer ? 'imageserver_info_rights_property' : 'iiifserver_manifest_rights_property');
                $url = (string) $this->resource->value($property);
                break;
            case 'item_or_url':
                $orUrl = true;
                // no break.
            case 'item':
                // Here, the resource is a media.
                $url = $this->rightsResource($this->resource->item());
                if ($url || !$orUrl) {
                    return $url;
                }
                break;
            // This method is only for Api 3.
            case 'text':
            case 'property_or_text':
            case 'none':
            default:
                return null;
        }

        if (!$url && $orUrl) {
            if ($this->hasImageServer) {
                $url = $this->settings->get('imageserver_info_rights_uri') ?: $this->settings->get('imageserver_info_rights_url');
            } else {
                $url = $this->settings->get('iiifserver_manifest_rights_uri') ?: $this->settings->get('iiifserver_manifest_rights_url');
            }
        }

        if ($url) {
            foreach ($this->rightUrls as $rightUrl) {
                if (strpos($url, $rightUrl) === 0) {
                    return $url;
                }
            }
        }

        return null;
    }

    public function extraQualities(): ?array
    {
        return null;
    }

    public function extraFormats(): ?array
    {
        return null;
    }

    /**
     * @link https://iiif.io/api/image/2.1/#profile-description
     * @link https://iiif.io/api/image/3.0/#6-compliance-level-and-profile-document
     */
    public function extraFeatures(): ?array
    {
        // See https://iiif.io/api/image/3/context.json.
        /*
        $support = [
            'baseUriRedirect',
            'canonicalLinkHeader',
            'cors',
            'jsonldMediaType',
            'mirroring',
            'profileLinkHeader',
            'regionByPct',
            'regionByPx',
            'regionSquare',
            'rotationArbitrary',
            'rotationBy90s',
            'sizeByConfinedWh',
            'sizeByH',
            'sizeByPct',
            'sizeByW',
            'sizeByWh',
            'sizeUpscaling',
        ];
        */
        return null;
    }
}
