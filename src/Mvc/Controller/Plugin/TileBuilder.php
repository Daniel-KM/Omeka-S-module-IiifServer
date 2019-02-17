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

        $params['destinationRemove'] = !empty($params['destinationRemove']);

        if (empty($params['storageId'])) {
            $params['storageId'] = pathinfo($source, PATHINFO_FILENAME);
        }

        $result = [];
        $tileType = $params['tile_type'];
        unset($params['tile_type']);
        switch ($tileType) {
            case 'deepzoom':
                $factory = new DeepzoomFactory;
                $result['tile_dir'] = $destination . DIRECTORY_SEPARATOR . basename($params['storageId']) . self::FOLDER_EXTENSION_DEEPZOOM;
                $result['tile_file'] = $destination . DIRECTORY_SEPARATOR . basename($params['storageId']) . self::FOLDER_EXTENSION_DEEPZOOM_FILE;
                break;
            case 'zoomify':
                $factory = new ZoomifyFactory;
                $destination .= DIRECTORY_SEPARATOR . basename($params['storageId']) . self::FOLDER_EXTENSION_ZOOMIFY;
                $result['tile_dir'] = $destination;
                break;
            default:
                throw new InvalidArgumentException(new Message(
                    'The type of tiling "%s" is not supported by the tile builder.', // @translate
                    $tileType
                ));
        }

        if (!$params['destinationRemove']) {
            if (is_dir($result['tile_dir'])) {
                $result['result'] = true;
                $result['skipped'] = true;
                return $result;
            }
        }

        $processor = $factory($params);
        $result['result'] = $processor->process($source, $destination);

        return $result;
    }
}
