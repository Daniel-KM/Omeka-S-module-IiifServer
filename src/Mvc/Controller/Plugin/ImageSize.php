<?php
namespace IiifServer\Mvc\Controller\Plugin;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFileFactory;
use Omeka\Mvc\Exception\RuntimeException;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

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
     * @param string $basePath
     * @param TempFileFactory $tempFileFactory
     */
    public function __construct($basePath, TempFileFactory $tempFileFactory)
    {
        $this->basePath = $basePath;
        $this->tempFileFactory = $tempFileFactory;
    }

    /**
     * Get an array of the width and height of the image file from a media.
     *
     * @todo Store size in the data of the media.
     *
     * @param MediaRepresentation $media
     * @param string $imageType
     * @throws RuntimeException
     * @return array|null Associative array of width and height of the image
     * file, else null.
     */
    public function __invoke(MediaRepresentation $media, $imageType = 'original')
    {
        // Check if this is an image.
        if (strtok($media->mediaType(), '/') !== 'image') {
            return null;
        }

        // The storage adapter should be checked for external storage.
        $storagePath = $imageType == 'original'
            ? $this->getStoragePath($imageType, $media->filename())
            : $this->getStoragePath($imageType, $media->storageId(), 'jpg');
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->getWidthAndHeight($filepath);

        // This is an image, but failed to get the resolution.
        if (empty($result)) {
            throw new RuntimeException(new Message('Failed to get image resolution: %s', // @translate
                $storagePath));
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
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array|null Associative array of width and height of the image
     * file, else null.
     */
    protected function getWidthAndHeight($filepath)
    {
        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempFile = $this->tempFileFactory->build();
            $tempPath = $tempFile->getTempPath();
            $tempFile->delete();
            $result = file_put_contents($tempPath, $filepath);
            if ($result !== false) {
                $result = getimagesize($tempPath);
                if ($result) {
                    list($width, $height) = $result;
                }
            }
            unlink($tempPath);
        }
        // A normal path.
        elseif (file_exists($filepath)) {
            $result = getimagesize($filepath);
            if ($result) {
                list($width, $height) = $result;
            }
        }

        if (empty($width) || empty($height)) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }
}
