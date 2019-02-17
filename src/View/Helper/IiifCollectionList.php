<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
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
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get a IIIF Collection manifest for a dynamic list.
 */
class IiifCollectionList extends AbstractHelper
{
    /**
     * Get the IIIF Collection manifest for the specified list of resources.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values, a standard json() without ld and attach it to
     * event in order to modify it if needed.
     * @see IiifManifest
     *
     * @param array $resources Array of resources.
     * @return Object|null
     */
    public function __invoke($resources)
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

        $translate = $this->getView()->plugin('translate');

        $identifier = $this->buildIdentifierForList($resources);
        $route = 'iiifserver_presentation_collection_list';
        $url = $this->view->url(
            $route,
            ['id' => $identifier],
            ['force_canonical' => true]
        );
        $url = $this->view->iiifForceBaseUrlIfRequired($url);
        $manifest['@id'] = $url;

        $label = $translate('Dynamic list');
        $manifest['label'] = $label;

        // TODO The dynamic list has no metadata. Use the query?

        $license = $this->view->setting('iiifserver_manifest_license_default');
        $manifest['license'] = $license;

        $attribution = $this->view->setting('iiifserver_manifest_attribution_default');
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = $this->view->setting('iiifserver_manifest_logo_default');

        /*
        // Omeka api is a service, but not referenced in https://iiif.io/api/annex/services.
        $manifest['service'] = [
             '@context' => $this->view->url('api-context'),
            '@id' => $this->view->url('api', $query, [], true),
             'format' =>'application/ld+json',
             // TODO What is the profile of Omeka json-ld?
             // 'profile' => '',
         ];
        */

        /*
        // TODO Reuses  the original query to provide public and api url (search results).
        $manifest['related'] = [
            '@id' => $this->view->url('resources', $query, [], true),
            'format' => 'text/html',
        ];

        $manifest['seeAlso'] = [
            '@id' => $this->view->url('api', $query, [], true),
            'format' => 'application/ld+json',
        ];
        */

        // List of the manifest of each record. IIIF v2.0 separates collections
        // and items, so the global order is not kept for them.
        $collections = [];
        $manifests = [];
        foreach ($resources as $resource) {
            if ($resource->resourceName() == 'item_sets') {
                $collections[] = $this->buildManifestBase($resource);
            } else {
                $manifests[] = $this->buildManifestBase($resource);
            }
        }
        $manifest['collections'] = $collections;
        $manifest['manifests'] = $manifests;

        // Give possibility to customize the manifest.
        // TODO Manifest should be a true object, with many sub-objects.
        $resource = &$resources;
        $type = 'collection_list';
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
            $url = $this->view->url('iiifserver_presentation_collection', [
                'id' => $resource->id(),
            ]);

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
     * Helper to create an identifier from a list of records.
     *
     * The dynamic identifier is a flat list of ids: "5,1,2,3".
     * If there is only one id, a comma is added to avoid to have the same route
     * than the collection itself.
     * In all cases the order of records is kept.
     *
     * @todo Merge with IiifServer\View\Helper\UniversalViewer::buildIdentifierForList()
     *
     * @param array $resources
     * @return string
     */
    protected function buildIdentifierForList($resources)
    {
        $identifiers = [];
        foreach ($resources as $resource) {
            $identifiers[] = $resource->id();
        }

        $identifier = implode(',', $identifiers);

        if (count($identifiers) == 1) {
            $identifier .= ',';
        }

        return $identifier;
    }
}
