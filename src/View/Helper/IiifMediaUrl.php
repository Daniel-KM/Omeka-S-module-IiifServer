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
    protected $baseUrlPath;

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
     * @var string
     */
    protected $prefix;

    /**
     * @var bool
     */
    protected $supportNonImages;

    /**
     * @var bool
     */
    protected $apachePreencoding;

    public function __construct(
        Url $url,
        IiifCleanIdentifiers $iiifCleanIdentifiers,
        ?string $baseUrlPath,
        ?string $imageApiUrl,
        ?string $defaultVersion,
        array $supportedVersions,
        ?string $forceUrlFrom,
        ?string $forceUrlTo,
        ?string $mediaIdentifier,
        ?string $prefix,
        bool $supportNonImages,
        bool $apachePreencoding
    ) {
        $this->url = $url;
        $this->iiifCleanIdentifiers = $iiifCleanIdentifiers;
        $this->baseUrlPath = $baseUrlPath;
        $this->imageApiUrl = $imageApiUrl;
        $this->defaultVersion = $defaultVersion;
        $this->supportedVersions = $supportedVersions;
        $this->forceUrlFrom = $forceUrlFrom;
        $this->forceUrlTo = $forceUrlTo;
        $this->mediaIdentifier = $mediaIdentifier;
        $this->prefix = $prefix;
        $this->supportNonImages = $supportNonImages;
        $this->apachePreencoding = $apachePreencoding;
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

        if ($this->mediaIdentifier === 'media_id') {
            $identifier = $id;
        } elseif ($this->mediaIdentifier === 'storage_id') {
            $identifier = $resource->storageId() ?: $id;
            // Pre-encode slashes only when apache pre-encoding is enabled.
            // By default, the url helper will encode "/" to "%2F" once.
            // With apache pre-encoding, "/" is pre-encoded to "%2F", then the
            // url helper encodes to "%252F". Apache decodes once to "%2F".
            if ($this->apachePreencoding && is_string($identifier)) {
                $identifier = strtr($identifier, ['/' => '%2F']);
            }
        } elseif ($this->mediaIdentifier === 'filename') {
            $identifier = $resource->filename() ?: $id;
            if ($this->apachePreencoding && is_string($identifier)) {
                $identifier = strtr($identifier, ['/' => '%2F']);
            }
        } elseif ($this->mediaIdentifier === 'filename_image') {
            $identifier = $resource->filename();
            if ($identifier) {
                $mediaType = $resource->mediaType();
                $mainMediaType = strtok((string) $mediaType, '/');
                // Remove extension only for non-images: the extension is
                // required for Cantaloupe to find the image.
                if ($mainMediaType !== 'image') {
                    $identifier = $resource->storageId() ?: (string) $id;
                }
                // Pre-encode slashes only when apache pre-encoding is enabled.
                if ($this->apachePreencoding) {
                    $identifier = strtr($identifier, ['/' => '%2F']);
                }
            } else {
                $identifier = $id;
            }
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

        // Fix issue when the method is called from a sub-job or when there is a
        // proxy.
        if ($this->baseUrlPath && strpos($urlIiif, $this->baseUrlPath) !== 0) {
            $urlIiif = substr($urlIiif, 0, 11) === 'https:/iiif'
                ? $this->baseUrlPath . substr($urlIiif, 6)
                : $this->baseUrlPath . substr($urlIiif, 5);
        }

        if ($this->imageApiUrl
            && ($this->supportNonImages || substr($route, 0, 11) === 'imageserver')
        ) {
            $urlIiif = substr_replace($urlIiif, $this->imageApiUrl, 0, strlen($this->baseUrlPath));
        }

        return $this->forceUrlFrom && (strpos($urlIiif, $this->forceUrlFrom) === 0)
            ? substr_replace($urlIiif, $this->forceUrlTo, 0, strlen($this->forceUrlFrom))
            : $urlIiif;
    }
}
