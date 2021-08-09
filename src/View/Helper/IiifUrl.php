<?php declare(strict_types=1);

/*
 * Copyright 2015-2020  Daniel Berthereau
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

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Url;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class IiifUrl extends AbstractHelper
{
    /**
     * @var Url
     */
    protected $url;

    /**
     * @var IiifCleanIdentifiers
     */
    protected $iiifCleanIdentifiers;

    /**
     * @var IiifImageUrl
     */
    protected $iiifImageUrl;

    /**
     * @var string
     */
    protected $defaultVersion;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $forceUrlFrom;

    /**
     * @var string
     */
    protected $forceUrlTo;

    /**
     * @param Url $url
     * @param IiifCleanIdentifiers $iiifCleanIdentifiers
     * @param IiifImageUrl $iifImageUrl
     * @param string $defaultVersion
     * @param string $prefix
     * @param string $forceUrlFrom
     * @param string $forceUrlTo
     */
    public function __construct(
        Url $url,
        IiifCleanIdentifiers $iiifCleanIdentifiers,
        IiifImageUrl $iifImageUrl,
        $defaultVersion,
        $prefix,
        $forceUrlFrom,
        $forceUrlTo
    ) {
        $this->url = $url;
        $this->iiifCleanIdentifiers = $iiifCleanIdentifiers;
        $this->iiifImageUrl = $iifImageUrl;
        $this->defaultVersion = $defaultVersion;
        $this->prefix = $prefix;
        $this->forceUrlFrom = $forceUrlFrom;
        $this->forceUrlTo = $forceUrlTo;
    }

    /**
     * Return the iiif url of one or multiple resource.
     *
     * When a value is a resource, the url cannot be built, because it requires
     * the admin or the site path. In that case, the canonical iiif url is used.
     *
     * @param AbstractResourceEntityRepresentation|AbstractResourceEntityRepresentation[]|int $resource
     * @param string $route
     * @param string $version
     * @param array $params
     * @return string
     */
    public function __invoke($resource, $route = '', $version = null, array $params = []): string
    {
        $apiVersion = $version ?: $this->defaultVersion;

        if (is_array($resource)) {
            $identifiers = $this->iiifCleanIdentifiers->__invoke($resource);
            $urlIiif = $this->url->__invoke(
                'iiifserver/set',
                ['version' => $apiVersion, 'id' => implode(',', $identifiers)],
                ['force_canonical' => true]
            );
            return $this->forceToIfRequired($urlIiif);
        }

        if (is_numeric($resource)) {
            $id = $resource;
            if (isset($params['resource_name'])) {
                $resourceName = $params['resource_name'];
            } else {
                // Generally, the resource is already loaded by doctrine.
                try {
                    $resourceName = $this->view->api()->read('resources', ['id' => $id])->getContent()->resourceName();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return '';
                }
            }
        } else {
            $id = $resource->id();
            $resourceName = $resource->resourceName();
        }

        if ($resourceName === 'media') {
            return $this->iiifImageUrl->__invoke($resource, 'imageserver/info', $version, $params);
        }

        $mapRouteNames = [
            'item_sets' => 'iiifserver/collection',
            'items' => 'iiifserver/manifest',
        ];

        $params += [
            'version' => $apiVersion,
            'prefix' => $this->prefix,
            'id' => $this->iiifCleanIdentifiers->__invoke($id),
        ];

        $urlIiif = $this->url->__invoke(
            $route ?: $mapRouteNames[$resourceName],
            $params,
            ['force_canonical' => true]
        );

        return $this->forceToIfRequired($urlIiif);
    }

    protected function forceToIfRequired($absoluteUrl): string
    {
        return $this->forceUrlFrom && (strpos($absoluteUrl, $this->forceUrlFrom) === 0)
            ? substr_replace($absoluteUrl, $this->forceUrlTo, 0, strlen($this->forceUrlFrom))
            : (string) $absoluteUrl;
    }
}
