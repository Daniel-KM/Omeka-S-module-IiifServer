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

use ArrayObject;
use Doctrine\Common\Inflector\Inflector;
use JsonSerializable;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Manage the IIIF resource types (iiif v3.0).
 *
 * @todo Use JsonLdSerializable?
 * @author Daniel Berthereau
 */
abstract class AbstractResourceType implements JsonSerializable
{
    const REQUIRED = 'required';
    const RECOMMENDED = 'recommended';
    const OPTIONAL = 'optional';
    const NOT_ALLOWED = 'not_allowed';

    /**
     * @var string
     */
    protected $type;

    /**
     * List of keys for the resource type.
     *
     * @link https://iiif.io/api/presentation/3.0/#a-summary-of-property-requirements
     *
     * @var array
     */
    protected $keys = [
        // Descriptive and rights properties.
        'label' => self::NOT_ALLOWED,
        'metadata' => self::NOT_ALLOWED,
        'summary' => self::NOT_ALLOWED,
        'requiredStatement' => self::NOT_ALLOWED,
        'rights' => self::NOT_ALLOWED,
        'navDate' => self::NOT_ALLOWED,
        'language' => self::NOT_ALLOWED,
        'provider' => self::NOT_ALLOWED,
        'thumbnail' => self::NOT_ALLOWED,
        'placeholderCanvas' => self::NOT_ALLOWED,
        'accompanyingCanvas' => self::NOT_ALLOWED,

        // Technical properties.
        'id' => self::NOT_ALLOWED,
        'type' => self::NOT_ALLOWED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        'height' => self::NOT_ALLOWED,
        'width' => self::NOT_ALLOWED,
        'duration' => self::NOT_ALLOWED,
        'viewingDirection' => self::NOT_ALLOWED,
        'behavior' => self::NOT_ALLOWED,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        'seeAlso' => self::NOT_ALLOWED,
        'service' => self::NOT_ALLOWED,
        'homepage' => self::NOT_ALLOWED,
        'rendering' => self::NOT_ALLOWED,
        'partOf' => self::NOT_ALLOWED,
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
        'hidden' => self::NOT_ALLOWED,
        'paged' => self::NOT_ALLOWED,
        'repeat' => self::NOT_ALLOWED,
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'together' => self::NOT_ALLOWED,
        'unordered' => self::NOT_ALLOWED,
    ];

    /**
     * @var array
     */
    protected $orderedKeys = [
        '@context' => null,
        'id' => null,
        'type' => null,
    ];

    /**
     * @var AbstractResourceEntityRepresentation
     */
    protected $resource;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var ArrayObject
     */
    protected $manifest;

    /**
     * @var \Zend\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * @var \IiifServer\View\Helper\IiifForceBaseUrlIfRequired
     */
    protected $iiifForceBaseUrlIfRequired;

    /**
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options
     * @return self
     */
    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        $this->resource = $resource;
        $this->options = $options;
        $viewHelpers = $resource->getServiceLocator()->get('ViewHelperManager');
        $this->urlHelper = $viewHelpers->get('url');
        $this->iiifForceBaseUrlIfRequired = $viewHelpers->get('iiifForceBaseUrlIfRequired');
    }

    /**
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation
     */
    public function getResource()
    {
        return $this->resource;
    }

    public function getData()
    {
        $this->manifest = new ArrayObject;

        // TODO Remove useless context from sub-objects.
        $this->manifest['@context'] = $this->getContext();

        $keys = array_filter($this->keys, function($v) {
            return $v !== self::NOT_ALLOWED;
        });

        foreach (array_keys($keys) as $key) {
            $method = 'get' . Inflector::classify($key);
            if (method_exists($this, $method)) {
                $this->manifest[$key] = $this->$method();
            }
        }

        $this->orderKeys();

        return $this->manifest;
    }

    public function jsonSerialize()
    {
        // Remove useless values (there is no "0", "null" or empty array at
        // first level).
        $output = array_filter($this->getData()->getArrayCopy(), function($v) {
            if ($v instanceof ArrayObject) {
                return (bool) $v->count();
            }
            if ($v instanceof JsonSerializable) {
                return (bool) $v->jsonSerialize();
            }
            return !empty($v);
        });

        // Check if all required data are present.
        $keys = array_filter($this->keys, function($v) {
            return $v === self::REQUIRED;
        });

        $intersect = array_intersect_key($keys, $output);
        if (count($keys) !== count($intersect)) {
            $missingKeys = array_keys(array_diff_key($keys, $intersect));
            throw new \RuntimeException(
                sprintf('Missing required keys for resource type "%1$s": "%2$s".', $this->getType(), implode('", "', $missingKeys))
            );
        }

        return (object) $output;
    }

    public function getContext()
    {
        return 'http://iiif.io/api/presentation/3/context.json';
    }

    /**
     * @return ValueLanguage
     */
    public function getLabel()
    {
        $template = $this->resource->resourceTemplate();
        if ($template && $template->titleProperty()) {
            $values = $this->resource->value($template->titleProperty()->term(), ['all' => true, 'default' => []]);
            if (empty($values)) {
                $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => []]);
            }
        } else {
            $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => []]);
        }
        return new ValueLanguage($values);
    }

    /**
     * @return string
     */
    abstract public function getId();

    /**
     * @return string
     */
    public function getType()
    {
        return (string) $this->type;
    }

    protected function orderKeys()
    {
        $array = $this->manifest->getArrayCopy();
        $this->manifest->exchangeArray(
            array_replace(array_intersect_key($this->orderedKeys, $array), $array)
        );
        return $this;
    }
}
