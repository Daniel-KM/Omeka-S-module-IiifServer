<?php declare(strict_types=1);

/*
 * Copyright 2020-2021 Daniel Berthereau
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
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSize;

    /**
     * @var \IiifServer\View\Helper\IiifTileInfo
     */
    protected $iiifTileInfo;

    /**
     * @var string
     */
    protected $imageApiVersion;

    /**
     * @var array
     */
    protected $imageApiSupportedVersions;

    /**
     * @var \IiifServer\Iiif\ContentResource
     */
    protected $contentResource;

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

        $services = $this->resource->getServiceLocator();
        $viewHelpers = $services->get('ViewHelperManager');
        $this->iiifImageUrl = $viewHelpers->get('iiifImageUrl');
        $this->iiifTileInfo = $viewHelpers->get('iiifTileInfo');
        $this->imageSize = $services->get('ControllerPluginManager')->get('imageSize');

        $setting = $this->setting;
        $this->imageApiVersion = $setting('iiifserver_media_api_default_version', '2');
        $this->imageApiSupportedVersions = (array) $setting('iiifserver_media_api_supported_versions', ['2/2', '3/2']);
    }

    public function id(): ?string
    {
        if ($this->isMediaIiif()) {
            $mediaData = $this->resource->mediaData();
            return $mediaData['id'] ?? $mediaData['@id'];
        }

        if ($this->contentResource->isImage()) {
            // According to https://iiif.io/api/presentation/3.0/#57-content-resources,
            // "the URL may be the complete URL to a particular size of the image
            // content", so the large one here, and it's always a jpeg.
            // It's not needed to use the full original size.
            // TODO Check if Universal Viewer 2 is fixed for the body id.
            // Nevertheless, UniversalViewer 2 requires the original size image,
            // because it doesn't load the info.json, but only the id: it
            // considers it as the whole image.
            // $size = $this->imageSize->__invoke($this->resource, 'large');
            $size = $this->imageSize->__invoke($this->resource, 'original');
            if (empty($size)) {
                $size = $this->imageSize->__invoke($this->resource, 'large');
                if (empty($size)) {
                    $iiifSize = $this->imageApiVersion === '3' ? 'max' : 'full';
                } else {
                    $iiifSize = $size['width'] . ',' . $size['height'];
                }
            } else {
                // Nevertheless, to avoid to big files, a limit of 1000 px is set.
                if ($size['width'] > 1000 || $size['height'] > 1000) {
                    $sizeLarge = $this->imageSize->__invoke($this->resource, 'large');
                    if ($sizeLarge) {
                        $size = $sizeLarge;
                    }
                }
                $iiifSize = $size['width'] . ',' . $size['height'];
            }

            return $this->iiifImageUrl->__invoke($this->resource, 'imageserver/media', $this->imageApiVersion, [
                'region' => 'full',
                'size' => $iiifSize,
                'rotation' => 0,
                'quality' => 'default',
                'format' => 'jpg',
            ]);
        }

        if ($this->contentResource->isAudioVideo()) {
            // TODO Manage iiif 3 audio video.
            $imageUrl = $this->iiifImageUrl;
            return $imageUrl($this->resource, 'mediaserver/media', $this->imageApiVersion, [
                'format' => $this->resource->extension(),
            ]);
        }

        return $this->contentResource->id();
    }

    public function type(): ?string
    {
        return $this->contentResource->type();
    }

    public function format(): ?string
    {
        return $this->contentResource->format();
    }

    /**
     * @todo Return array of Service.
     */
    public function service(): ?array
    {
        // TODO Move this in ContentResource or TraitMedia.

        if ($this->isMediaIiif()) {
            $mediaData = $this->resource->mediaData();
            $imageResourceServices = [];
            $context = is_array($mediaData['@context']) ? array_pop($mediaData['@context']) : $mediaData['@context'];
            $id = $mediaData['id'] ?? $mediaData['@id'];
            $type = $this->_iiifType($context);
            $profile = $this->_iiifComplianceLevel($mediaData['profile']);
            if (!$id || !$type || !$profile) {
                return null;
            }
            $imageResourceServices[] = [
                'id' => $id,
                'type' => $type,
                'profile' => $profile,
            ];
            return $imageResourceServices;
        }

        if ($this->contentResource->isImage()) {
            $resourceIiifTileInfo = $this->iiifTileInfo->__invoke($this->resource);
            if ($resourceIiifTileInfo) {
                $resourceIiifTileInfo = [
                    'tiles' => [$resourceIiifTileInfo],
                    'height' => $this->height(),
                    'width' => $this->width(),
                ];
            } else {
                $resourceIiifTileInfo = [];
            }

            $imageResourceServices = [];
            foreach ($this->imageApiSupportedVersions as $supportedVersion) {
                $service = strtok($supportedVersion, '/');
                $level = strtok('/') ?: '0';
                $imageResourceService = [
                    'id' => $this->iiifImageUrl->__invoke($this->resource, 'imageserver/id', $service),
                    'type' => 'ImageService' . $service,
                    'profile' => 'level' . $level,
                ];
                $imageResourceService += $imageResourceService;
                $imageResourceServices[] = $imageResourceService;
            }

            return $imageResourceServices;
        }

        return null;
    }

    public function height(): ?int
    {
        return method_exists($this->contentResource, 'height')
            ? $this->contentResource->height()
            : null;
    }

    public function width(): ?int
    {
        return method_exists($this->contentResource, 'width')
            ? $this->contentResource->width()
            : null;
    }

    public function duration(): ?string
    {
        return method_exists($this->contentResource, 'duration')
            ? $this->contentResource->duration()
            : null;
    }

    /**
     * Get the iiif type from the context.
     */
    protected function _iiifType(string $context): ?string
    {
        $contexts = [
            'http://library.stanford.edu/iiif/image-api/context.json' => 'ImageService1',
            'http://library.stanford.edu/iiif/image-api/1.1/context.json' => 'ImageService1',
            'http://iiif.io/api/image/2/context.json' => 'ImageService2',
            'http://iiif.io/api/image/3/context.json' => 'ImageService3',
        ];
        return $contexts[$context] ?? null;
    }

    /**
     * Helper to set the compliance level to the IIIF Image API, based on the
     * compliance level URI
     *
     * @see https://iiif.io/api/image/1.1/compliance/
     *
     * @param array|string $profile Contents of the `profile` property from the
     * info.json
     * @return string Image API compliance level (returned value: level0 | level1 | level2)
     */
    protected function _iiifComplianceLevel($profile): string
    {
        // In Image API 2.1, the profile property is a list, and the first entry
        // is the compliance level URI.
        // In Image API 1.1 and 3.0, the profile property is a string.
        if (is_array($profile)) {
            $profile = $profile[0];
        }

        $profileToLlevels = [
            // Image API 1.0 profile.
            'http://library.stanford.edu/iiif/image-api/compliance.html' => 'level0',
            // Image API 1.1 profiles.
            'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level0' => 'level0',
            'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level1' => 'level1',
            'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level2' => 'level2',
            // Api 2.0.
            'http://iiif.io/api/image/2/level0.json' => 'level0',
            'http://iiif.io/api/image/2/level1.json' => 'level1',
            'http://iiif.io/api/image/2/level2.json' => 'level2',
            // in Image API 3.0, the profile property is a string with one of
            // these values: level0, level1, or level2 so just return the value…
            'level0' => 'level0',
            'level1' => 'level1',
            'level2' => 'level2',
        ];

        return $profileToLlevels[$profile] ?? 'level0';
    }
}
