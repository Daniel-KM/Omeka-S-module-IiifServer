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

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;

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

    protected $propertyRequirements = [
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
        'services' => self::OPTIONAL,

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
     * @var \IiifServer\View\Helper\IiifUrl
     */
    protected $iiifUrl;

    /**
     * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]|string[]
     */
    protected $resources;

    public function setServiceLocator(ServiceLocatorInterface $services): self
    {
        $this->services = $services;
        $viewHelpers = $this->services->get('ViewHelperManager');
        $this->iiifUrl = $viewHelpers->get('iiifUrl');
        return $this;
    }

    public function setResources(array $resources): self
    {
        $this->resources = $resources;
        return $this;
    }

    public function context(): ?string
    {
        return 'http://iiif.io/api/presentation/3/context.json';
    }

    public function id(): ?string
    {
        return $this->options['iiif_url'] ?? $this->iiifUrl->__invoke($this->resources, 'iiifserver/set', '3');
    }

    public function label(): ?array
    {
        $values = ['none' => ['Collection list']];
        return ValueLanguage::output($values);
    }

    public function items(): array
    {
        $items = [];
        foreach ($this->resources as $resource) {
            if (is_object($resource)) {
                if ($resource instanceof ItemRepresentation) {
                    $referenced = new ReferencedManifest();
                    $referenced->setResource($resource);
                    $items[] = $referenced;
                } elseif ($resource instanceof ItemSetRepresentation) {
                    $referenced = new ReferencedCollection();
                    $referenced->setResource($resource);
                    $items[] = $referenced;
                }
            } else {
                $protocol = substr((string) $resource, 0, 7);
                // It's not possible to know if it's a collection or a manifest.
                if ($protocol === 'https:/' || $protocol === 'http://') {
                    $items[] = [
                        'id' => $resource,
                        'type' => 'Manifest',
                        'label' => ValueLanguage::output('[Untitled]', false),
                    ];
                }
            }
        }
        return $items;
    }

    /**
     * For collection items, that may be empty after a search.
     *
     * {@inheritDoc}
     * @see \IiifServer\Iiif\AbstractType::filterContentFilled()
     */
    protected function filterContentFilled($v, $k): bool
    {
        // Any array, string, numeric, boolean, object, etc. is filled.
        return $k === 'items'
            || $v === '0'
            || is_bool($v)
            || !empty($v);
    }
}
