<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\RangeToArray;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RangeToArrayFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new RangeToArray(
            $services->get('ControllerPluginManager')->get('rangeToArray')
        );
    }
}
