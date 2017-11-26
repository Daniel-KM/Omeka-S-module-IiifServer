<?php
namespace IiifServer\Service\Media\Ingester;

use IiifServer\Media\Ingester\Tile;
use Interop\Container\ContainerInterface;
use Omeka\Service\Exception\ConfigException;
use Zend\ServiceManager\Factory\FactoryInterface;

class TileFactory implements FactoryInterface
{
    /**
     * Create the Tile ingester service.
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

        $validator = $services->get('Omeka\File\Validator');
        $uploader = $services->get('Omeka\File\Uploader');
        $jobDispatcher = $services->get('Omeka\Job\Dispatcher');
        return new Tile(
            $validator,
            $uploader,
            $jobDispatcher
        );
    }
}
