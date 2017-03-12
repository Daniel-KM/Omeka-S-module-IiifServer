<?php
namespace IiifServer\Mvc\Controller\Plugin;

use Omeka\Service\Exception\InvalidArgumentException;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class TileBuilder extends AbstractPlugin
{
    /**
     * Extension added to a folder name to store data and tiles for Zoomify.
     *
     * @var string
     */
    const FOLDER_EXTENSION_ZOOMIFY = '_zdata';

    /**
     * Convert the source into tiles of the specified format and store them.
     *
     * @param string $filepath The path to the image.
     * @param string $destination The directory where to store the tiles.
     * @param array $params The processor to use or the path to a command.
     * @return void
     */
    public function __invoke($source, $destination, array $params = [])
    {
        $source = realpath($source);
        if (empty($source)) {
            throw new InvalidArgumentException('Source is empty.'); // @translate
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new InvalidArgumentException(new Message(
                'Source file "%s" is not readable.', $source)); // @translate
        }

        if (empty($destination)) {
            throw new InvalidArgumentException('Destination is empty.'); // @translate
        }

        $format = $params['format'];
        unset($params['format']);
        switch ($format) {
            case 'deepzoom':
                $this->deepzoom($source, $destination, $params);
                break;
            case 'zoomify':
                if (empty($params['storageId'])) {
                    $params['storageId'] = pathinfo($source, PATHINFO_FILENAME);
                }
                $destination .= DIRECTORY_SEPARATOR . $params['storageId'] . self::FOLDER_EXTENSION_ZOOMIFY;
                $this->zoomify($source, $destination, $params);
                break;
            default:
                throw new InvalidArgumentException(new Message(
                    'Format "%s" is not supported by the tile builder.', $format)); // @translate
        }
    }

    /**
     * Passed a file name, it will initilize the deepzoom and cut the tiles.
     *
     * @param string $source The path to the image.
     * @param string $destination The directory where to store the tiles.
     * @param array $params The params for the graphic processor.
     * @return void
     */
    protected function deepzoom($source, $destination, $params)
    {
        require_once dirname(dirname(dirname(__DIR__)))
            . DIRECTORY_SEPARATOR . 'libraries'
            . DIRECTORY_SEPARATOR . 'Deepzoom'
            . DIRECTORY_SEPARATOR . 'Deepzoom.php';

        $config = $params;
        $config['destinationRemove'] = true;
        $deepzoom = new \Deepzoom\Deepzoom($config);
        $deepzoom->process($source, $destination);
    }

    /**
     * Passed a file name, it will initilize the zoomify and cut the tiles.
     *
     * @param string $source The path to the image.
     * @param string $destination The directory where to store the tiles.
     * @param array $params The params for the graphic processor.
     * @return void
     */
    protected function zoomify($source, $destination, $params)
    {
        $processor = $params['processor'];
        if (!empty($processor) && !in_array($processor, ['imagick', 'gd'])) {
            // Check if another processor is available before throwing an error.
            if (extension_loaded('imagick')) {
                $processor = 'imagick';
            } elseif (extension_loaded('gd')) {
                $processor = 'gd';
            } else {
                throw new InvalidArgumentException(
                    'The Zoomify format requires the processors "Imagick" or "GD".'); // @translate
            }
        }

        require_once dirname(dirname(dirname(__DIR__)))
            . DIRECTORY_SEPARATOR . 'libraries'
            . DIRECTORY_SEPARATOR . 'Zoomify'
            . DIRECTORY_SEPARATOR . 'Zoomify.php';

        $zoomify = new \Zoomify();
        $zoomify->processor = $processor;
        $zoomify->updatePerms = false;
        $zoomify->destinationRemove = true;
        $zoomify->convertPath = $params['convertPath'];
        $zoomify->executeStrategy = $params['executeStrategy'];
        $zoomify->zoomifyImage($source, $destination);
    }
}
