<?php
namespace IiifServer\Mvc\Controller\Plugin;

use DanielKm\Deepzoom\DeepzoomFactory;
use DanielKm\Zoomify\ZoomifyFactory;
use Omeka\Service\Exception\InvalidArgumentException;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class TileBuilder extends AbstractPlugin
{
    /**
     * Extension added to a folder name to store tiles for Deepzoom.
     *
     * @var string
     */
    const FOLDER_EXTENSION_DEEPZOOM = '_files';

    /**
     * Extension added to a file to store data for Deepzoom.
     *
     * @var string
     */
    const FOLDER_EXTENSION_DEEPZOOM_FILE = '.dzi';

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
     * @return array Info on result, the tile dir and the tile data file if any.
     */
    public function __invoke($source, $destination, array $params = [])
    {
        $source = realpath($source);
        if (empty($source)) {
            throw new InvalidArgumentException('Source is empty.'); // @translate
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new InvalidArgumentException(new Message(
                'Source file "%s" is not readable.', // @translate
                $source
            ));
        }

        if (empty($destination)) {
            throw new InvalidArgumentException('Destination is empty.'); // @translate
        }

        $params['destinationRemove'] = true;

        if (empty($params['storageId'])) {
            $params['storageId'] = pathinfo($source, PATHINFO_FILENAME);
        }

        $result = [];
        $tileType = $params['tile_type'];
        unset($params['tile_type']);
        switch ($tileType) {
            case 'deepzoom':
                $result['result'] = $this->deepzoom($source, $destination, $params);
                $result['tile_dir'] = $destination . DIRECTORY_SEPARATOR . basename($params['storageId']) . self::FOLDER_EXTENSION_DEEPZOOM;
                $result['tile_file'] = $destination . DIRECTORY_SEPARATOR . basename($params['storageId']) . self::FOLDER_EXTENSION_DEEPZOOM_FILE;
                break;
            case 'zoomify':
                $destination .= DIRECTORY_SEPARATOR . basename($params['storageId']) . self::FOLDER_EXTENSION_ZOOMIFY;
                $result['result'] = $this->zoomify($source, $destination, $params);
                $result['tile_dir'] = $destination;
                break;
            default:
                throw new InvalidArgumentException(new Message(
                    'The type of tiling "%s" is not supported by the tile builder.', // @translate
                    $tileType
                ));
        }

        return $result;
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
        // This direct autoload avoid to load useless class in Omeka.
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
        // This direct autoload avoid to load useless class in Omeka.
        require_once dirname(dirname(dirname(dirname(__DIR__))))
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'daniel-km'
            . DIRECTORY_SEPARATOR . 'zoomify'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'ZoomifyFactory.php';

        $factory = new ZoomifyFactory;
        $processor = $factory($params);
        return $processor->process($source, $destination);
    }
}
