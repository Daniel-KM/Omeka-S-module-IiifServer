<?php declare(strict_types=1);

namespace IiifServer\Service\ControllerPlugin;

use IiifServer\Mvc\Controller\Plugin\IsIiifMedia;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class IsIiifMediaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        $mediaIngesters = $config['iiifserver']['media_ingesters'] ?? [];
        return new IsIiifMedia($mediaIngesters);
    }
}
