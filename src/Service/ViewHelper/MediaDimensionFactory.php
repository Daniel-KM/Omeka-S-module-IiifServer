<?php
namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\MediaDimension;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MediaDimensionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $plugin = $pluginManager->get('mediaDimension');
        return new MediaDimension(
            $plugin
        );
    }
}
