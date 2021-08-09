<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Url;

/**
 * @todo FIXME Rename helper iiifMediaUrl, because it is used for media too.
 */
class IiifMediaUrl extends AbstractHelper
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
    protected $baseUrl;

    /**
     * @var string
     */
    protected $imageApiUrl;

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
     * @var string
     */
    protected $mediaIdentifier;

    /**
     * @var bool
     */
    protected $supportNonImages;

    public function __construct(
        Url $url,
        IiifCleanIdentifiers $iiifCleanIdentifiers,
        ?string $baseUrl,
        ?string $imageApiUrl,
        ?string $defaultVersion,
        array $supportedVersions,
        ?string $prefix,
        ?string $forceUrlFrom,
        ?string $forceUrlTo,
        ?string $mediaIdentifier,
        bool $supportNonImages
    ) {
        $this->url = $url;
        $this->iiifCleanIdentifiers = $iiifCleanIdentifiers;
        $this->baseUrl = $baseUrl;
        $this->imageApiUrl = $imageApiUrl;
        $this->defaultVersion = $defaultVersion;
        $this->supportedVersions = $supportedVersions;
        $this->prefix = $prefix;
        $this->forceUrlFrom = $forceUrlFrom;
        $this->forceUrlTo = $forceUrlTo;
        $this->mediaIdentifier = $mediaIdentifier;
        $this->supportNonImages = $supportNonImages;
    }

    /**
     * Return an iiif image (or any media) url.
     *
     * It takes care of external server and of the option to force base url.
     * @see \IiifServer\View\Helper\IiifUrl
     *
     * @param \Omeka\Api\Representation\MediaRepresentation|int $resource
     */
    public function __invoke($resource, ?string $route = null, ?string $version = null, array $params = []): string
    {
        if (is_numeric($resource)) {
            $id = $resource;
            try {
                $resource = $this->view->api()->read('media', ['id' => $resource])->getContent();
            } catch (\Exception $e) {
                return '';
            }
        } else {
            $id = $resource->id();
        }

        if ($this->mediaIdentifier === 'storage_id' || $this->mediaIdentifier === 'filename') {
            $identifier = $this->mediaIdentifier === 'storage_id'
                ? $resource->storageId()
                : $resource->filename();
            $identifier = $identifier ? str_replace('/', '%2F', $identifier) : $id;
        } elseif ($this->mediaIdentifier === 'media_id') {
            $identifier = $id;
        } else {
            // The identifier will be the identifier set in clean url or the
            // media id.
            $identifier = $this->iiifCleanIdentifiers->__invoke($resource);
        }

        if (!$route) {
            $route = substr((string) $resource->mediaType(), 0, 6) === 'image/'
                ? 'imageserver/info'
                : 'mediaserver/info';
        }

        $params += [
            'version' => $version ?: $this->defaultVersion,
            'prefix' => $this->prefix,
            'id' => $identifier,
        ];

        $urlIiif = (string) $this->url->__invoke($route, $params, ['force_canonical' => true]);

        if ($this->imageApiUrl
            && ($this->supportNonImages || substr($route, 0, 11) === 'imageserver')
        ) {
            $urlIiif = substr_replace($urlIiif, $this->imageApiUrl, 0, strlen($this->baseUrl));
        }

        return $this->forceUrlFrom && (strpos($urlIiif, $this->forceUrlFrom) === 0)
            ? substr_replace($urlIiif, $this->forceUrlTo, 0, strlen($this->forceUrlFrom))
            : $urlIiif;
    }
}
