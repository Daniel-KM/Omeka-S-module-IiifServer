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

use Omeka\Api\Representation\ItemSetRepresentation;

/**
 * @link https://iiif.io/api/presentation/3.0/#51-collection
 */
class Collection extends AbstractResourceType
{
    use TraitDescriptive;
    use TraitLinking;
    use TraitThumbnail;

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
        // Temporal behaviors.
        'auto-advance' => self::OPTIONAL,
        'no-auto-advance' => self::OPTIONAL,
        'repeat' => self::OPTIONAL,
        'no-repeat' => self::OPTIONAL,
        // Layout behaviors.
        'unordered' => self::OPTIONAL,
        'individuals' => self::OPTIONAL,
        'continuous' => self::OPTIONAL,
        'paged' => self::OPTIONAL,
        'facing-pages' => self::NOT_ALLOWED,
        'non-paged' => self::NOT_ALLOWED,
        // Collection behaviors.
        'multi-part' => self::OPTIONAL,
        'together' => self::OPTIONAL,
        // Range behaviors.
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'no-nav' => self::NOT_ALLOWED,
        // Miscellaneous behaviors.
        'hidden' => self::NOT_ALLOWED,
    ];

    public function id(): ?string
    {
        // TODO Check if the id is the same for items (see manifest for 2.1)
        /** @see \Omeka\Api\Representation\ItemSetRepresentation */
        return $this->iiifUrl->__invoke($this->resource, 'iiifserver/collection', '3');
    }

    public function items(): array
    {
        if ($this->resource instanceof ItemSetRepresentation) {
            $items = [];
            foreach ($this->api->search('items', ['item_set_id' => $this->resource->id()])->getContent() as $item) {
                $referenced = new ReferencedManifest();
                $referenced->setResource($item);
                $items[] = $referenced;
            }
            return $items;
        }

        return $this->externalManifestsOfResource();
    }

    /**
     * @todo Merge with helper IiifManifestExternal?
     */
    protected function externalManifestsOfResource(): array
    {
        $manifestProperty = $this->settings->get('iiifserver_manifest_external_property');
        if (empty($manifestProperty)) {
            return [];
        }

        $result = [];

        // Manage the case where the url is saved as an uri or a text and the
        // case where the property contains other values that are not url.
        foreach ($this->resource->value($manifestProperty, ['all' => true]) as $value) {
            if ($value->type() === 'uri') {
                $result[] = [
                    'id' => $value->uri(),
                    'type' => 'Manifest',
                    'label' => ValueLanguage::output((string) $value->value(), false, '[Untitled]'),
                ];
            } else {
                $urlManifest = (string) $value;
                if (filter_var($urlManifest, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                    $result[] = [
                        'id' => $urlManifest,
                        'type' => 'Manifest',
                        'label' => ValueLanguage::output('[Untitled]', false),
                    ];
                }
            }
        }

        return $result;
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
