<?php
namespace IiifServer\Mvc\Controller\Plugin;

use Omeka\Service\Exception\InvalidArgumentException;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use DanielKm\Deepzoom\DeepzoomFactory;
use DanielKm\Zoomify\ZoomifyFactory;

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

        $params['destinationRemove'] = true;

        $tileType = $params['tile_type'];
        unset($params['tile_type']);
        switch ($tileType) {
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
                    'The type of tiling "%s" is not supported by the tile builder.', $tileType)); // @translate
        }
    }

    /**
     * Passed a file name, it will initilize the deepzoom and cut the tiles.
     *
     * @param string $source The path to the image.
     * @param string $destination The directory where to store the tiles.
     * @param array $params The params for the graphic processor.
     * @return bool
     */
    protected function deepzoom($source, $destination, $params)
    {
        require_once dirname(dirname(dirname(dirname(__DIR__))))
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'daniel-km'
            . DIRECTORY_SEPARATOR . 'deepzoom'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'DeepzoomFactory.php';

        $factory = new DeepzoomFactory;
        $processor = $factory($params);
        return $processor->process($source, $destination);
    }

    /**
     * Passed a file name, it will initilize the zoomify and cut the tiles.
     *
     * @param string $source The path to the image.
     * @param string $destination The directory where to store the tiles.
     * @param array $params The params for the graphic processor.
     */
    protected function zoomify($source, $destination, $params)
    {
        require_once dirname(dirname(dirname(dirname(__DIR__))))
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'daniel-km'
            . DIRECTORY_SEPARATOR . 'zoomify'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'ZoomifyFactory.php';

        $factory = new ZoomifyFactory;
        $processor = $factory($params);
        $processor->process($source, $destination);
    }
}
