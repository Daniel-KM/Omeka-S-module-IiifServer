<?php
namespace IiifServer\Service\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use IiifServer\Controller\ImageController;

class ImageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');
        $moduleManager = $services->get('Omeka\ModuleManager');
        $translator = $services->get('MvcTranslator');
        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $convertDir = $config['file_manager']['thumbnail_options']['imagemagick_dir'];

        $controller = new ImageController(
            $fileManager,
            $moduleManager,
            $translator,
            $cli,
            $convertDir
        );

        return $controller;
    }
}
