<?php

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

use Omeka\Mvc\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;

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
        $params = $this->params()->fromRoute();
        $params['action'] = 'manifest';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function collectionAction()
    {
        // Not found exception is automatically thrown.
        $id = $this->params('id');
        $resource = $this->api()->read('item_sets', $id)->getContent();

        $version = $this->requestedVersion();

        $iiifCollection = $this->viewHelpers()->get('iiifCollection');
        $manifest = $iiifCollection($resource, $version);

        return $this->iiifJsonLd($manifest, $version);
    }

    public function listAction()
    {
        $params = $this->params();
        $identifiers = $params->fromQuery('id');

        // Compatibility with old comma-separated list.
        if (empty($identifiers)) {
            $id = $params->fromRoute('id');
            if (empty($id)) {
                throw new NotFoundException;
            }

            // For compatibility with old urls from Omeka Classic.
            $id = preg_replace('/[^0-9,]/', '', $id);

            $identifiers = array_filter(explode(',', $id));
        }

        // Extract the resources from the identifier.

        // Currently, Omeka S doesn't allow to read mixed resources.
        $conn = $this
            ->getEvent()
            ->getApplication()
            ->getServiceManager()
            ->get('Omeka\Connection');

        $map = [
            'Omeka\Entity\Item' => 'items',
            'Omeka\Entity\ItemSet' => 'item_sets',
            'Omeka\Entity\Media' => 'media',
        ];

        // TODO Use the adapter / get representation directly instead re-query result (but keep possible duplicate).
        $qb = $conn->createQueryBuilder()
            ->select('id, resource_type')
            ->from('resource', 'resource')
            ->where('resource.id IN (' . implode(',', $identifiers) . ')')
            ->andWhere("resource.resource_type IN ('Omeka\\\Entity\\\Item', 'Omeka\\\Entity\\\ItemSet', 'Omeka\\\Entity\\\Media')");
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        $resourceIds = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // The loop is done with identifiers to keep original order and possible
        // duplicates.
        $api = $this->api();
        $identifiers = array_intersect($identifiers, array_keys($resourceIds));
        $resources = [];
        foreach ($identifiers as $id) {
            // Not found exception is automatically thrown.
            $resources[] = $api->read($map[$resourceIds[$id]], $id)->getContent();
        }

        if (empty($resources)) {
            throw new NotFoundException;
        }

        $version = $this->requestedVersion();

        $iiifCollectionList = $this->viewHelpers()->get('iiifCollectionList');
        $manifest = $iiifCollectionList($resources, $version);

        return $this->iiifJsonLd($manifest, $version);
    }

    public function manifestAction()
    {
        // Not found exception is automatically thrown.
        $id = $this->params('id');
        $resource = $this->api()->read('items', $id)->getContent();

        $version = $this->requestedVersion();

        $iiifManifest = $this->viewHelpers()->get('iiifManifest');
        $manifest = $iiifManifest($resource, $version);

        return $this->iiifJsonLd($manifest, $version);
    }

    public function canvasAction()
    {
        // Not found exception is automatically thrown.
        $id = $this->params('id');
        try {
            $resource = $this->api()->read('items', $id)->getContent();
            $version = '3.0';
            $name = $this->params('name');
            $index = preg_replace('/[^0-9]/', '', $name);
            $resource = $this->api()->read('media', $name)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $resource = $this->api()->read('media', $id)->getContent();
            $version = '2.1';
            $name = $this->params('name');
            $index = preg_replace('/[^0-9]/', '', $name);
        }

        // $version = $this->requestedVersion();

        $iiifCanvas = $this->viewHelpers()->get('iiifCanvas');
        $canvas = $iiifCanvas($resource, $index, $version);

        return $this->iiifJsonLd($canvas, $version);
    }

    public function genericAction()
    {
        $type = $this->params('type');
        if ($type === 'canvas' && $this->params('name')) {
            return $this->canvasAction();
        }
        throw new NotFoundException(
            sprintf('The type "%s" is currently only managed as uri, not url', $type)
        );
    }

    protected function requestedVersion()
    {
        $accept = $this->getRequest()->getHeaders()->get('Accept')->toString();
        if (strpos($accept, 'iiif.io/api/presentation/3/context.json')) {
            return '3.0';
        }
        if (strpos($accept, 'iiif.io/api/presentation/2/context.json')) {
            return '2.1';
        }
        return null;
    }
}
