<?php
namespace IiifServer\Service\Controller;

use IiifServer\Controller\ImageController;
use Interop\Container\ContainerInterface;
use Omeka\File\Thumbnailer\ImageMagick;
use Omeka\Stdlib\Cli;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $store = $services->get('Omeka\File\Store');
        $moduleManager = $services->get('Omeka\ModuleManager');
        $translator = $services->get('MvcTranslator');

        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $convertDir = $config['thumbnails']['thumbnailer_options']['imagemagick_dir'];

        $commandLineArgs = [];
        $commandLineArgs['cli'] = $cli;
        $commandLineArgs['convertPath'] = $this->getConvertPath($cli, $convertDir);
        $commandLineArgs['executeStrategy'] = $config['cli']['execute_strategy'];

        $basePath = OMEKA_PATH . DIRECTORY_SEPARATOR . 'files';

        $controller = new ImageController(
            $tempFileFactory,
            $store,
            $moduleManager,
            $translator,
            $commandLineArgs,
            $basePath
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
            ? $cli->validateCommand($convertDir, ImageMagick::CONVERT_COMMAND)
            : $cli->getCommandPath(ImageMagick::CONVERT_COMMAND);
        return (string) $convertPath;
    }
}
