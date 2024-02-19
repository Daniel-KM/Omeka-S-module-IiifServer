<?php declare(strict_types=1);

/*
 * Copyright 2015-2023 Daniel Berthereau
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

namespace IiifServer\Controller;

use Common\Stdlib\PsrMessage;
use Laminas\Mvc\I18n\Translator;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Mvc\Exception\NotFoundException;

class PresentationController extends AbstractActionController
{
    use IiifServerControllerTrait;

    public function __construct(
        Translator $translator
    ) {
        $this->translator = $translator;
    }

    public function indexAction()
    {
        return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
    }

    public function collectionAction()
    {
        // A collection can be an item set or an item with external manifests.
        $resource = $this->fetchResource('resources');
        if (!$resource) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $version = $this->requestedVersion();

        $iiifCollection = $this->viewHelpers()->get('iiifCollection');
        try {
            $manifest = $iiifCollection($resource, $version);
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }

        return $this->iiifJsonLd($manifest, $version);
    }

    public function listAction()
    {
        // TODO Set the resource type to fetch resources from identifiers?
        $resources = $this->fetchResourcesAndIiifUrls();
        if (!count($resources)) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $query = $this->params()->fromQuery();
        $version = $this->requestedVersion();
        $currentUrl = $this->url()->fromRoute(null, [], ['query' => $query, 'force_canonical' => true], true);

        $iiifCollectionList = $this->viewHelpers()->get('iiifCollectionList');
        try {
            $manifest = $iiifCollectionList($resources, $version, $currentUrl);
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }

        return $this->iiifJsonLd($manifest, $version);
    }

    public function manifestAction()
    {
        $params = $this->params()->fromRoute();

        // It can be a forward from the module Image Server.
        $resource = $params['resource'] ?? $this->fetchResource('items');
        if (!$resource) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $viewHelpers = $this->viewHelpers();

        $internal = (bool) $this->params()->fromQuery('internal');
        if (!$internal) {
            $externalManifest = $viewHelpers->get('iiifManifestExternal')->__invoke($resource, true);
            if ($externalManifest) {
                return $this->redirect()->toUrl($externalManifest);
            }
        }

        // Version may be 2 or 3.
        $version = $this->requestedVersion();

        $manifest = null;
        $toCache = false;

        $useCache = (bool) $this->settings()->get('iiifserver_manifest_cache_derivativemedia', false);
        if ($useCache && $viewHelpers->has('derivativeList')) {
            $type = 'iiif-' . (int) $version;
            $derivative = $viewHelpers->get('derivativeList')->__invoke($resource, ['type' => $type]);
            if ($derivative) {
                $config = $resource->getServiceLocator()->get('Config');
                $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
                $filepath = $basePath . '/' . $derivative[$type]['file'];
                if ($derivative[$type]['ready']) {
                    $manifest = file_get_contents($filepath);
                    $manifest = json_decode($manifest, true);
                    return $this->iiifJsonLd($manifest, $version);
                }
                if (!$derivative[$type]['in_progress']) {
                    $toCache = true;
                }
            }
            // Else derivative is not enabled in module DerivativeMedia.
        }

        $iiifManifest = $this->viewHelpers()->get('iiifManifest');
        try {
            $manifest = $iiifManifest($resource, $version);
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }

        if ($toCache) {
            // Ensure dirpath. Don't keep issue, it's only cache.
            if (!file_exists(dirname($filepath))) {
                @mkdir(dirname($filepath), 0775, true);
            }
            @file_put_contents($filepath, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $this->iiifJsonLd($manifest, $version);
    }

    public function genericAction()
    {
        $type = $this->params('type');
        $name = $this->params('name');
        if ($type === 'canvas' && $name) {
            return $this->canvasAction();
        } elseif ($type === 'annotation-page' && $name && $this->params('subtype')) {
            return $this->annotationPageLineAction();
        } elseif ($type === 'annotation-list' && $name) {
            return $this->annotationListAction();
        }
        return $this->jsonError(new PsrMessage(
            'The type "{type}" is currently only managed as uri, not url', // @translate
            ['type' => $type]
        ), \Laminas\Http\Response::STATUS_CODE_501);
    }

    protected function canvasAction()
    {
        // The canvases are no more media ids in this implementation of iiif v3,
        // but the media position in a item like in iiif v2, or any name via the
        // structure. It may be used in Iiif Search too.
        // Note: the position is not the item's one, non-iiif media are skipped.

        // A canvas name can be hard coded in a table of contents, for example "cover".

        $name = $this->params('name');
        if (!$name) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        // When the id is a clean url identifier, the id is already extracted.
        $id = $this->params('id');
        try {
            $item = $this->api()->read('items', ['id' => $id])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $viewHelpers = $this->viewHelpers();

        $version = $this->requestedVersion();

        // In the manifest, the position is not the one set by the item.
        // The simplest way to check it is to recreate the manifest.
        // Furthermore, it allows to manage alphanumeric canvas names.

        // Normally, the identifier is the same in version 2 and version 3, so
        // use the manifest version 3, that can output the original resource.

        /** @var \IiifServer\Iiif\Manifest $manifest */
        try {
            $manifest = $viewHelpers->get('iiifManifest')->__invoke($item, '3');
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }
        $found = false;
        // In iiif, here, items means canvases.
        foreach ($manifest->items() as $canvas) {
            if ($name === basename($canvas->id())) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        if ($version === '2') {
            $iiifCanvas = $viewHelpers->get('iiifCanvas2');
            try {
                $canvas = $iiifCanvas($canvas->resource(), $name);
            } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
                return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
            }
        }

        return $this->iiifJsonLd($canvas, $version);
    }

    protected function annotationPageLineAction()
    {
        // Unlike canvas, the name is the main media id.

        $name = $this->params('name');
        if (!$name) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $api = $this->api();

        // When the id is a clean url identifier, the id is already extracted.
        $id = $this->params('id');
        try {
            $api->read('items', ['id' => $id])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_404);
        }

        try {
            $media = $api->read('media', ['item' => $id, 'id' => $name])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $viewHelpers = $this->viewHelpers();
        $iiifAnnotationPageLine = $viewHelpers->get('iiifAnnotationPageLine');

        $version = $this->requestedVersion();

        try {
            $annotationPageLine = $iiifAnnotationPageLine($media, null, $version);
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }

        return $this->iiifJsonLd($annotationPageLine, $version);
    }

    /**
     * Get the annotations list from module Annotate/Cartography for a media.
     *
     * @todo Use url like /item-id/canvas/canvas-id/annotation-list ?
     */
    protected function annotationListAction()
    {
        // Unlike canvas, the name is the main media id.

        $name = $this->params('name');
        if (!$name) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $api = $this->api();

        // When the id is a clean url identifier, the id is already extracted.
        $id = $this->params('id');
        try {
            $api->read('items', ['id' => $id])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_404);
        }

        try {
            $media = $api->read('media', ['item' => $id, 'id' => $name])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $viewHelpers = $this->viewHelpers();
        $iiifAnnotationList = $viewHelpers->get('iiifAnnotationList');

        $version = $this->requestedVersion();

        try {
            $annotationPageLine = $iiifAnnotationList($media, null, $version);
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }

        return $this->iiifJsonLd($annotationPageLine, $version);
    }

    /**
     * @param string $resourceType
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]
     */
    protected function fetchResourcesAndIiifUrls($resourceType = null): array
    {
        $params = $this->params();
        $identifiers = $params->fromQuery('id');

        // Compatibility with comma-separated list (/collection/i1,m2 or /set/1,2).
        if (empty($identifiers)) {
            $id = $params->fromRoute('id');
            if (empty($id)) {
                return [];
            }
            $identifiers = array_filter(explode(',', $id));
        } elseif (is_string($identifiers)) {
            $identifiers = array_filter(explode(',', $identifiers));
        }

        $identifiers = array_filter(array_map('trim', $identifiers));
        if (empty($identifiers)) {
            return [];
        }

        // Remove all ids that are urls, because they are already iiif urls.
        $urlIdentifiers = [];
        $nonUrlIdentifiers = [];
        foreach ($identifiers as $identifier) {
            $protocol = substr($identifier, 0, 7);
            if ($protocol === 'https:/' || $protocol === 'http://') {
                $urlIdentifiers[] = $identifier;
            } else {
                $nonUrlIdentifiers[] = $identifier;
            }
        }

        // TODO Manage media naming for iiif lists.

        // Extract the resources from the identifier.
        $useCleanIdentifier = $this->useCleanIdentifier();
        if ($useCleanIdentifier) {
            $getResourcesFromIdentifiers = $this->viewHelpers()->get('getResourcesFromIdentifiers');
            $resources = $nonUrlIdentifiers
                ? $getResourcesFromIdentifiers($nonUrlIdentifiers, $resourceType)
                : [];
            // A loop is done with identifiers to keep original order and
            // possible duplicates.
            $result = [];
            foreach ($identifiers as $identifier) {
                $protocol = substr($identifier, 0, 7);
                if ($protocol === 'https:/' || $protocol === 'http://') {
                    $result[] = $identifier;
                } elseif (isset($resources[$identifier])) {
                    $result[] = $resources[$identifier];
                }
            }
            return $result;
        }

        $ids = array_filter(array_map('intval', $identifiers));
        if (!$ids) {
            return $urlIdentifiers;
        }

        // Currently, Omeka S doesn't allow to read mixed resources.
        $services = $this->getEvent()->getApplication()->getServiceManager();
        $entityManager = $services->get('Omeka\EntityManager');
        $resources = $entityManager->getRepository(\Omeka\Entity\Resource::class)
            ->findBy(['id' => $ids]);
        // A loop is done with identifiers to keep original order and possible
        // duplicates.
        $adapter = $services->get('Omeka\ApiAdapterManager')->get('resources');
        $resourceList = [];
        foreach ($resources as $resource) {
            $resourceList[$resource->getId()] = $adapter->getRepresentation($resource);
        }
        $result = [];
        foreach ($identifiers as $identifier) {
            $protocol = substr($identifier, 0, 7);
            if ($protocol === 'https:/' || $protocol === 'http://') {
                $result[] = $identifier;
            } elseif (isset($resourceList[$identifier])) {
                $result[] = $resourceList[$identifier];
            }
        }
        return $result;
    }
}
