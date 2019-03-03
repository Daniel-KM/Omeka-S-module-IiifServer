<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
 * Copyright 2016-2017 BibLibre
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

namespace IiifServer\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get a IIIF Collection manifest for an item set
 */
class IiifCollection extends AbstractHelper
{
    /**
     * Get the IIIF Collection manifest for the specified item set.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values, a standard json() without ld and attach it to
     * event in order to modify it if needed.
     * @see IiifManifest
     *
     * @param ItemSetRepresentation $itemSet Item set
     * @return Object|null
     */
    public function __invoke(ItemSetRepresentation $itemSet)
    {
        // Prepare values needed for the manifest. Empty values will be removed.
        // Some are required.
        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => '',
            '@type' => 'sc:Collection',
            'label' => '',
            'description' => '',
            'thumbnail' => '',
            'license' => '',
            'attribution' => '',
            // A logo to add at the end of the information panel.
            'logo' => '',
            'service' => '',
            // For example the web page of the item.
            'related' => '',
            // Other formats of the same data.
            'seeAlso' => '',
            'within' => '',
            'metadata' => [],
            'collections' => [],
            'manifests' => [],
        ];

        $manifest = array_merge($manifest, $this->buildManifestBase($itemSet));

        $metadata = $this->iiifMetadata($itemSet);
        $manifest['metadata'] = $metadata;

        $descriptionProperty = $this->view->setting('iiifserver_manifest_description_property');
        if ($descriptionProperty) {
            $description = strip_tags($itemSet->value($descriptionProperty, ['type' => 'literal']));
        }
        $manifest['description'] = $description;

        $licenseProperty = $this->view->setting('iiifserver_license_property');
        if ($licenseProperty) {
            $license = $itemSet->value($licenseProperty);
        }
        if (empty($license)) {
            $license = $this->view->setting('iiifserver_manifest_license_default');
        }
        $manifest['license'] = $license;

        $attributionProperty = $this->view->setting('iiifserver_manifest_attribution_property');
        if ($attributionProperty) {
            $attribution = strip_tags($itemSet->value($attributionProperty, ['type' => 'literal']));
        }
        if (empty($attribution)) {
            $attribution = $this->view->setting('iiifserver_manifest_attribution_default');
        }
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = $this->view->setting('iiifserver_manifest_logo_default');

        // TODO Use resource thumbnail (> Omeka 1.3).
        // $manifest['thumbnail'] = $thumbnail;
        // $manifest['service'] = $service;

        /*
         // Omeka api is a service, but not referenced in https://iiif.io/api/annex/services.
         $manifest['service'] = [
             '@context' => $this->view->url('api-context'),
             '@id' => $itemSet->apiUrl(),
             'format' =>'application/ld+json',
             // TODO What is the profile of Omeka json-ld?
             // 'profile' => '',
         ];
         $manifest['service'] = [
             '@context' =>'http://example.org/ns/jsonld/context.json',
             '@id' => 'http://example.org/service/example',
             'profile' => 'http://example.org/docs/example-service.html',
         ];
         */

        $manifest['related'] = [
            '@id' => $this->view->publicResourceUrl($itemSet, true),
            'format' => 'text/html',
        ];

        $manifest['seeAlso'] = [
            '@id' => $itemSet->apiUrl(),
            'format' => 'application/ld+json',
            // TODO What is the profile of Omeka json-ld?
            // 'profile' => '',
        ];

        // TODO Use within with collection tree.
        // $manifest['within'] = $within;

        // List of manifests inside the item set.
        $manifests = [];
        $response = $this->view->api()->search('items', ['item_set_id' => $itemSet->id()]);
        $items = $response->getContent();
        foreach ($items as $item) {
            $manifests[] = $this->buildManifestBase($item);
        }
        $manifest['manifests'] = $manifests;

        // Give possibility to customize the manifest.
        // TODO Manifest should be a true object, with many sub-objects.
        $resource = $itemSet;
        $type = 'collection';
        $triggerHelper = $this->view->plugin('trigger');
        $params = compact('manifest', 'resource', 'type');
        $params = $triggerHelper('iiifserver.manifest', $params, true);
        $manifest = $params['manifest'];

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        // Keep at least "manifests", even if no member.
        if (empty($manifest['collections']) && empty($manifest['manifests'])) {
            $manifest['manifests'] = [];
        }

        $manifest = (object) $manifest;
        return $manifest;
    }

    protected function buildManifestBase(AbstractResourceEntityRepresentation $resource)
    {
        $resourceName = $resource->resourceName();
        $manifest = [];

        if ($resourceName == 'item_sets') {
            $url = $this->view->url(
                'iiifserver_presentation_collection',
                ['id' => $resource->id()],
                ['force_canonical' => true]
            );

            $type = 'sc:Collection';
        } else {
            $url = $this->view->url(
                'iiifserver_presentation_item',
                ['id' => $resource->id()],
                ['force_canonical' => true]
            );

            $type = 'sc:Manifest';
        }

        $url = $this->view->iiifForceBaseUrlIfRequired($url);
        $manifest['@id'] = $url;

        $manifest['@type'] = $type;

        $manifest['label'] = $resource->displayTitle();

        return $manifest;
    }

    /**
     * Prepare the metadata of a resource.
     *
     * @todo Factorize with IiifManifest.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function iiifMetadata(AbstractResourceEntityRepresentation $resource)
    {
        $metadata = [];
        $properties = $this->view->setting('iiifserver_manifest_properties_collection');
        $values = $properties ? array_intersect_key($resource->values(), array_flip($properties)) : $resource->values();
        foreach ($values as $propertyData) {
            $valueMetadata = [];
            $valueMetadata['label'] = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $valueValues = array_filter(array_map(function ($v) {
                return $v->type() === 'resource'
                    ? $this->view->iiifUrl($v->valueResource())
                    : (string) $v;
            }, $propertyData['values']), 'strlen');
                $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
                $metadata[] = (object) $valueMetadata;
        }
        return $metadata;
    }
}
