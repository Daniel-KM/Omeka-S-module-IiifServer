<?php
namespace UniversalViewer\Service\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use UniversalViewer\Controller\MediaController;

class MediaControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');

        $controller = new ImageController($fileManager);

        return $controller;
    }
}
