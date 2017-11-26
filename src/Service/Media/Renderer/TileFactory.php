<?php
namespace IiifServer\Service\Media\Renderer;

use IiifServer\Media\Renderer\Tile;
use Omeka\Service\Exception\ConfigException;
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
        if (empty($tileDir)) {
            throw new ConfigException('The tile dir is not defined.');
        }
        return new Tile($tileDir);
    }
}
