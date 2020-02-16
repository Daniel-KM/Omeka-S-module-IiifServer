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
 * @link https://iiif.io/api/presentation/3.0/#52-manifest
 */
class Manifest extends AbstractResourceType
{
    protected $type = 'Manifest';

    protected $keys = [
        // Descriptive and rights properties.
        'label' => self::REQUIRED,
        'metadata' => self::RECOMMENDED,
        'summary' => self::RECOMMENDED,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::OPTIONAL,
        'language' => self::NOT_ALLOWED,
        'provider' => self::RECOMMENDED,
        'thumbnail' => self::RECOMMENDED,
        'placeholderCanvas' => self::OPTIONAL,
        'accompanyingCanvas' => self::OPTIONAL,

        // Technical properties.
        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        'height' => self::NOT_ALLOWED,
        'width' => self::NOT_ALLOWED,
        'duration' => self::NOT_ALLOWED,
        'viewingDirection' => self::OPTIONAL,
        'behavior' => self::OPTIONAL,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
        'homepage' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::OPTIONAL,
        'supplementary' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::REQUIRED,
        'structures' => self::OPTIONAL,
        'annotations' => self::OPTIONAL,

        // Behavior values.
        'auto-advance' => self::OPTIONAL,
        'continuous' => self::OPTIONAL,
        'facing-pages' => self::NOT_ALLOWED,
        'individuals' => self::OPTIONAL,
        'multi-part' => self::NOT_ALLOWED,
        'no-auto-advance' => self::OPTIONAL,
        'no-nav' => self::NOT_ALLOWED,
        'no-repeat' => self::OPTIONAL,
        'non-paged' => self::NOT_ALLOWED,
        'hidden' => self::NOT_ALLOWED,
        'paged' => self::OPTIONAL,
        'repeat' => self::OPTIONAL,
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'together' => self::OPTIONAL,
        'unordered' => self::OPTIONAL,
    ];

    /**
     * @link https://iiif.io/api/presentation/3.0/#b-example-manifest-response
     *
     * @var array
     */
    protected $orderedKeys = [
        '@context' => null,
        'id' => null,
        'type' => null,
        'label' => null,
        'metadata' => null,
        'summary' => null,

        'thumbnail' => null,

        'viewingDirection' => null,
        'behavior' => null,
        'navDate' => null,

        'rights' => null,
        'requiredStatement' => null,
        'logo' => null,
        'homepage' => null,
        'service' => null,
        'seeAlso' => null,
        'rendering' => null,
        'partOf' => null,
        'start' => null,
        'id' => null,
        'type' => null,

        'items' => null,

        'structures' => null,

        'annotations' => null,
    ];

    public function getId()
    {
        $helper = $this->urlHelper;
        $url = $helper(
            'iiifserver/item',
            ['id' => $this->resource->id()],
            ['force_canonical' => true]
        );
        $helper = $this->iiifForceBaseUrlIfRequired;
        return $helper($url);
    }
}
