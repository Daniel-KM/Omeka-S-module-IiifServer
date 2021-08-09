<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Url;

/**
 * @todo FIXME Rename helper iiifImageUrl, because it is used for media too.
 */
class IiifImageUrl extends AbstractHelper
{
    /**
     * @var \Laminas\View\Helper\Url
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
     * @var array
     */
    protected $supportedVersions;

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
     * @param string $defaultVersion
     * @param string $supportedVersions
     * @param string $prefix
     * @param string $forceUrlFrom
     * @param string $forceUrlTo
     */
    public function __construct(
        Url $url,
        IiifCleanIdentifiers $iiifCleanIdentifiers,
        $defaultVersion,
        $supportedVersions,
        $prefix,
        $forceUrlFrom,
        $forceUrlTo
    ) {
        $this->url = $url;
        $this->iiifCleanIdentifiers = $iiifCleanIdentifiers;
        $this->defaultVersion = $defaultVersion;
        $this->supportedVersions = $supportedVersions;
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
    public function __invoke($resource, $route = '', $version = null, array $params = []): string
    {
        $route = $route ?: 'imageserver/info';
        $apiVersion = $version ?: $this->defaultVersion;
        $id = is_numeric($resource) ? $resource : $resource->id();

        $params += [
            'version' => $apiVersion,
            'prefix' => $this->prefix,
            'id' => $this->iiifCleanIdentifiers->__invoke($id),
        ];
        $urlIiif = (string) $this->url->__invoke($route, $params, ['force_canonical' => true]);

        return $this->forceUrlFrom && (strpos($urlIiif, $this->forceUrlFrom) === 0)
            ? substr_replace($urlIiif, $this->forceUrlTo, 0, strlen($this->forceUrlFrom))
            : $urlIiif;
    }
}
