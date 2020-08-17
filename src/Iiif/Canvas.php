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

use Omeka\Api\Representation\MediaRepresentation;

/**
 *@link https://iiif.io/api/presentation/3.0/#53-canvas
 */
class Canvas extends AbstractResourceType
{
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
        // Either size and/or duration are required.
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
    ];

    protected $behaviors = [
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

    public function __construct(MediaRepresentation $resource, array $options = null)
    {
        // This construct is required, because the resource must be a media.
        parent::__construct($resource, $options);
        // TODO Add linking properties when not in manifest.
    }

    public function getLabel()
    {
        $setting = $this->setting;
        $labelOption = $setting('iiifserver_manifest_canvas_label');
        $values = [];
        $fallback = (string) $this->options['index'];
        switch ($labelOption) {
            case 'property':
                $labelProperty = $setting('iiifserver_manifest_canvas_label_property');
                $values = $this->resource->value($labelProperty, ['all' => true, 'default' => $fallback]);
                break;
            case 'property_or_source':
                $labelProperty = $setting('iiifserver_manifest_canvas_label_property');
                $values = $this->resource->value($labelProperty, ['all' => true, 'default' => []]);
                if (count($values)) {
                    break;
                }
                // no break;
            case 'source':
                $values = $this->resource->displayTitle($fallback);
                break;
            case 'template_or_source':
                $fallback = $this->resource->displayTitle($fallback);
                // no break;
            case 'template':
                $template = $this->resource->resourceTemplate();
                if ($template && $template->titleProperty()) {
                    $labelProperty = $template->titleProperty()->term();
                    $values = $this->resource->value($labelProperty, ['all' => true, 'default' => []]);
                }
                if (!$values) {
                    $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => $fallback]);
                }
                break;
            case 'position':
            default:
                // Use fallback.
                break;
        }
        return new ValueLanguage($values, false, $fallback);
    }

    public function getId()
    {
        return $this->iiifUrl->__invoke($this->resource->item(), 'iiifserver/canvas', '3', [
            'name' => $this->resource->id(),
        ]);
    }

    /**
     * As the process converts Omeka resource, there is only one file by canvas
     * currently.
     */
    public function getItems()
    {
        if (!array_key_exists('items', $this->_storage)) {
            $this->_storage['items'] = [];
            if (isset($this->options['key']) && $this->options['key'] === 'annotation'
                && isset($this->options['motivation']) && $this->options['motivation'] === 'painting'
            ) {
                $item = new AnnotationPage($this->resource, $this->options);
                $this->_storage['items'][] = $item;
            }
        }
        return $this->_storage['items'];
    }

    public function getAnnotations()
    {
        if (!array_key_exists('annotations', $this->_storage)) {
            $this->_storage['annotations'] = [];
            if (isset($this->options['key']) && $this->options['key'] === 'annotation'
                && isset($this->options['motivation']) && $this->options['motivation'] !== 'painting'
            ) {
                $rendering = new AnnotationPage($this->resource, $this->options);
                $this->_storage['annotations'][] = $rendering;
            }
        }
        return $this->_storage['annotations'];
    }

    public function getRendering()
    {
        if (!array_key_exists('rendering', $this->_storage)) {
            $this->_storage['rendering'] = [];
            if (isset($this->options['key']) && $this->options['key'] === 'rendering') {
                $rendering = new Rendering($this->resource, $this->options);
                $this->_storage['rendering'][] = $rendering;
            }
        }
        return $this->_storage['rendering'];
    }

    public function getHeight()
    {
        return $this->canvasDimensions()['height'];
    }

    public function getWidth()
    {
        return $this->canvasDimensions()['width'];
    }

    public function getDuration()
    {
        return $this->canvasDimensions()['duration'];
    }

    protected function canvasDimensions()
    {
        if (!array_key_exists('dimension', $this->_storage)) {
            $heights = [0];
            $widths = [0];
            $durations = [0];
            foreach ($this->getItems() as $item) {
                foreach ($item->getItems() as $itemItem) {
                    if ($itemItem->getMotivation() !== 'painting') {
                        continue;
                    }
                    $body = $itemItem->getBody();
                    if (method_exists($body, 'getHeight')) {
                        $heights[] = $body->getHeight();
                    }
                    if (method_exists($body, 'getWidth')) {
                        $widths[] = $body->getWidth();
                    }
                    if (method_exists($body, 'getDuration')) {
                        $durations[] = $body->getDuration();
                    }
                }
            }
            $this->_storage['dimension']['height'] = max($heights) ?: null;
            $this->_storage['dimension']['width'] = max($widths) ?: null;
            $this->_storage['dimension']['duration'] = max($durations) ?: null;

            /* // TODO Required dimensions of canvas. It should work with pdf and UV.
            // The canvas must have a size or a duration. It depends on the type
            // of the view.
            // Image / Video.
            if (empty($this->_storage['dimension']['duration'])) {
                $this->keys['width'] = self::REQUIRED;
                $this->keys['height'] = self::REQUIRED;
            }
            // Audio / Video.
            if (empty($this->_storage['dimension']['width'])) {
                $this->keys['duration'] = self::REQUIRED;
            }
            */
        }

        return $this->_storage['dimension'];
    }
}
