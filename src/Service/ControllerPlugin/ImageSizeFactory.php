<?php declare(strict_types=1);
namespace IiifServer\Service\ControllerPlugin;

use IiifServer\Mvc\Controller\Plugin\ImageSize;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ImageSizeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ImageSize(
            $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files'),
            $services->get('Omeka\File\TempFileFactory'),
            $services->get('Omeka\ApiAdapterManager')
        );
    }
}
