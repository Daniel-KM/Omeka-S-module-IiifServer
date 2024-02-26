<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifManifest2;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifManifest2Factory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $viewHelpers = $services->get('ViewHelperManager');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new IiifManifest2(
            $viewHelpers->has('isAllowedMediaContent') ? $viewHelpers->get('isAllowedMediaContent') : null,
            $services->get('Omeka\Settings'),
            $services->get('Omeka\File\TempFileFactory'),
            $basePath
        );
    }
}
