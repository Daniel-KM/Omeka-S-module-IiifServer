<?php

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifManifest;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the api view helper.
 */
class IiifManifestFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new IiifManifest($tempFileFactory, $basePath);
    }
}
