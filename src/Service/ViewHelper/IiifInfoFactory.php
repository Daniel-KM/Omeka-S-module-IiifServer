<?php

namespace IiifServer\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use IiifServer\View\Helper\IiifInfo;

/**
 * Service factory for the api view helper.
 */
class IiifInfoFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');
        return new IiifInfo($fileManager);
    }
}
