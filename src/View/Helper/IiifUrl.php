<?php

/*
 * Copyright 2015-2017  Daniel Berthereau
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

namespace IiifServer\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

class IiifUrl extends AbstractHelper
{
    /**
     * Return the iiif url of the resource.
     *
     * When a value is a resource, the url cannot be built, because it requires
     * the admin or the site path. In that case, the canonical iiif url is used.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $mapRouteNames = [
            'item_sets' => 'iiifserver_presentation_collection',
            'items' => 'iiifserver_presentation_item',
            'media' => 'iiifserver_image_info',
        ];
        $url = $this->view->url(
            $mapRouteNames[$resource->resourceName()],
            ['id' => $resource->id()],
            ['force_canonical' => true]
        );
        return $this->view->iiifForceBaseUrlIfRequired($url);
    }
}
