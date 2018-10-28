<?php
namespace IiifServer\Service\ControllerPlugin;

use IiifServer\Mvc\Controller\Plugin\ImageSize;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImageSizeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        return new ImageSize($basePath, $tempFileFactory);
    }
}
