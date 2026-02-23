<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Representation\AssetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Asset;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;

class ImageSize extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @var AdapterManager
     */
    protected $adapterManager;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * The default output when the file is unavailable or unknown.
     *
     * @var array
     */
    protected $emptySize = [
        'width' => null,
        'height' => null,
    ];

    public function __construct(
        ?string $basePath,
        TempFileFactory $tempFileFactory,
        AdapterManager $adapterManager,
        Connection $connection
    ) {
        $this->basePath = $basePath;
        $this->tempFileFactory = $tempFileFactory;
        $this->adapterManager = $adapterManager;
        $this->connection = $connection;
    }

    /**
     * Get an array of the width and height of the image file from a media.
     *
     * If media is not an image, width and height are null.
     *
     * @param MediaRepresentation|AssetRepresentation|Media|Asset|string $image
     * Can be a media, an asset, a url or a filepath.
     * @param string $type
     * @param bool $force Get size from the file, not from the media data.
     * @return array Associative array of width and height of the image file as
     * integer. Values are empty (null or zero) when the size is undetermined.
     */
    public function __invoke($image, string $type = 'original', bool $force = false): array
    {
        // A security check. Useful?
        if (strpos($type, '/..') !== false || strpos($type, '../') !== false) {
            return $this->emptySize;
        } elseif ($image instanceof MediaRepresentation) {
            return $this->sizeMedia($image, $type, $force);
        } elseif ($image instanceof AssetRepresentation) {
            return $this->sizeAsset($image, $force);
        } elseif ($image instanceof Media) {
            $image = $this->adapterManager->get('media')->getRepresentation($image);
            return $this->sizeMedia($image, $type, $force);
        } elseif ($image instanceof Asset) {
            $image = $this->adapterManager->get('assets')->getRepresentation($image);
            return $this->sizeAsset($image, $force);
        } else {
            return $this->getWidthAndHeight((string) $image);
        }
    }

    /**
     * Get an array of the width and height of the image file from a media.
     */
    protected function sizeMedia(MediaRepresentation $media, string $type, bool $force): array
    {
        // Check if this is an image for type original.
        if ($type === 'original') {
            $mainMediaType = substr((string) $media->mediaType(), 0, 5);
            if ($mainMediaType !== 'image') {
                return $this->emptySize;
            }
        }

        // In-memory cache to avoid redundant lookups within the same request
        // (the DB-cached mediaData may not reflect a just-written value).
        static $cache = [];
        $cacheKey = $media->id() . '/' . $type;
        if (!$force && isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        // Check if size is already stored. The stored dimension may be null.
        if (!$force) {
            $mediaData = $media->mediaData();
            if (is_array($mediaData)
                && !empty($mediaData['dimensions'][$type])
            ) {
                $cache[$cacheKey] = $mediaData['dimensions'][$type];
                return $mediaData['dimensions'][$type];
            }
        }

        // Try local file first, fall back to URL for external storage.
        if ($type === 'original') {
            $storagePath = $this->getStoragePath($type, $media->filename());
            $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
            $result = $this->getWidthAndHeightLocal($filepath);
            if (!$result['width']) {
                $result = $this->getWidthAndHeightUrl($media->originalUrl());
            }
        } else {
            $storagePath = $this->getStoragePath($type, $media->storageId(), 'jpg');
            $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
            $result = $this->getWidthAndHeightLocal($filepath);
            if (!$result['width']) {
                $result = $this->getWidthAndHeightUrl($media->thumbnailUrl($type));
            }
        }

        // Cache dimensions in media data to avoid computation on next request.
        if ($result['width'] && $result['height']) {
            $this->cacheMediaDimensions($media->id(), $type, $result);
            $cache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Get an array of the width and height of the image file from an asset.
     */
    protected function sizeAsset(AssetRepresentation $asset): array
    {
        // The storage adapter should be checked for external storage.
        $storagePath = $this->getStoragePath('asset', $asset->filename());
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->getWidthAndHeightLocal($filepath);
        if ($result['width']) {
            return $result;
        }
        return $this->getWidthAndHeightUrl($asset->assetUrl());
    }

    /**
     * Get a storage path.
     */
    protected function getStoragePath(string $prefix, ?string $name, string $extension = ''): string
    {
        return sprintf('%s/%s%s', $prefix, $name, strlen($extension) ? '.' . $extension : '');
    }

    /**
     * Helper to get width and height of an image, local or remote.
     */
    protected function getWidthAndHeight(string $filepath): array
    {
        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            return $this->getWidthAndHeightUrl($filepath);
        }
        // A normal path.
        return $this->getWidthAndHeightLocal($filepath);
    }

    /**
     * Helper to get width and height of a local image file.
     *
     * Handles missing or unreadable files gracefully: @getimagesize()
     * returns false and the method returns $this->emptySize.
     */
    protected function getWidthAndHeightLocal(string $filepath): array
    {
        $result = @getimagesize($filepath);
        if (!$result) {
            return $this->emptySize;
        }
        [$width, $height] = $result;
        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Helper to get width and height of an image url.
     *
     * Try getimagesize() on the url first: it reads only the image header
     * over HTTP, which is much faster than downloading the whole file,
     * especially for remote storage (S3, etc.).
     * Fall back to a full download only when the direct call fails (e.g.
     * allow_url_fopen off, authenticated url, or unsupported format).
     */
    protected function getWidthAndHeightUrl(string $url): array
    {
        // Fast path: getimagesize() reads only the image header via HTTP.
        $result = @getimagesize($url);
        if ($result) {
            [$width, $height] = $result;
            if ($width && $height) {
                return [
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        // Slow path: download the full file then check dimensions locally.
        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath();
        $tempFile->delete();
        $handle = @fopen($url, 'rb');
        if ($handle) {
            $result = file_put_contents($tempPath, $handle);
            @fclose($handle);
            if ($result) {
                $result = getimagesize($tempPath);
                if ($result) {
                    [$width, $height] = $result;
                    unlink($tempPath);
                    return [
                        'width' => $width,
                        'height' => $height,
                    ];
                }
            }
            unlink($tempPath);
        }

        return $this->emptySize;
    }

    /**
     * Store computed dimensions into media data.
     *
     * Omeka require mysl 5, so a single sql with json_set cannot be used.
     */
    protected function cacheMediaDimensions(
        int $mediaId,
        string $type,
        array $dimensions
    ): void {
        // Only safe type values (original, large, medium, square).
        if (!preg_match('/^[a-zA-Z][\w-]*$/', $type)) {
            return;
        }
        try {
            $raw = $this->connection->fetchOne(
                'SELECT `data` FROM `media` WHERE `id` = ?',
                [$mediaId]
            );
            $mediaData = $raw ? json_decode($raw, true) : [];
            if (!is_array($mediaData)) {
                $mediaData = [];
            }
            $mediaData['dimensions'][$type] = [
                'width' => $dimensions['width'] ? (int) $dimensions['width'] : null,
                'height' => $dimensions['height'] ? (int) $dimensions['height'] : null,
            ];
            $this->connection->executeStatement(
                'UPDATE `media` SET `data` = ? WHERE `id` = ?',
                [json_encode($mediaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $mediaId]
            );
        } catch (\Exception $e) {
            // Retry later.
        }
    }
}
