<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifTileInfo;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifTileInfoFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new IiifTileInfo(
            $plugins->has('tileInfo') ? $plugins->get('tileInfo') : null
        );
    }
}
