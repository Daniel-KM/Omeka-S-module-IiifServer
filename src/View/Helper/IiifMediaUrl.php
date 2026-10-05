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
     * @var bool
     */
    protected $versionAppend;

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
    protected $encodeSlash;

    public function __construct(
        Url $url,
        IiifCleanIdentifiers $iiifCleanIdentifiers,
        ?string $baseUrlPath,
        ?string $imageApiUrl,
        ?string $defaultVersion,
        bool $versionAppend,
        array $supportedVersions,
        ?string $forceUrlFrom,
        ?string $forceUrlTo,
        ?string $mediaIdentifier,
        ?string $prefix,
        bool $supportNonImages,
        bool $encodeSlash = false
    ) {
        $this->url = $url;
        $this->iiifCleanIdentifiers = $iiifCleanIdentifiers;
        $this->baseUrlPath = $baseUrlPath;
        $this->imageApiUrl = $imageApiUrl;
        $this->defaultVersion = $defaultVersion;
        $this->versionAppend = $versionAppend;
        $this->supportedVersions = $supportedVersions;
        $this->forceUrlFrom = $forceUrlFrom;
        $this->forceUrlTo = $forceUrlTo;
        $this->mediaIdentifier = $mediaIdentifier;
        $this->prefix = $prefix;
        $this->supportNonImages = $supportNonImages;
        $this->encodeSlash = $encodeSlash;
    }

    /**
     * Return an iiif image (or any media) url.
     *
     * It takes care of external server and of the option to force base url.
     * @see \IiifServer\View\Helper\IiifUrl
     *
     * @param \Omeka\Api\Representation\MediaRepresentation|int $resource
     * @param array $params Route params, and "page" for a page of a pdf served
     *   by an image server like Cantaloupe.
     */
    public function __invoke($resource, ?string $route = null, ?string $version = null, array $params = []): string
    {
        if (is_numeric($resource)) {
            $id = $resource;
            try {
                $resource = $this->view->api()->read('media', ['id' => $resource])->getContent();
            } catch (\Throwable $e) {
                return '';
            }
        } else {
            $id = $resource->id();
        }

        if ($this->mediaIdentifier === 'media_id') {
            $identifier = $id;
        } elseif ($this->mediaIdentifier === 'storage_id') {
            $identifier = $resource->storageId() ?: $id;
        } elseif ($this->mediaIdentifier === 'filename') {
            $identifier = $resource->filename() ?: $id;
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
            } else {
                $identifier = $id;
            }
        } else {
            // The identifier will be the identifier set in clean url or the
            // media id.
            $identifier = $this->iiifCleanIdentifiers->__invoke($resource);
        }

        // A page of a pdf is an image for an image server like Cantaloupe, with
        // the file name and the page as meta-identifier ("file.pdf;2").
        $page = (int) ($params['page'] ?? 0);
        unset($params['page']);
        if ($page) {
            if (in_array($this->mediaIdentifier, ['filename', 'filename_image'], true) && $resource->filename()) {
                $identifier = $resource->filename();
            }
            $identifier .= ';' . $page;
            $route = $route ?: 'imageserver/id';
        }

        if (!$route) {
            $route = substr((string) $resource->mediaType(), 0, 6) === 'image/'
                ? 'imageserver/info'
                : 'mediaserver/info';
        }

        // Pre-encode "/" in the identifier so the slash survives Segmentv route
        // assembly. Laminas Url emits "/" verbatim for constrained parameters
        // even when the constraint forbids it ([^\/]+), so we pre-encode to
        // "%2F"; Laminas then rawurlencodes the "%" to "%252F", which we
        // collapse back to "%2F" right before returning. When encodeSlash is
        // off, the final block below restores literal "/" (legacy fallback for
        // servers that decode encoded slashes).
        $idForUrl = (is_string($identifier) && $this->encodeSlash && strpos($identifier, '/') !== false)
            ? strtr($identifier, ['/' => '%2F'])
            : $identifier;

        // Only include the version segment in the URL when the caller passes
        // it explicitly or the setting iiifserver_media_api_version_append is
        // enabled. Otherwise the route default ('') is kept and the segment
        // is omitted, producing /iiif/{id}/info.json instead of /iiif/3/...
        $params += [
            'version' => $version ?: ($this->versionAppend ? $this->defaultVersion : ''),
            'prefix' => $this->prefix,
            'id' => $idForUrl,
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

        // Collapse the double-encoding produced by the pre-encoding step.
        // When encodeSlash is off, restore literal slashes so URLs remain
        // functional on servers without "AllowEncodedSlashes NoDecode"
        // (non-spec-compliant fallback).
        $urlIiif = $this->encodeSlash
            ? strtr($urlIiif, ['%252F' => '%2F'])
            : strtr($urlIiif, ['%252F' => '/', '%2F' => '/']);

        // The delimiter of the page should not be encoded for the image server.
        if ($page) {
            $urlIiif = strtr($urlIiif, ['%3B' => ';', '%3b' => ';']);
        }

        return $this->forceUrlFrom && (strpos($urlIiif, $this->forceUrlFrom) === 0)
            ? substr_replace($urlIiif, $this->forceUrlTo, 0, strlen($this->forceUrlFrom))
            : $urlIiif;
    }
}
