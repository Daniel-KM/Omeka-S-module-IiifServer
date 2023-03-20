<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use finfo;
use JamesHeinrich\GetID3\GetId3;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;
use Omeka\Mvc\Exception\RuntimeException;

class MediaDimension extends AbstractPlugin
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
     * @var MediaAdapter
     */
    protected $mediaAdapter;

    /**
     * The default output when the file is unavailable or unknown.
     *
     * @var array
     */
    protected $emptyDimensions = [
        'width' => null,
        'height' => null,
        'duration' => null,
    ];

    public function __construct(
        ?string $basePath,
        TempFileFactory $tempFileFactory,
        MediaAdapter $mediaAdapter
    ) {
        $this->basePath = $basePath;
        $this->tempFileFactory = $tempFileFactory;
        $this->mediaAdapter = $mediaAdapter;
    }

    /**
     * Get an array of the width, height, and/or duration of a media or file.
     *
     * Data can be stored in media data via the module Image Server or a job in
     * module Bulk Check.
     *
     * @todo Use the storage adapter.
     *
     * @param MediaRepresentation|Media|string $media Can be a media, an url or a filepath.
     * @param string $type "original", "large", "medium", "square", or any other
     * subdirectory of the directory "files".
     * @param bool $force Get dimensions from the file, not from the media data.
     * @return array Associative array of width, height, and/or duration of the
     * media. A dimension may be null. All dimensions are null for media that
     * are not an image, an audio or a video.
     * @throws RuntimeException
     */
    public function __invoke($media, string $type = 'original', bool $force = false): array
    {
        if ($media instanceof MediaRepresentation) {
            return $this->dimensionMedia($media, $type, $force);
        }
        if ($media instanceof Media) {
            $media = $this->mediaAdapter->getRepresentation($media);
            return $this->dimensionMedia($media, $type, $force);
        }
        // For file path.
        return $this->getDimensions((string) $media);
    }

    /**
     * Get an array of the width, height, and/or duration of a media.
     *
     * @throws RuntimeException
     */
    protected function dimensionMedia(MediaRepresentation $media, string $type, bool $force): array
    {
        // Check if this is a media (image, video, audio).
        $mainMediaType = substr((string) $media->mediaType(), 0, 5);
        if (!in_array($mainMediaType, ['image', 'video', 'audio'])
            // A security check.
            || strpos($type, '/..') !== false
            || strpos($type, '../') !== false
        ) {
            return $this->emptyDimensions;
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
                ? $this->getDimensionsLocal($filepath, $mainMediaType)
                : $this->getDimensionsUrl($media->originalUrl(), $mainMediaType);
        }

        $storagePath = $this->getStoragePath($type, $media->storageId(), 'jpg');
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        return file_exists($filepath)
            ? $this->getDimensionsLocal($filepath, $mainMediaType)
            : $this->getDimensionsUrl($media->thumbnailUrl($type), $mainMediaType);
    }

    /**
     * Get a storage path.
     */
    protected function getStoragePath(string $prefix, ?string $name, string $extension = ''): string
    {
        return sprintf('%s/%s%s', $prefix, $name, strlen($extension) ? '.' . $extension : '');
    }

    /**
     * Helper to get width, height, and/or duration of a media.
     */
    protected function getDimensions(string $filepath): array
    {
        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            return $this->getDimensionsUrl($filepath);
        }
        // A normal path.
        if (file_exists($filepath) && is_file($filepath) && is_readable($filepath) && filesize($filepath)) {
            return $this->getDimensionsLocal($filepath);
        }
        return $this->emptyDimensions;
    }

    protected function getDimensionsUrl(?string $url, ?string $mainMediaType = null): array
    {
        if (empty($url)) {
            return $this->emptyDimensions;
        }

        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath();
        $tempFile->delete();
        $handle = @fopen($url, 'rb');
        if ($handle) {
            $result = file_put_contents($tempPath, $handle);
            @fclose($handle);
            if ($result) {
                $dimensions = $this->getDimensionsLocal($tempPath, $mainMediaType);
                unlink($tempPath);
                return $dimensions;
            }
            unlink($tempPath);
        }

        return $this->emptyDimensions;
    }

    /**
     * Helper to get width, height, and/or duration of a media.
     *
     *  The path should be already checked.
     */
    protected function getDimensionsLocal(string $filepath, ?string $mainMediaType = null): array
    {
        // Do a quick check for images: getimagesize is nearly instant and
        // GetId3 doesn't support jpeg2000, etc.
        if (!$mainMediaType) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mainMediaType = strtok((string) $finfo->file($filepath), '/');
        }

        if ($mainMediaType === 'image') {
            $result = getimagesize($filepath);
            if ($result) {
                [$width, $height] = $result;
                return [
                    'width' => (int) $width,
                    'height' => (int) $height,
                    'duration' => null,
                ];
            }
        }

        $getId3 = new GetId3();
        $data = $getId3->analyze($filepath);
        $data = $this->fixOggDuration($data);
        $width = empty($data['video']['resolution_x']) ? null : (int) $data['video']['resolution_x'];
        $height = !$width || empty($data['video']['resolution_y']) ? null : (int) $data['video']['resolution_y'];
        $duration = empty($data['playtime_seconds']) ? null : (string) $data['playtime_seconds'];

        return [
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
        ];
    }

    /**
     * GetId3 does not support extraction of ogg duration for now, but it can be
     * determined indirectly.
     */
    private function fixOggDuration(array $data): array
    {
        if ($data['mime_type'] !== 'audio/ogg' && $data['mime_type'] !== 'video/ogg') {
            return $data;
        }
        if (!empty($data['playtime_seconds'])
            || empty($data['ogg']['pageheader']['eos']['segment_table'])
        ) {
            return $data;
        }
        // Use 1 to avoid an issue with the manifest when duration is required.
        $frames = array_sum($data['ogg']['pageheader']['eos']['segment_table']) ?: 1;
        $frameRate = $data['video']['frame_rate']
            ?? (($data['ogg']['pageheader']['theora']['frame_rate_numerator'] ?? 25) / ($data['ogg']['pageheader']['theora']['frame_rate_denominator'] ?? 1));
        $data['playtime_seconds'] = ceil($frames / ($frameRate ?: 1));
        return $data;
    }
}
