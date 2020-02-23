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
use IiifServer\Iiif\TraitImage;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * @todo The body should be created according or by the image server.
 */
class Body extends AbstractResourceType
{
    use TraitImage;

    /**
     * @todo Manage other types than image.
     *
     * @var string
     */
    protected $type = 'Image';

    protected $keys = [
        // Types for annotation body are not iiif.

        '@context' => self::NOT_ALLOWED,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'format' => self::REQUIRED,
        'service' => self::REQUIRED,
        'height' => self::RECOMMENDED,
        'width' => self::RECOMMENDED,
    ];

    protected $orderedKeys = [
        'id' => null,
        'type' => null,
        'format' => null,
        'service' => null,
        'height' => null,
        'width' => null,
    ];

    /**
     * @var \Omeka\Api\Representation\MediaRepresentation
     */
    protected $resource;

    /**
     * @var string
     */
    protected $serviceLevel;

    /**
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options
     * @return self
     */
    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        parent::__construct($resource, $options);
        $this->initImage();

        $viewHelpers = $this->resource->getServiceLocator()->get('ViewHelperManager');
        $setting = $viewHelpers->get('setting');
        $this->serviceLevel = $setting('imageserver_manifest_version', '2.1');
    }

    public function getId()
    {
        $size = $this->imageSize();
        if (!$size) {
            return null;
        }

        $helper = $this->iiifImageUrl;
        return $helper(
            'imageserver/media',
            [
                'id' => $this->resource->id(),
                'region' => 'full',
                'size' => $size['width'] . ',' . $size['height'],
                'rotation' => 0,
                'quality' => 'default',
                'format' => 'jpg',
            ]
        );
    }

    public function getFormat()
    {
        return $this->resource->mediaType();
    }

    public function getService()
    {
        // TODO Use the json from the image server.

        $helper = $this->urlHelper;
        $url = $helper(
            'imageserver/id',
            [
                'id' => $this->resource->id(),
            ],
            ['force_canonical' => true]
        );
        $helper = $this->iiifForceBaseUrlIfRequired;
        $id = $helper($url);

        switch ($this->serviceLevel) {
            case '3.0':
                return (object) [
                    'id' => $id,
                    'type' => 'ImageService3',
                    'profile' => 'level2',
                ];
            case '2.1':
            default:
                return (object) [
                    '@id' => $id,
                    '@type' => 'ImageService2',
                    'profile' => 'http://iiif.io/api/image/2/level2.json',
                ];
        }
    }
}
