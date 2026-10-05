<?php declare(strict_types=1);

namespace IiifServer\Service\ControllerPlugin;

use IiifServer\Mvc\Controller\Plugin\IiifAccessCache;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifAccessCacheFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): IiifAccessCache
    {
        return new IiifAccessCache(
            $services->get('Omeka\Connection'),
            $services->get('ControllerPluginManager')->has('isAllowedMediaContent')
        );
    }
}
