<?php

namespace IiifServer\Mvc\Controller\Plugin;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Stdlib\Message;
use Zend\Log\LoggerInterface;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class Tiler extends AbstractPlugin
{
    /**
     * @var array
     */
    protected $params;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param array $params
     * @param LoggerInterface $logger
     */
    public function __construct(array $params, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->logger = $logger;
    }

    /**
     * Tile a media.
     *
     * @var MediaRepresentation $media
     * @var bool $removeDestination
     * @return array|bool False on error, else data about tiling, with a boolean
     * for key "result".
     */
    public function __invoke(MediaRepresentation $media, $removeDestination = false)
    {
        if (!$media->hasOriginal()
            || strtok($media->mediaType(), '/') !== 'image'
        ) {
            return false;
        }

        $sourcePath = $this->params['basePath'] . '/original/' . $media->filename();
        if (!file_exists($sourcePath) || !filesize($sourcePath)) {
            $message = new Message(
                'The file "%s" of media #%d is missing', // @translate
                $media->filename(),
                $media->id()
            );
            $this->logger->err($message);
            return false;
        }

        $params = $this->params;

        // When a specific store or Archive Repertory are used, the storage id
        // may contain a subdir, so it should be added. There is no change with
        // the default simple storage id.
        $storageId = $media->storageId();
        $params['storageId'] = basename($storageId);
        $tileDir = $params['basePath'] . DIRECTORY_SEPARATOR . $params['tile_dir'];
        $tileDir = dirname($tileDir . DIRECTORY_SEPARATOR . $storageId);

        $params['destinationRemove'] = $removeDestination;

        $tileBuilder = new TileBuilder();
        try {
            $result = $tileBuilder($sourcePath, $tileDir, $params);
        } catch (\Exception $e) {
            $message = new Message(
                'The tiler failed: %s', // @translate
                $e
            );
            $this->logger->err($message);
            return false;
        }

        return $result;
    }
}
