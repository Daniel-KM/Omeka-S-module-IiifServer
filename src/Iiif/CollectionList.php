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
 * @link https://iiif.io/api/presentation/3.0/#51-collection
 *
 * Same as Collection, but without main resource (used for a search result or a
 * list of manifests of any type). Properties related to the resources are not
 * available
 */
class CollectionList extends AbstractType
{
    protected $type = 'Collection';

    protected $keys = [
        '@context' => self::REQUIRED,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,

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
        // 'id' => self::REQUIRED,
        // 'type' => self::REQUIRED,
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
        'logo' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::NOT_ALLOWED,
        'supplementary' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::REQUIRED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::OPTIONAL,
    ];

    protected $behaviors = [
        'auto-advance' => self::OPTIONAL,
        'continuous' => self::OPTIONAL,
        'facing-pages' => self::NOT_ALLOWED,
        'individuals' => self::OPTIONAL,
        'multi-part' => self::OPTIONAL,
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
     * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]
     */
    protected $resources;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var \Omeka\View\Helper\Api
     */
    protected $api;

    /**
     * @var \Zend\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * @var \IiifServer\View\Helper\IiifForceBaseUrlIfRequired
     */
    protected $iiifForceBaseUrlIfRequired;

    /**
     * @var \Omeka\View\Helper\Setting
     */
    protected $setting;

    /**
     * @var \IiifServer\View\Helper\PublicResourceUrl
     */
    protected $publicResourceUrl;

    public function __construct(array $resources = null, array $options = null)
    {
        $this->resources = $resources;
        $this->options = $options;
    }

    public function setServiceLocator($services)
    {
        $this->serviceLocator = $services;
        $viewHelpers = $services->get('ViewHelperManager');
        $this->api = $viewHelpers->get('api');
        $this->urlHelper = $viewHelpers->get('url');
        $this->iiifForceBaseUrlIfRequired = $viewHelpers->get('iiifForceBaseUrlIfRequired');
        $this->setting = $viewHelpers->get('setting');
        $this->publicResourceUrl = $viewHelpers->get('publicResourceUrl');
    }

    public function getContext()
    {
        return 'http://iiif.io/api/presentation/3/context.json';
    }

    public function getId()
    {
        $identifiers = $this->buildIdentifierForList();

        $helper = $this->urlHelper;
        $url = $helper(
            'iiifserver/set',
            [],
            [
                'query' => ['id' => $identifiers],
                'force_canonical' => true,
            ]
        );
        $helper = $this->iiifForceBaseUrlIfRequired;
        return $helper($url);
    }

    /**
     * @return ValueLanguage
     */
    public function getLabel()
    {
        $values = ['none' => ['Collection list']];
        return new ValueLanguage($values);
    }

    public function getItems()
    {
        $items = [];
        foreach ($this->resources as $item) {
            $items[] = new ReferencedManifest($item);
        }
        return $items;
    }

    protected function getCleanContent()
    {
        return $this->content = array_filter($this->getContent()->getArrayCopy(), function ($v, $k) {
            if ($k === 'items') {
                return true;
            }
            if ($v instanceof \ArrayObject) {
                return (bool) $v->count();
            }
            if ($v instanceof \JsonSerializable) {
                return (bool) $v->jsonSerialize();
            }
            return !empty($v);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Helper to list all resource ids.
     *
     * @return string
     */
    protected function buildIdentifierForList()
    {
        return array_map(function ($v) {
            return $v->id();
        }, $this->resources);
    }
}
