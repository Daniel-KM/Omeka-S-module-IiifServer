<?php declare(strict_types=1);
namespace IiifServer\Service\ControllerPlugin;

// The autoload doesn’t work with GetId3.
if (!class_exists(\JamesHeinrich\GetID3\GetId3::class)) {
    require_once dirname(__DIR__, 3) . '/vendor/james-heinrich/getid3/src/GetID3.php';
}

use IiifServer\Mvc\Controller\Plugin\MediaDimension;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaDimensionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MediaDimension(
            $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files'),
            $services->get('Omeka\File\TempFileFactory'),
            $services->get('Omeka\ApiAdapterManager')->get('media')
        );
    }
}
