<?php
namespace IiifServer\Service\ControllerPlugin;

// The autoload doesn‘t work with GetId3.
if (!class_exists(\JamesHeinrich\GetID3\GetId3::class)) {
    require_once dirname(dirname(dirname(__DIR__))) . '/vendor/james-heinrich/getid3/src/GetID3.php';
}

use IiifServer\Mvc\Controller\Plugin\MediaDimension;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MediaDimensionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        return new MediaDimension($basePath, $tempFileFactory);
    }
}
