<?php declare(strict_types=1);

namespace IiifServer\Service\Controller;

use IiifServer\Controller\MediaController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $plugins = $services->get('ControllerPluginManager');
        return new MediaController(
            $services->get('Omeka\File\Store'),
            $basePath,
            $plugins->has('isAllowedMediaContent') ? $plugins->get('isAllowedMediaContent') : null
        );
    }
}
