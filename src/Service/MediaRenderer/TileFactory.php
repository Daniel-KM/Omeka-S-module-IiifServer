<?php
namespace IiifServer\Service\MediaRenderer;

use IiifServer\Media\Renderer\Tile;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class TileFactory implements FactoryInterface
{
    /**
     * Create the Tile renderer service.
     *
     * @return Tile
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $tileDir = $settings->get('iiifserver_image_tile_dir');
        return new Tile($tileDir);
    }
}
