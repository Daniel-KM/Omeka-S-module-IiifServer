<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

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
        AdapterManager $adapterManager
    ) {
        $this->basePath = $basePath;
        $this->tempFileFactory = $tempFileFactory;
        $this->adapterManager = $adapterManager;
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
        if ($image instanceof MediaRepresentation) {
            return $this->sizeMedia($image, $type, $force);
        }
        if ($image instanceof AssetRepresentation) {
            return $this->sizeAsset($image, $force);
        }
        if ($image instanceof Media) {
            $image = $this->adapterManager->get('media')->getRepresentation($image);
            return $this->sizeMedia($image, $type, $force);
        }
        if ($image instanceof Asset) {
            $image = $this->adapterManager->get('assets')->getRepresentation($image);
            return $this->sizeAsset($image, $force);
        }
        return $this->getWidthAndHeight((string) $image);
    }

    /**
     * Get an array of the width and height of the image file from a media.
     */
    protected function sizeMedia(MediaRepresentation $media, string $type, bool $force): array
    {
        // Check if this is an image.
        $mainMediaType = substr((string) $media->mediaType(), 0, 5);
        if ($mainMediaType !== 'image'
            // A security check.
            || strpos($type, '/..') !== false
            || strpos($type, '../') !== false
        ) {
            return $this->emptySize;
        }

        // Check if size is already stored. The stored dimension may be null.
        if (!$force) {
            $mediaData = $media->mediaData();
            if (is_array($mediaData)
                && !empty($mediaData['dimensions'][$type])
            ) {
                return $mediaData['dimensions'][$type];
            }
        }

        // In order to manage external storage, check if the file is local.
        if ($type === 'original') {
            $storagePath = $this->getStoragePath($type, $media->filename());
            $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
            return file_exists($filepath)
                ? $this->getWidthAndHeightLocal($filepath)
                : $this->getWidthAndHeightUrl($media->originalUrl());
        }

        $storagePath = $this->getStoragePath($type, $media->storageId(), 'jpg');
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        return file_exists($filepath)
            ? $this->getWidthAndHeightLocal($filepath)
            : $this->getWidthAndHeightUrl($media->thumbnailUrl($type));
    }

    /**
     * Get an array of the width and height of the image file from an asset.
     */
    protected function sizeAsset(AssetRepresentation $asset): array
    {
        // The storage adapter should be checked for external storage.
        $storagePath = $this->getStoragePath('asset', $asset->filename());
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        return file_exists($filepath)
            ? $this->getWidthAndHeightLocal($filepath)
            : $this->getWidthAndHeightUrl($asset->assetUrl());
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
        if (file_exists($filepath) && is_file($filepath) && is_readable($filepath) && filesize($filepath)) {
            return $this->getWidthAndHeightLocal($filepath);
        }
        return $this->emptySize;
    }

    /**
     * Helper to get width and height of an image (path is already checked).
     */
    protected function getWidthAndHeightLocal(string $filepath): array
    {
        $result = getimagesize($filepath);
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
     */
    protected function getWidthAndHeightUrl(string $url): array
    {
        $width = null;
        $height = null;

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
                }
            }
            unlink($tempPath);
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }
}
