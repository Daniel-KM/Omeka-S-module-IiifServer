<?php declare(strict_types=1);

namespace IiifServer\Service\Controller;

use IiifServer\Controller\PresentationController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PresentationControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new PresentationController(
            $services->get('MvcTranslator')
        );
    }
}
