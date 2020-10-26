<?php
namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\MediaDimension;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaDimensionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MediaDimension(
            $services->get('ControllerPluginManager')->get('mediaDimension')
        );
    }
}
