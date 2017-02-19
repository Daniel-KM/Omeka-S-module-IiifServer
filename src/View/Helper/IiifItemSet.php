<?php

/*
 * Copyright 2015  Daniel Berthereau
 * Copyright 2016  BibLibre
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace UniversalViewer\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get a IIIF manifest for an item set
 */
class IiifItemSet extends AbstractHelper
{
    /**
     * Get the IIIF manifest for the specified item set.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values, a standard json() without ld and attach it to
     * event in order to modify it if needed.
     * @see IiifManifest
     *
     * @param ItemSetRepresentation $itemSet Item set
     * @param boolean $asJson Return manifest as object or as a json string.
     * @return Object|string|null. The object or the json string corresponding to the
     * manifest.
     */
    public function __invoke(ItemSetRepresentation $itemSet, $asJson = true)
    {
        $result = $this->_buildManifestItemSet($itemSet);

        if ($asJson) {
            return version_compare(phpversion(), '5.4.0', '<')
                ? json_encode($result)
                : json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        // Return as array
        return $result;
    }

    /**
     * Get the IIIF manifest for the specified item set.
     *
     * @param ItemSetRepresentation $itemSet
     * @return Object|null. The object corresponding to the collection.
     */
    protected function _buildManifestItemSet(ItemSetRepresentation $itemSet)
    {
        // Prepare values needed for the manifest. Empty values will be removed.
        // Some are required.
        $manifest = array(
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
            'metadata' => array(),
            'collections' => array(),
            'manifests' => array(),
        );

        $manifest = array_merge($manifest, $this->_buildManifestBase($itemSet, false));

        // Prepare the metadata of the record.
        // TODO Manage filter and escape?
        $metadata = array();
        foreach ($itemSet->values() as $name => $term) {
            $value = reset($term['values']);
            $metadata[] = (object) [
                'label' => $value->property()->localName(),
                'value' => (string) $value,
            ];
        }
        $manifest['metadata'] = $metadata;

        $descriptionProperty = $this->view->setting('universalviewer_manifest_description_property');
        if ($descriptionProperty) {
            $description = strip_tags($itemSet->value($descriptionProperty, array('type' => 'literal')));
        }
        $manifest['description'] = $description;

        $licenseProperty = $this->view->setting('universalviewer_license_property');
        if ($licenseProperty) {
            $license = $itemSet->value($licenseProperty);
        }
        if (empty($license)) {
            $license = $this->view->setting('universalviewer_manifest_license_default');
        }
        $manifest['license'] = $license;

        $attributionProperty = $this->view->setting('universalviewer_attribution_property');
        if ($attributionProperty) {
            $attribution = strip_tags($itemSet->value($attributionProperty, array('type' => 'literal')));
        }
        if (empty($attribution)) {
            $attribution = $this->view->setting('universalviewer_manifest_attribution_default');
        }
        $manifest['attribution'] = $attribution;

        // $manifest['thumbnail'] = $thumbnail;
        // $manifest['service'] = $service;
        // TODO To parameter or to extract from metadata (Dublin Core Relation).
        // $manifest['seeAlso'] = $seeAlso;
        // TODO Use within with collection tree.
        // $manifest['within'] = $within;

        // List of manifests inside the item set.
        $manifests = array();
        $response = $this->view->api()->search('items', array('item_set_id' => $itemSet->id()));
        $items = $response->getContent();
        foreach ($items as $item) {
            $manifests[] = $this->_buildManifestBase($item);
        }
        $manifest['manifests'] = $manifests;

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        $manifest = (object) $manifest;
        return $manifest;
    }

    protected function _buildManifestBase(AbstractResourceEntityRepresentation $resource, $asObject = true)
    {
        $resourceName = $resource->resourceName();
        $manifest = array();

        $url = $this->view->url('universalviewer_presentation_manifest', array(
            'recordtype' => $resourceName,
            'id' => $resource->id(),
        ));
        $url = $this->view->uvForceHttpsIfRequired($url);
        $manifest['@id'] = $url;

        $manifest['@type'] = $resourceName == 'item_sets' ? 'sc:Collection' : 'sc:Manifest';

        $manifest['label'] = $resource->displayTitle();

        return $asObject
            ? (object) $manifest
            : $manifest;
    }
}
