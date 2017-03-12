<?php
namespace IiifServer\Service\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use IiifServer\Controller\ImageController;
use Omeka\Service\Cli;
use Omeka\File\Thumbnailer\ImageMagickThumbnailer;

class ImageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');
        $moduleManager = $services->get('Omeka\ModuleManager');
        $translator = $services->get('MvcTranslator');

        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $config['file_manager']['thumbnail_options']['imagemagick_dir'];
        $convertDir = $config['file_manager']['thumbnail_options']['imagemagick_dir'];

        $commandLineArgs = [];
        $commandLineArgs['cli'] = $cli;
        $commandLineArgs['convertPath'] = $this->getConvertPath($cli, $convertDir);
        $commandLineArgs['executeStrategy'] = $config['cli']['execute_strategy'];

        $controller = new ImageController(
            $fileManager,
            $moduleManager,
            $translator,
            $commandLineArgs
        );

        return $controller;
    }

    /**
     * Get the path to the ImageMagick "convert" command.
     *
     * @param Cli $cli
     * @param string $convertDir
     * @return string
     */
    protected function getConvertPath(Cli $cli, $convertDir)
    {
        $convertPath = $convertDir
            ? $cli->validateCommand($convertDir, ImageMagickThumbnailer::CONVERT_COMMAND)
            : $cli->getCommandPath(ImageMagickThumbnailer::CONVERT_COMMAND);
        return (string) $convertPath;
    }
}
