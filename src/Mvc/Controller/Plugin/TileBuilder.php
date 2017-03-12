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
     * @param string $format The format of the tiles.
     * @param string $storageId The storage id may be used to create filenames
     * with some formats.
     * @return void
     */
    public function __invoke($source, $destination, $format, $storageId = '')
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

        switch ($format) {
            case 'deepzoom':
                $this->deepzoom($source, $destination);
                break;
            case 'zoomify':
                if (empty($storageId)) {
                    $storageId = pathinfo($source, PATHINFO_FILENAME);
                }
                $destination .= DIRECTORY_SEPARATOR . $storageId . self::FOLDER_EXTENSION_ZOOMIFY;
                $this->zoomify($source, $destination);
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
     * @return void
     */
    public function deepzoom($source, $destination)
    {
    }

    /**
     * Passed a file name, it will initilize the zoomify and cut the tiles.
     *
     * @param string $source The path to the image.
     * @param string $destination The directory where to store the tiles.
     * @return void
     */
    public function zoomify($source, $destination)
    {
        require_once dirname(dirname(dirname(__DIR__)))
            . DIRECTORY_SEPARATOR . 'libraries'
            . DIRECTORY_SEPARATOR . 'Zoomify'
            . DIRECTORY_SEPARATOR . 'Zoomify.php';

        $zoomify = new \Zoomify();
        $zoomify->updatePerms = false;
        $zoomify->zoomifyImage($source, $destination);
    }
}
