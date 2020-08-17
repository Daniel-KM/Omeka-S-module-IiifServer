<?php
namespace IiifServer\Mvc\Controller\Plugin;

use JamesHeinrich\GetID3\GetId3;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;
use Omeka\Mvc\Exception\RuntimeException;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

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
     * @param string $basePath
     * @param TempFileFactory $tempFileFactory
     * @param MediaAdapter $mediaAdapter
     */
    public function __construct(
        $basePath,
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
     * @todo Store dimensions in the data of the media. Or use numeric properties (with units).
     *
     * @param MediaRepresentation|Media|string $media
     * Can be a media, an url or a filepath.
     * @throws RuntimeException
     * @return array|null Associative array of width, height, and/or duration of
     * the media, else null.
     */
    public function __invoke($media)
    {
        if ($media instanceof MediaRepresentation) {
            return $this->dimensionMedia($media);
        }
        if ($media instanceof Media) {
            $media = $this->mediaAdapter->getRepresentation($media);
            return $this->dimensionMedia($media);
        }
        return $this->getDimensions($media);
    }

    /**
     * Get an array of the width, height, and/or duration of a media.
     *
     * @param MediaRepresentation $media
     * @throws RuntimeException
     * @return array|null Associative array of width, height, and/or duration of
     * the media, else null.
     */
    protected function dimensionMedia(MediaRepresentation $media)
    {
        // Check if this is a media (image, video, audio).
        if (!in_array(strtok($media->mediaType(), '/'), ['image', 'video', 'audio'])) {
            return ['width' => null, 'height' => null, 'duration' => null];
        }

        // Check if size is already stored. The stored dimension may be null.
        $mediaData = $media->mediaData();
        if (is_array($mediaData)
            && !empty($mediaData['dimensions']['original'])
        ) {
            return $mediaData['dimensions']['originial'];
        }

        // In order to manage external storage, check if the file is local.
        $storagePath = $this->getStoragePath('original', $media->filename());
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        return file_exists($filepath) && is_readable($filepath)
            ? $this->getDimensionsLocal($filepath)
            : $this->getDimensionsUrl($media->originalUrl());
    }

    /**
     * Get a storage path.
     *
     * @param string $prefix The storage prefix
     * @param string $name The file name, or basename if extension is passed
     * @param null|string $extension The file extension
     * @return string
     */
    protected function getStoragePath($prefix, $name, $extension = '')
    {
        return sprintf('%s/%s%s', $prefix, $name, strlen($extension) ? '.' . $extension : '');
    }

    /**
     * Helper to get width, height, and/or duration of a media.
     *
     * @param string $filepath It should be a video/audio/image (no check here).
     * @return array Associative array of width, height, and/or duration of the
     * media. Values are empty when the size is undetermined.
     */
    protected function getDimensions($filepath)
    {
        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            return $this->getDimensionsUrl($filepath);
        }
        // A normal path.
        if (file_exists($filepath) && is_readable($filepath)) {
            return $this->getDimensionsLocal($filepath);
        }
        return [
            'width' => null,
            'height' => null,
            'duration' => null,
        ];
    }

    /**
     * Helper to get width, height, and/or duration of a media (path is alreacy checked).
     *
     * @param string $filepath It should be a video/audio/image (no check here).
     * @return array Associative array of width, height, and/or duration of the
     * media. Values are empty when the size is undetermined.
     */
    protected function getDimensionsLocal($filepath)
    {
        return $this->getId3Dimensions($filepath);
    }

    protected function getDimensionsUrl($filepath)
    {
        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath();
        $tempFile->delete();
        $handle = @fopen($filepath, 'rb');
        if ($handle) {
            $result = file_put_contents($tempPath, $handle);
            @fclose($handle);
            if ($result) {
                $dimensions = $this->getId3Dimensions($tempPath);
                unlink($tempPath);
                return $dimensions;
            }
            unlink($tempPath);
        }

        return [
            'width' => null,
            'height' => null,
            'duration' => null,
        ];
    }

    protected function getId3Dimensions($filepath)
    {
        $getId3 = new GetId3();
        $data = $getId3->analyze($filepath);
        $width = empty($data['video']['resolution_x']) ? null : $data['video']['resolution_x'];
        $height = !$width || empty($data['video']['resolution_y']) ? null : $data['video']['resolution_y'];
        $duration = empty($data['playtime_seconds']) ? null : $data['playtime_seconds'];

        return [
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
        ];
    }
}
