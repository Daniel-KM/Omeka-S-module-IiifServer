<?php declare(strict_types=1);

/*
 * Copyright 2015-2021 Daniel Berthereau
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

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Helper to get a IIIF Collection manifest for a dynamic list.
 */
class IiifCollectionList2 extends AbstractHelper
{
    use \IiifServer\Iiif\TraitRights;

    /**
     * Get the IIIF Collection manifest for the specified list of resources or url.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values, a standard json() without ld and attach it to
     * event in order to modify it if needed.
     * @see IiifManifest
     *
     * @param array $resources Array of resources or iiif url.
     * @param string $url The url of the list (avoid to recreate it). It is
     * required when the source contains urls.
     * @return Object|null
     */
    public function __invoke(array $resourcesOrUrls, $url = null)
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

        $translate = $this->view->plugin('translate');

        $manifest['@id'] = $url ?: $this->view->iiifUrl($resourcesOrUrls, 'iiifserver/set', '2');

        $label = $translate('Dynamic list');
        $manifest['label'] = $label;

        // TODO The dynamic list has no metadata. Use the query?

        $this->setting = $this->view->getHelperPluginManager()->get('setting');
        $license = $this->rightsResource();
        if ($license) {
            $manifest['license'] = $license;
        }

        $attribution = $this->view->setting('iiifserver_manifest_attribution_default');
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = $this->view->setting('iiifserver_manifest_logo_default');

        /*
        // Omeka api is a service, but not referenced in https://iiif.io/api/annex/services.
        $manifest['service'] = [
             '@context' => $this->view->url('api-context', [], ['force_canonical' => true]),
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
        foreach ($resourcesOrUrls as $resource) {
            if (is_object($resource)) {
                if ($resource->resourceName() == 'item_sets') {
                    $collections[] = $this->buildManifestBase($resource);
                } else {
                    $manifests[] = $this->buildManifestBase($resource);
                }
            } else {
                $protocol = substr((string) $resource, 0, 7);
                // It's not possible to know if it's a collection or a manifest.
                if ($protocol === 'https:/' || $protocol === 'http://') {
                    $manifests[] = [
                        '@id' => $resource,
                        '@type' => 'sc:Manifest',
                    ];
                }
            }
        }
        $manifest['collections'] = $collections;
        $manifest['manifests'] = $manifests;

        // Give possibility to customize the manifest.
        // TODO Manifest should be a true object, with many sub-objects.
        $resource = &$resourcesOrUrls;
        $type = 'collection_list';
        $params = compact('manifest', 'resource', 'type');
        $params = $this->view->plugin('trigger')->__invoke('iiifserver.manifest', $params, true);
        $manifest = $params['manifest'];

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        // Keep at least "manifests", even if no member.
        if (empty($manifest['collections']) && empty($manifest['manifests'])) {
            $manifest['manifests'] = [];
        }

        return $manifest;
    }

    /**
     * Prepare the base manifest of a resource.
     *
     * @todo Factorize with IiifCollection.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function buildManifestBase(AbstractResourceEntityRepresentation $resource)
    {
        $resourceName = $resource->resourceName();
        $mapRoutes = [
            'item_sets' => 'iiifserver/collection',
            'items' => 'iiifserver/manifest',
        ];
        $mapTypes = [
            'item_sets' => 'sc:Collection',
            'items' => 'sc:Manifest',
        ];
        $manifest = [];
        $manifest['@id'] = $this->view->iiifUrl($resource, $mapRoutes[$resourceName], '2');
        $manifest['@type'] = $mapTypes[$resourceName];
        $manifest['label'] = $resource->displayTitle();
        return $manifest;
    }

    /**
     * Added in order to use trait TraitRights.
     */
    protected function context()
    {
        return 'http://iiif.io/api/presentation/2/context.json';
    }
}
