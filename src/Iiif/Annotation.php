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

namespace IiifServer\Iiif;

/**
 *@link https://iiif.io/api/presentation/3.0/#56-annotation
 */
class Annotation extends AbstractResourceType
{
    protected $type = 'Annotation';

    protected $keys = [
        '@context' => self::NOT_ALLOWED,

        // Descriptive and rights properties.
        'label' => self::OPTIONAL,
        'metadata' => self::OPTIONAL,
        'summary' => self::OPTIONAL,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::NOT_ALLOWED,
        'language' => self::NOT_ALLOWED,
        'provider' => self::OPTIONAL,
        'thumbnail' => self::OPTIONAL,
        'placeholderCanvas' => self::NOT_ALLOWED,
        'accompanyingCanvas' => self::NOT_ALLOWED,

        // Technical properties.
        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        'height' => self::NOT_ALLOWED,
        'width' => self::NOT_ALLOWED,
        'duration' => self::NOT_ALLOWED,
        'viewingDirection' => self::NOT_ALLOWED,
        'behavior' => self::OPTIONAL,
        'timeMode' => self::OPTIONAL,

        // Linking properties.
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
        'homepage' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::NOT_ALLOWED,
        'supplementary' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::NOT_ALLOWED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::NOT_ALLOWED,

        // Behavior values.
        'auto-advance' => self::NOT_ALLOWED,
        'continuous' => self::NOT_ALLOWED,
        'facing-pages' => self::NOT_ALLOWED,
        'individuals' => self::NOT_ALLOWED,
        'multi-part' => self::NOT_ALLOWED,
        'no-auto-advance' => self::NOT_ALLOWED,
        'no-nav' => self::NOT_ALLOWED,
        'no-repeat' => self::NOT_ALLOWED,
        'non-paged' => self::NOT_ALLOWED,
        'hidden' => self::OPTIONAL,
        'paged' => self::NOT_ALLOWED,
        'repeat' => self::NOT_ALLOWED,
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'together' => self::NOT_ALLOWED,
        'unordered' => self::NOT_ALLOWED,

        // Not in specification, but required to build the manifest.
        'motivation' => self::REQUIRED,
        'body' => self::REQUIRED,
        'target' => self::REQUIRED,
    ];

    protected $orderedKeys = [
        '@context' => null,
        'id' => null,
        'type' => null,
        'motivation' => null,
        'body' => null,
        'target' => null,
    ];

    /**
     * @var \Omeka\Api\Representation\MediaRepresentation
     */
    protected $resource;

    public function getId()
    {
        $helper = $this->urlHelper;
        $url = $helper(
            'iiifserver/uri',
            [
                'id' => $this->resource->item()->id(),
                'type' => 'annotation',
                'name' => $this->resource->id(),
            ],
            ['force_canonical' => true]
        );
        $helper = $this->iiifForceBaseUrlIfRequired;
        return $helper($url);
    }

    public function getMotivation()
    {
        return 'painting';
    }

    public function getBody()
    {
        return new Annotation\Body($this->resource, [
            'type' => 'Image',
            'motivation' => $this->getMotivation(),
        ]);
    }

    public function getTarget()
    {
        $helper = $this->urlHelper;
        $url = $helper(
            'iiifserver/uri',
            [
                'id' => $this->resource->item()->id(),
                'type' => 'canvas',
                'name' => $this->resource->id(),
            ],
            ['force_canonical' => true]
        );
        $helper = $this->iiifForceBaseUrlIfRequired;
        return $helper($url);
    }
}
