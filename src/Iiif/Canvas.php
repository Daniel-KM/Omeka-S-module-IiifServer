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

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

/**
 *@link https://iiif.io/api/presentation/3.0/#53-canvas
 */
class Canvas extends AbstractResourceType
{
    use TraitImage;
    use TraitDescriptive;

    protected $type = 'Canvas';

    /**
     * @link https://iiif.io/api/presentation/3.0/#b-example-manifest-response
     *
     * @var array
     */
    protected $keys = [
        '@context' => self::OPTIONAL,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,

        // Descriptive and rights properties.
        'label' => self::RECOMMENDED,
        'metadata' => self::OPTIONAL,
        'summary' => self::OPTIONAL,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::OPTIONAL,
        'language' => self::NOT_ALLOWED,
        'provider' => self::OPTIONAL,
        'thumbnail' => self::OPTIONAL,
        // Except for a placeholder or an accompaying canvas.
        'placeholderCanvas' => self::OPTIONAL,
        'accompanyingCanvas' => self::OPTIONAL,

        // Technical properties.
        // 'id' => self::REQUIRED,
        // 'type' => self::REQUIRED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        // Height and width should be set together.
        'height' => self::OPTIONAL,
        'width' => self::OPTIONAL,
        'duration' => self::OPTIONAL,
        'viewingDirection' => self::NOT_ALLOWED,
        'behavior' => self::OPTIONAL,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
        'homepage' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::NOT_ALLOWED,
        'supplementary' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::RECOMMENDED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::OPTIONAL,

        // Behavior values.
        'auto-advance' => self::OPTIONAL,
        'continuous' => self::NOT_ALLOWED,
        'facing-pages' => self::OPTIONAL,
        'individuals' => self::NOT_ALLOWED,
        'multi-part' => self::NOT_ALLOWED,
        'no-auto-advance' => self::OPTIONAL,
        'no-nav' => self::NOT_ALLOWED,
        'no-repeat' => self::NOT_ALLOWED,
        'non-paged' => self::OPTIONAL,
        'hidden' => self::NOT_ALLOWED,
        'paged' => self::NOT_ALLOWED,
        'repeat' => self::NOT_ALLOWED,
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'together' => self::NOT_ALLOWED,
        'unordered' => self::NOT_ALLOWED,
    ];

    /**
     * @var \Omeka\Api\Representation\MediaRepresentation
     */
    protected $resource;

    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        if (!($resource instanceof MediaRepresentation)) {
            throw new \RuntimeException(
                'A media is required to build a canvas.'
            );
        }

        parent::__construct($resource, $options);

        $this->initImage();
        // TODO Add linking properties when not in manifest.
    }

    public function getLabel()
    {
        // TODO Store the use of the fallback to avoid to copy the parent.

        $template = $this->resource->resourceTemplate();
        if ($template && $template->titleProperty()) {
            $values = $this->resource->value($template->titleProperty()->term(), ['all' => true, 'default' => []]);
            if (empty($values)) {
                $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => []]);
            }
        } else {
            $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => []]);
        }
        return new ValueLanguage($values, false, '[' . $this->resource->id() . ']');
    }

    public function getId()
    {
        $helper = $this->urlHelper;
        $url = $helper(
            'iiifserver/canvas',
            [
                'id' => $this->resource->item()->id(),
                'name' => $this->resource->id(),
            ],
            ['force_canonical' => true]
        );
        $helper = $this->iiifForceBaseUrlIfRequired;
        return $helper($url);
    }

    /**
     * As the process converts Omeka resource, there is only one file by canvas.
     */
    public function getItems()
    {
        $item = new AnnotationPage($this->resource, $this->options);
        return [$item];
    }
}
