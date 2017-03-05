<?php
namespace IiifServer\Service\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use IiifServer\Controller\MediaController;

class MediaControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');
        $moduleManager = $services->get('Omeka\ModuleManager');
        $translator = $services->get('MvcTranslator');

        $controller = new MediaController(
            $fileManager,
            $moduleManager,
            $translator
        );

        return $controller;
    }
}
