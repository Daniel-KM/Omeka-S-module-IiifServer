<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use finfo;
use JamesHeinrich\GetID3\GetID3;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\AssetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Asset;
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
    protected $emptyDimensions = [
        'width' => null,
        'height' => null,
        'duration' => null,
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
     * Get an array of the width, height, and/or duration of a media, asset or
     * file.
     *
     * Data can be stored in media data via the module Image Server or a job in
     * module Bulk Check.
     *
     * @todo Use the storage adapter.
     *
     * @param MediaRepresentation|AssetRepresentation|Media|Asset|string $media
     * Can be a media, an asset, a url or a filepath.
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
        if ($media instanceof MediaRepresentation
            || (class_exists('DigitalObject\Module', false)
                && $media instanceof \DigitalObject\Api\Representation\DigitalObjectRepresentation)
        ) {
            return $this->dimensionMedia($media, $type, $force);
        }
        if ($media instanceof AssetRepresentation) {
            return $this->dimensionAsset($media);
        }
        if ($media instanceof Media) {
            $media = $this->adapterManager->get('media')->getRepresentation($media);
            return $this->dimensionMedia($media, $type, $force);
        }
        if ($media instanceof Asset) {
            $media = $this->adapterManager->get('assets')->getRepresentation($media);
            return $this->dimensionAsset($media);
        }
        // For file path or url.
        return $this->getDimensions((string) $media);
    }

    /**
     * Get width and height of an asset (logo, thumbnail, placeholder, etc.).
     *
     * Assets are always images, never audio/video. Duration is always null.
     */
    protected function dimensionAsset(AssetRepresentation $asset): array
    {
        $storagePath = $this->getStoragePath('asset', $asset->filename());
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->getDimensionsLocal($filepath, 'image');
        if ($result['width']) {
            return $result;
        }
        return $this->getDimensionsUrl($asset->assetUrl(), 'image');
    }

    /**
     * Get an array of the width, height, and/or duration of a media.
     *
     * @throws RuntimeException
     */
    protected function dimensionMedia(AbstractResourceEntityRepresentation $media, string $type, bool $force): array
    {
        // Check if this is a media (image, video, audio). IIIF-ingested medias
        // may have a null media_type but are always images by construction
        // (Image API or Presentation canvas).
        $mainMediaType = substr((string) $media->mediaType(), 0, 5);
        if (!in_array($mainMediaType, ['image', 'video', 'audio'])) {
            // Use the renderer (not the ingester) so digital objects, whose
            // ingester is always "digital_object", are recognized too.
            $renderer = method_exists($media, 'renderer')
                ? (string) $media->renderer()
                : '';
            if (in_array($renderer, ['iiif', 'iiif_presentation'], true)) {
                $mainMediaType = 'image';
            } else {
                return $this->emptyDimensions;
            }
        }
        // A security check.
        if (strpos($type, '/..') !== false || strpos($type, '../') !== false) {
            return $this->emptyDimensions;
        }

        $mediaData = $media->mediaData() ?: [];

        // Check if size is already stored. Stored dimensions may contain an
        // all-null tuple (legacy rows). Use the cache only when at least one
        // value is non-null; otherwise fall through and recompute from file.
        if (!$force) {
            $stored = $mediaData['dimensions'][$type] ?? null;
            if (is_array($stored) && array_filter(
                $stored,
                static fn ($v) => $v !== null && $v !== ''
            )) {
                return $stored;
            }
        }

        // Remote IIIF medias (Omeka IIIF / IiifPresentation ingester) ship with
        // their info.json or canvas payload in mediaData. Width and height are
        // authoritative and avoid an HTTP probe of the original. Promote them
        // into the canonical dimensions[original] slot on the fly. Supported
        // shapes:
        //   - Image API v1/v2/v3 info.json   → width, height at top level
        //   - Presentation API v2/v3 canvas  → width, height at top level
        //   - Image API v2/v3 sizes[]        → largest entry
        // The info.json payload is treated as authoritative for the original
        // image, even when $force is true: a remote IIIF media has no other
        // reliable source short of downloading the file (the URL probe can also
        // fail when the iiif server requires special headers or a signed
        // request). Forcing a refresh of dimensions[$type] still re-runs the
        // local-file/URL probes for derivatives.
        if ($type === 'original' && $mainMediaType === 'image') {
            $infoDim = $this->extractIiifDimensions($mediaData);
            if ($infoDim) {
                $result = $infoDim + ['duration' => null];
                $table = $media->resourceName() === 'digital_objects'
                    ? 'digital_object'
                    : 'media';
                $this->cacheMediaDimensions($media->id(), $type, $result, $table);
                return $result;
            }
        }

        // Try local file first, fall back to URL only for external storage.
        // Locally-ingested medias (hasOriginal=true) whose file is missing from
        // disk must not trigger an HTTP probe: the URL points back to this
        // Omeka instance and the probe degrades into a slow 404 round trip,
        // which freezes the bulk job for seconds per broken media.
        $isLocal = method_exists($media, 'hasOriginal') ? (bool) $media->hasOriginal() : true;
        if ($type === 'original') {
            $storagePath = $this->getStoragePath($type, $media->filename());
            $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
            $localOk = file_exists($filepath) && is_readable($filepath);
            if ($localOk) {
                $result = $this->getDimensionsLocal($filepath, $mainMediaType);
            } elseif ($isLocal) {
                $result = $this->emptyDimensions;
            } else {
                $result = $this->getDimensionsUrl($media->originalUrl(), $mainMediaType);
            }
        } else {
            $storagePath = $this->getStoragePath($type, $media->storageId(), 'jpg');
            $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
            $localOk = file_exists($filepath) && is_readable($filepath);
            if ($localOk) {
                $result = $this->getDimensionsLocal($filepath, $mainMediaType);
            } elseif ($isLocal) {
                $result = $this->emptyDimensions;
            } else {
                $result = $this->getDimensionsUrl($media->thumbnailUrl($type), $mainMediaType);
            }
        }

        // Cache dimensions in media data to avoid computation on next request.
        // A media created but not yet persisted (item creation, before save)
        // has no id: nothing to update in database, skip. Dimensions are still
        // saved with the entity via setData() in the sizing listener.
        if ($media->id() && ($result['width'] || $result['height'] || $result['duration'])) {
            $table = $media->resourceName() === 'digital_objects' ? 'digital_object' : 'media';
            $this->cacheMediaDimensions((int) $media->id(), $type, $result, $table);
        }

        return $result;
    }

    /**
     * Read width/height from a mediaData payload deposited by a IIIF ingester.
     *
     * Probes, in order:
     *   - top-level width/height (Image API v1/v2/v3 info.json, Presentation
     *     API v2/v3 canvas);
     *   - largest entry of `sizes[]` (Image API v2/v3 alternative).
     *
     * @return array{width:int,height:int}|null
     */
    protected function extractIiifDimensions(array $mediaData): ?array
    {
        $w = $mediaData['width'] ?? null;
        $h = $mediaData['height'] ?? null;
        if ($w && $h) {
            return ['width' => (int) $w, 'height' => (int) $h];
        }
        $sizes = $mediaData['sizes'] ?? null;
        if (is_array($sizes) && $sizes) {
            $best = null;
            foreach ($sizes as $size) {
                if (!is_array($size)) {
                    continue;
                }
                $sw = (int) ($size['width'] ?? 0);
                $sh = (int) ($size['height'] ?? 0);
                if ($sw > 0 && $sh > 0
                    && (!$best || $sw > $best['width'])
                ) {
                    $best = ['width' => $sw, 'height' => $sh];
                }
            }
            if ($best) {
                return $best;
            }
        }
        return null;
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
     * For images, try getimagesize() on the url first: it reads only the image
     * header over HTTP, which is much faster than downloading the whole file,
     * especially for remote storage (S3, etc.).
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
                    // EXIF orientations 5-8 indicate a 90° or 270° rotation, so
                    // width and height must be swapped.
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
                // EXIF orientations 5-8 indicate a 90° or 270° rotation, so
                // width and height must be swapped.
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

        $getId3 = new GetID3();
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
        array $dimensions,
        string $table = 'media'
    ): void {
        // Only safe type values (original, large, medium, square).
        if (!preg_match('/^[a-zA-Z][\w-]*$/', $type)) {
            return;
        }
        // Allow-list table names to avoid SQL injection via the parameter.
        if (!in_array($table, ['media', 'digital_object'], true)) {
            return;
        }
        try {
            $raw = $this->connection->fetchOne(
                "SELECT `data` FROM `$table` WHERE `id` = ?",
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
                "UPDATE `$table` SET `data` = ? WHERE `id` = ?",
                [json_encode($mediaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $mediaId]
            );
        } catch (\Throwable $e) {
            // Retry later.
        }
    }
}
