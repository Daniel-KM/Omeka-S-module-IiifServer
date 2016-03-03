<?php
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
        $serviceLocator = $this->view->getHelperPluginManager()->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $description = strip_tags($itemSet->value('dcterms:description', array('type' => 'literal')));
        $licence = $settings->get('universalviewer_licence');
        $attribution = $settings->get('universalviewer_attribution');

        $metadata = array();
        foreach ($itemSet->values() as $name => $term) {
            $metadata[] = (object) array(
                'label' => $term,
                'value' => count($term['values']) > 1
                   ? $term['values']
                   :  reset($term['values']),
            );
        }

        // List of manifests inside the item set.
        $manifests = array();
        $api = $serviceLocator->get('Omeka\ApiManager');
        $response = $api->search('items', array('item_set_id' => $itemSet->id()));
        $items = $response->getContent();
        foreach ($items as $item) {
            $manifests[] = $this->_buildManifestBase($item);
        }

        // Prepare manifest.
        $manifest = array();
        $manifest['@context'] = 'http://iiif.io/api/presentation/2/context.json';
        $manifest = array_merge($manifest, $this->_buildManifestBase($itemSet, false));
        if ($metadata) {
            $manifest['metadata'] = $metadata;
        }
        if ($description) {
           $manifest['description'] = $description;
        }
        if ($licence) {
            $manifest['license'] = $licence;
        }
        if ($attribution) {
            $manifest['attribution'] = $attribution;
        }
        // $manifest['service'] = $service;
        // $manifest['seeAlso'] = $seeAlso;
        // $manifest['within'] = $within;

        if ($manifests) {
            $manifest['manifests'] = $manifests;
        }
        $manifest = (object) $manifest;

        return $manifest;
    }

    protected function _buildManifestBase(AbstractResourceEntityRepresentation $resource, $asObject = true)
    {
        $resourceName = $resource->resourceName();
        $manifest = array();
        $manifest['@id'] = $this->view->url('universalviewer_presentation_manifest', array(
            'recordtype' => $resourceName,
            'id' => $resource->id(),
        ));
        $manifest['@type'] = $resourceName == 'item_sets' ? 'sc:Collection' : 'sc:Manifest';
        $manifest['label'] = strip_tags($resource->value('dcterms:title', array('type' => 'literal'))) ?: $this->view->translate('[Untitled]');

        return $asObject
            ? (object) $manifest
            : $manifest;
    }
}
