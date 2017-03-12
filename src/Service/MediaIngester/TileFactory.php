<?php
namespace IiifServer\Service\MediaIngester;

use IiifServer\Media\Ingester\Tile;
use IiifServer\Mvc\Controller\Plugin\TileBuilder;
use Omeka\File\Thumbnailer\ImageMagickThumbnailer;
use Omeka\Service\Cli;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class TileFactory implements FactoryInterface
{
    /**
     * Create the Tile ingester service.
     *
     * @return Tile
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');

        $tileBuilder = new TileBuilder();

        $settings = $services->get('Omeka\Settings');

        $params = [];
        $params['tile_dir'] = $settings->get('iiifserver_image_tile_dir');
        $params['format'] = $settings->get('iiifserver_image_tile_format');

        $processor = $settings->get('iiifserver_image_creator');
        $processors = [
            'Auto' => '',
            'Imagick' => 'imagick',
            'GD' => 'gd',
            'ImageMagick' => 'cli',
        ];
        $params['processor'] = isset($processors[$processor]) ? $processors[$processor] : '';

        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $convertDir = $config['file_manager']['thumbnail_options']['imagemagick_dir'];
        $params['convertPath'] = $this->getConvertPath($cli, $convertDir);
        $params['executeStrategy'] = $config['cli']['execute_strategy'];

        return new Tile($fileManager, $tileBuilder, $params);
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
