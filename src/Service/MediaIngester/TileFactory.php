<?php
namespace IiifServer\Service\MediaIngester;

use IiifServer\Media\Ingester\Tile;
use IiifServer\Mvc\Controller\Plugin\TileBuilder;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class TileFactory implements FactoryInterface
{
    /**
     * Create the Tile ingester service.
     *
     * @return Tile
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');

        $tileBuilder = new TileBuilder();

        $settings = $services->get('Omeka\Settings');

        $params = [];
        $params['tile_dir'] = $settings->get('iiifserver_image_tile_dir');
        $params['format'] = $settings->get('iiifserver_image_tile_format');

        return new Tile($fileManager, $tileBuilder, $params);
    }
}
