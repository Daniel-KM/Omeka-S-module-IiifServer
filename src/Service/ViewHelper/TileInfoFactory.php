<?php
namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\TileInfo;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the TileInfo view helper.
 */
class TileInfoFactory implements FactoryInterface
{
    /**
     * Create and return the TileInfo view helper
     *
     * @return TileInfo
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new TileInfo(
            $plugins->has('tileInfo') ? $plugins->get('tileInfo') : null
        );
    }
}
