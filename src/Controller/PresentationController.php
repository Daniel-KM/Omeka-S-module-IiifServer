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

namespace UniversalViewer\Controller;

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
        $this->forward('manifest');
    }

    public function manifestAction()
    {
        $id = $this->params('id');
        if (empty($id)) {
            throw new NotFoundException;
        }

        // Map iiif resources with Omeka Classic and Omeka S records.
        $matchingResources = array(
            'item' => 'items',
            'items' => 'items',
            'item-set' => 'item_sets',
            'item-sets' => 'item_sets',
            'item_set' => 'item_sets',
            'item_sets' => 'item_sets',
            'collection' => 'item_sets',
            'collections' => 'item_sets',
        );
        $resourceName = $this->params('resourcename');
        if (!isset($matchingResources[$resourceName])) {
            throw new NotFoundException;
        }
        $resourceName = $matchingResources[$resourceName];

        if ($resource->resourceName() == 'item_sets') {
            return $this->collectionAction();
        } else {
            return $this->itemAction();
        }
    }

    public function collectionAction()
    {
        $id = $this->params('id');
        if (empty($id)) {
            throw new NotFoundException;
        }

        $response = $this->api()->read('item_sets', $id);
        $resource = $response->getContent();
        if (empty($resource)) {
            throw new NotFoundException;
        }

        $iiifCollection = $this->viewHelpers()->get('iiifCollection');
        $manifest = $iiifCollection($resource, false);

        return $this->jsonLd($manifest);
    }

    public function listAction()
    {
        $id = $this->params('id');
        if (empty($id)) {
            throw new NotFoundException;
        }

        // For compatibility with old urls from Omeka Classic.
        $id = preg_replace('/[^0-9,]/', '', $id);

        $identifiers = array_filter(explode(',', $id));

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

        $qb = $conn->createQueryBuilder()
            ->select('id, resource_type')
            ->from('resource', 'resource')
            ->where('resource.id IN (' . implode(',', $identifiers) . ')')
            ->andWhere("resource.resource_type IN ('Omeka\\\Entity\\\Item', 'Omeka\\\Entity\\\ItemSet', 'Omeka\\\Entity\\\Media')");
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        $resourceIds = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // The loop is done with identifiers to keep original order and possible
        // duplicates.
        $identifiers = array_intersect($identifiers, array_keys($resourceIds));
        foreach ($identifiers as $id) {
            $response = $this->api()->read($map[$resourceIds[$id]], $id);
            $resources[] = $response->getContent();
        }

        if (empty($resources)) {
            throw new NotFoundException;
        }

        $iiifCollectionList = $this->viewHelpers()->get('iiifCollectionList');
        $manifest = $iiifCollectionList($resources);

        return $this->jsonLd($manifest);
    }

    public function itemAction()
    {
        $id = $this->params('id');
        if (empty($id)) {
            throw new NotFoundException;
        }

        $response = $this->api()->read('items', $id);
        $resource = $response->getContent();
        if (empty($resource)) {
            throw new NotFoundException;
        }

        $iiifManifest = $this->viewHelpers()->get('iiifManifest');
        $manifest = $iiifManifest($resource, false);

        return $this->jsonLd($manifest);
    }
}
