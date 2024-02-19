<?php declare(strict_types=1);

namespace IiifServer\Service\Controller;

use IiifServer\Controller\NoopServerController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class NoopServerControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new NoopServerController(
            $services->get('MvcTranslator')
        );
    }
}
