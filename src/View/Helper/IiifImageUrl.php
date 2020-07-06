<?php

namespace IiifServer\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\Url;

/**
 * @todo FIXME Rename helper iiifImageUrl, because it is used for media too.
 */
class IiifImageUrl extends AbstractHelper
{
    /**
     * @var \Zend\View\Helper\Url
     */
    protected $url;

    /**
     * @var IiifCleanIdentifiers
     */
    protected $iiifCleanIdentifiers;

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
    protected $forceFrom;

    /**
     * @var string
     */
    protected $forceTo;

    /**
     * @param Url $url
     * @param IiifCleanIdentifiers $iiifCleanIdentifiers
     * @param string $defaultVersion
     * @param string $prefix
     * @param string $forceUrlFrom
     * @param string $forceUrlTo
     */
    public function __construct(
        Url $url,
        IiifCleanIdentifiers $iiifCleanIdentifiers,
        $defaultVersion,
        $prefix,
        $forceUrlFrom,
        $forceUrlTo
    ) {
        $this->url = $url;
        $this->iiifCleanIdentifiers = $iiifCleanIdentifiers;
        $this->defaultVersion = $defaultVersion;
        $this->prefix = $prefix;
        $this->forceUrlFrom = $forceUrlFrom;
        $this->forceUrlTo = $forceUrlTo;
    }

    /**
     * Return an iiif image url.
     *
     * It takes care of external server and of the option to force base url.
     * @see \IiifServer\View\Helper\IiifUrl
     *
     * @param \Omeka\Api\Representation\MediaRepresentation|int $resource
     * @param string $route
     * @param string $version
     * @param array $params
     * @return string
     */
    public function __invoke($resource, $route = '', $version = null, array $params = [])
    {
        $urlHelper = $this->url;
        $iiifCleanIdentifiersHelper = $this->iiifCleanIdentifiers;

        $route = $route ?: 'imageserver/info';
        $apiVersion = $version ?: $this->defaultVersion;
        $id = is_numeric($resource) ? $resource : $resource->id();

        $params += [
            'version' => $apiVersion,
            'prefix' => $this->prefix,
            'id' => $iiifCleanIdentifiersHelper($id),
        ];
        $urlIiif = $urlHelper($route, $params, ['force_canonical' => true]);

        return $this->forceFrom && (strpos($urlIiif, $this->forceFrom) === 0)
            ? substr_replace($urlIiif, $this->forceTo, 0, strlen($this->forceFrom))
            : $urlIiif;
    }
}
