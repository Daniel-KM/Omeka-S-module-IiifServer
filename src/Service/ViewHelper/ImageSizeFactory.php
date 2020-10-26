<?php
namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\ImageSize;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ImageSizeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        // Plugin ImageSize may be overridden by module ImageServer or another one.
        $plugin = $pluginManager->get('imageSize');
        return new ImageSize(
            $plugin
        );
    }
}
