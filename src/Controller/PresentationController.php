<?php declare(strict_types=1);

/*
 * Copyright 2015-2020 Daniel Berthereau
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

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Mvc\Exception\NotFoundException;

class PresentationController extends AbstractActionController
{
    /**
     * Forward to the 'manifest' action.
     *
     * @internal Unlike info.json, the redirect is not required.
     *
     * @see self::manifestAction()
     */
    public function indexAction()
    {
        $settings = $this->settings();
        $params = $this->params()->fromRoute();
        $params['action'] = 'manifest';
        $params += [
            'version' => $settings->get('iiifserver_manifest_default_version', '2'),
            'prefix' => $this->params('prefix') ?: $settings->get('iiifserver_identifier_prefix', ''),
        ];
        return $this->forward()->dispatch(__CLASS__, $params);
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
        $resource = $this->fetchResource('items');
        if (!$resource) {
            return $this->jsonError(new NotFoundException, \Laminas\Http\Response::STATUS_CODE_404);
        }

        $internal = (bool) $this->params()->fromQuery('internal');
        if (!$internal) {
            $externalManifest = $this->viewHelpers()->get('iiifManifestExternal')->__invoke($resource, true);
            if ($externalManifest) {
                return $this->redirect()->toUrl($externalManifest);
            }
        }

        $version = $this->requestedVersion();

        $iiifManifest = $this->viewHelpers()->get('iiifManifest');
        try {
            $manifest = $iiifManifest($resource, $version);
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }

        return $this->iiifJsonLd($manifest, $version);
    }

    public function canvasAction()
    {
        // TODO Check for clean url.
        // Not found exception is automatically thrown.
        $id = $this->params('id');
        try {
            $resource = $this->api()->read('items', $id)->getContent();
            $version = '3';
            $name = $this->params('name');
            $index = preg_replace('/[^0-9]/', '', $name);
            $resource = $this->api()->read('media', $name)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            try {
                $resource = $this->api()->read('media', $id)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_404);
            }
            $version = '2';
            $name = $this->params('name');
            $index = preg_replace('/[^0-9]/', '', $name);
        }

        // $version = $this->requestedVersion();

        $iiifCanvas = $this->viewHelpers()->get('iiifCanvas');
        try {
            $manifest = $iiifCanvas($resource, $index, $version);
        } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            return $this->jsonError($e, \Laminas\Http\Response::STATUS_CODE_400);
        }

        return $this->iiifJsonLd($manifest, $version);
    }

    public function genericAction()
    {
        $type = $this->params('type');
        if ($type === 'canvas' && $this->params('name')) {
            return $this->canvasAction();
        }
        return $this->jsonError(new NotFoundException(
            sprintf('The type "%s" is currently only managed as uri, not url', $type), // @translate
            \Laminas\Http\Response::STATUS_CODE_501
        ));
    }

    /**
     * @todo Factorize with ImageServer.
     *
     * @param string $resourceType
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation|null
     */
    protected function fetchResource($resourceType): ?AbstractResourceEntityRepresentation
    {
        $id = $this->params('id');

        $useCleanIdentifier = $this->useCleanIdentifier();
        if ($useCleanIdentifier) {
            $getResourceFromIdentifier = $this->viewHelpers()->get('getResourceFromIdentifier');
            return $getResourceFromIdentifier($id, $resourceType);
        }

        try {
            return $this->api()->read($resourceType, $id)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return null;
        }
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

    protected function useCleanIdentifier(): bool
    {
        return $this->viewHelpers()->has('getResourcesFromIdentifiers')
            && $this->settings()->get('iiifserver_identifier_clean');
    }

    protected function requestedVersion(): ?string
    {
        // Check the version from the url first.
        $version = $this->params('version');
        if ($version === '2' || $version === '3') {
            return $version;
        }

        $accept = $this->getRequest()->getHeaders()->get('Accept')->toString();
        if (strpos($accept, 'iiif.io/api/presentation/3/context.json')) {
            return '3';
        }
        if (strpos($accept, 'iiif.io/api/presentation/2/context.json')) {
            return '2';
        }
        return null;
    }

    protected function jsonError(\Exception $exception, $statusCode = 500): JsonModel
    {
        $this->getResponse()->setStatusCode($statusCode);
        return new JsonModel([
            'status' => 'error',
            'message' => $exception->getMessage(),
        ]);
    }
}
