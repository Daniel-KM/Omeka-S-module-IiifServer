<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
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
     * @var Connection
     */
    protected $connection;

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
        MediaAdapter $mediaAdapter,
        Connection $connection
    ) {
        $this->basePath = $basePath;
        $this->tempFileFactory = $tempFileFactory;
        $this->mediaAdapter = $mediaAdapter;
        $this->connection = $connection;
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
        // For file path or url.
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

        // Try local file first, fall back to URL for external storage.
        // For images, skip file_exists(): @getimagesize() handles missing
        // files. For audio/video, keep file_exists(): GetId3 does its own
        // check, so skipping ours would just add init overhead for nothing.
        if ($type === 'original') {
            $storagePath = $this->getStoragePath($type, $media->filename());
            $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
            if ($mainMediaType === 'image') {
                $result = $this->getDimensionsLocal($filepath, $mainMediaType);
                if (!$result['width']) {
                    $result = $this->getDimensionsUrl($media->originalUrl(), $mainMediaType);
                }
            } else {
                $result = file_exists($filepath)
                    ? $this->getDimensionsLocal($filepath, $mainMediaType)
                    : $this->getDimensionsUrl($media->originalUrl(), $mainMediaType);
            }
        } else {
            $storagePath = $this->getStoragePath($type, $media->storageId(), 'jpg');
            $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
            if ($mainMediaType === 'image') {
                $result = $this->getDimensionsLocal($filepath, $mainMediaType);
                if (!$result['width']) {
                    $result = $this->getDimensionsUrl($media->thumbnailUrl($type), $mainMediaType);
                }
            } else {
                $result = file_exists($filepath)
                    ? $this->getDimensionsLocal($filepath, $mainMediaType)
                    : $this->getDimensionsUrl($media->thumbnailUrl($type), $mainMediaType);
            }
        }

        // Cache dimensions in media data to avoid computation on next request.
        if ($result['width'] || $result['height'] || $result['duration']) {
            $this->cacheMediaDimensions($media->id(), $type, $result);
        }

        return $result;
    }

    /**
     * Get a storage path.
     */
    protected function getStoragePath(string $prefix, ?string $name, string $extension = ''): string
    {
        return sprintf('%s/%s%s', $prefix, $name, strlen($extension) ? '.' . $extension : '');
    }

    /**
     * Helper to get width, height, and/or duration of a file or url.
     */
    protected function getDimensions(string $filepath): array
    {
        static $cache = [];

        if (!isset($cache[$filepath])) {
            // An internet path.
            if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
                $cache[$filepath] = $this->getDimensionsUrl($filepath);
            }
            // A normal path.
            elseif (file_exists($filepath) && is_file($filepath) && is_readable($filepath) && filesize($filepath)) {
                $cache[$filepath] = $this->getDimensionsLocal($filepath);
            }
            // Invalid path.
            else {
                $cache[$filepath] = $this->emptyDimensions;
            }
        }

        return $cache[$filepath];
    }

    /**
     * For images, try getimagesize() on the url first: it reads only the
     * image header over HTTP, which is much faster than downloading the
     * whole file, especially for remote storage (S3, etc.).
     * For audio/video, a full download is required for GetId3 analysis.
     */
    protected function getDimensionsUrl(?string $url, ?string $mainMediaType = null): array
    {
        if (empty($url)) {
            return $this->emptyDimensions;
        }

        // Fast path for images: read only the header via HTTP.
        if ($mainMediaType === 'image') {
            $result = @getimagesize($url);
            if ($result) {
                [$width, $height] = $result;
                if ($width && $height) {
                    // EXIF orientations 5-8 indicate a 90° or 270°
                    // rotation, so width and height must be swapped.
                    $exif = @exif_read_data($url);
                    if ($exif && !empty($exif['Orientation']) && $exif['Orientation'] >= 5) {
                        [$width, $height] = [$height, $width];
                    }
                    return [
                        'width' => (int) $width,
                        'height' => (int) $height,
                        'duration' => null,
                    ];
                }
            }
        }

        // Slow path: download the full file then analyze locally.
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
            $result = @getimagesize($filepath);
            if ($result) {
                [$width, $height] = $result;
                // EXIF orientations 5-8 indicate a 90° or 270°
                // rotation, so width and height must be swapped.
                try {
                    $exif = @exif_read_data($filepath);
                } catch (\Throwable $e) {
                    $exif = false;
                }
                if ($exif && !empty($exif['Orientation']) && $exif['Orientation'] >= 5) {
                    [$width, $height] = [$height, $width];
                }
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
        // In IIIF, the width and height should be positive integer and duration
        // should be a positive float.
        // In a previous version, a string was used instead of the float to
        // avoid the modification of the value during json conversion, but this
        // is an event that doesn't occur.
        $width = empty($data['video']['resolution_x']) ? null : (int) $data['video']['resolution_x'];
        $height = !$width || empty($data['video']['resolution_y']) ? null : (int) $data['video']['resolution_y'];
        $duration = empty($data['playtime_seconds']) ? null : (float) $data['playtime_seconds'];

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
        if (!isset($data['mime_type'])) {
            return $data;
        }
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
                'duration' => $dimensions['duration'] !== null ? (float) $dimensions['duration'] : null,
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
