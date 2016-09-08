<?php

namespace UniversalViewer\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use UniversalViewer\View\Helper\IiifManifest;

/**
 * Service factory for the api view helper.
 */
class IiifManifestFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new IiifManifest($services->get('Omeka\File\Manager'));
    }
}
