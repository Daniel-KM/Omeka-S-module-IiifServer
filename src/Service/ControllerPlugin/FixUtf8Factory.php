<?php declare(strict_types=1);

namespace IiifServer\Service\ControllerPlugin;

use IiifServer\Mvc\Controller\Plugin\FixUtf8;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FixUtf8Factory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new FixUtf8(
            $services->get('Omeka\Logger')
        );
    }
}
