<?php declare(strict_types=1);
namespace ImageServer\Service\ViewHelper;

use ImageServer\View\Helper\IiifInfo2;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the api view helper.
 */
class IiifInfo2Factory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new IiifInfo2($tempFileFactory, $basePath);
    }
}
