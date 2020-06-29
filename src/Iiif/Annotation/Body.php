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
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * @todo The body should be created according or by the image server.
 */
class Body extends AbstractResourceType
{
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
     * @var string
     */
    protected $serviceLevel;

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

        $viewHelpers = $this->resource->getServiceLocator()->get('ViewHelperManager');
        $this->iiifImageUrl = $viewHelpers->get('iiifImageUrl');

        $setting = $this->setting;
        $this->serviceLevel = $setting('imageserver_manifest_version', '3');
    }

    public function getId()
    {
        /** @var \IiifServer\Iiif\ContentResource $contentResource */
        if ($this->contentResource->isImage()) {
            $imageUrl = $this->iiifImageUrl;
            $cleanIdentifiers = $this->iiifCleanIdentifiers;
            return $imageUrl(
                'imageserver/media',
                [
                    'version' => $this->serviceLevel,
                    'id' => $cleanIdentifiers($this->resource->id()),
                    'region' => 'full',
                    'size' => $this->contentResource->getWidth() . ',' . $this->contentResource->getHeight(),
                    'rotation' => 0,
                    'quality' => 'default',
                    'format' => 'jpg',
                ]
            );
        }

        if ($this->contentResource->isAudioVideo()) {
            $imageUrl = $this->iiifImageUrl;
            $cleanIdentifiers = $this->iiifCleanIdentifiers;
            return $imageUrl(
                'mediaserver/media',
                [
                    'version' => $this->serviceLevel,
                    'id' => $cleanIdentifiers($this->resource->id()),
                    'format' => $this->resource->extension(),
                ]
            );
        }

        return $this->contentResource->getId();
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
            $helper = $this->urlHelper;
            $cleanIdentifiers = $this->iiifCleanIdentifiers;
            $url = $helper(
                'imageserver/id',
                [
                    'version' => $this->serviceLevel,
                    'id' => $cleanIdentifiers($this->resource->id()),
                ],
                ['force_canonical' => true]
            );
            $helper = $this->iiifForceBaseUrlIfRequired;
            $id = $helper($url);

            switch ($this->serviceLevel) {
                case '2':
                    return (object) [
                        '@id' => $id,
                        '@type' => 'ImageService2',
                        'profile' => 'http://iiif.io/api/image/2/level2.json',
                    ];
                case '3':
                default:
                    return (object) [
                        'id' => $id,
                        'type' => 'ImageService3',
                        'profile' => 'level2',
                    ];
            }
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
}
