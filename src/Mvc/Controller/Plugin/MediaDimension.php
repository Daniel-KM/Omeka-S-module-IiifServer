<?php
namespace IiifServer\Mvc\Controller\Plugin;

use JamesHeinrich\GetID3\GetId3;
use Omeka\File\TempFileFactory;
use Omeka\Api\Representation\MediaRepresentation;
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
     * @param string $basePath
     * @param TempFileFactory $tempFileFactory
     */
    public function __construct($basePath, TempFileFactory $tempFileFactory)
    {
        $this->basePath = $basePath;
        $this->tempFileFactory = $tempFileFactory;
    }

    /**
     * Get an array of the width, height, and/or duration of a media or file.
     *
     * @todo Store dimensions in the data of the media. Or use numeric properties (with units).
     *
     * @param \Omeka\Api\Representation\MediaRepresentation|string $media Can be an
     * media, an url or a filepath.
     * @throws RuntimeException
     * @return array|null Associative array of width, height, and/or duration of
     * the media, else null.
     */
    public function __invoke($media)
    {
        if ($media instanceof MediaRepresentation) {
            return $this->dimensionMedia($media);
        }
        return $this->dimensionFile($media);
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
            return null;
        }

        // The storage adapter should be checked for external storage.
        $storagePath = $this->getStoragePath('original', $media->filename());
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->getDimensions($filepath);

        // This is a, audio/video/image, but failed to get the dimensions.
        if (empty($result)) {
            throw new RuntimeException(new Message('Failed to get media dimensions: %s', // @translate
                $storagePath));
        }

        return $result;
    }

    /**
     * Get an array of the width and height of the image file from a file.
     *
     * @param string $file Filepath or url
     * @throws RuntimeException
     * @return array|null Associative array of width, height, and/or duration of
     * the media, else null.
     */
    protected function dimensionFile($file)
    {
        $result = $this->getDimensions($file);
        if (empty($result)) {
            throw new RuntimeException(new Message('Failed to get media dimension: %s', // @translate
                $file));
        }
        return $result;
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
     * @return array|null Associative array of width, height, and/or duration of
     * the media, else null.
     */
    protected function getDimensions($filepath)
    {
        $dimensions = null;

        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempFile = $this->tempFileFactory->build();
            $tempPath = $tempFile->getTempPath();
            $tempFile->delete();
            $handle = @fopen($filepath, 'rb');
            if ($handle) {
                $result = file_put_contents($tempPath, $handle);
                @fclose($handle);
                if ($result) {
                    $dimensions = $this->getId3Dimensions($tempPath);
                }
                unlink($tempPath);
            }
            return $dimensions;
        }

        // A normal path.
        if (file_exists($filepath)) {
            return $this->getId3Dimensions($filepath);
        }

        return null;
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
