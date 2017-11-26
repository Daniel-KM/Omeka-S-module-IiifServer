<?php
namespace IiifServer\Service\Media\Ingester;

use IiifServer\Media\Ingester\Tile;
use IiifServer\Mvc\Controller\Plugin\TileBuilder;
use Interop\Container\ContainerInterface;
use Omeka\File\Thumbnailer\ImageMagick;
use Omeka\Service\Exception\ConfigException;
use Omeka\Stdlib\Cli;
use Zend\ServiceManager\Factory\FactoryInterface;

class TileFactory implements FactoryInterface
{
    /**
     * Create the Tile ingester service.
     *
     * @return Tile
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $validator = $services->get('Omeka\File\Validator');
        $uploader = $services->get('Omeka\File\Uploader');

        $tileBuilder = new TileBuilder();

        $settings = $services->get('Omeka\Settings');

        $params = [];
        $params['tile_dir'] = $settings->get('iiifserver_image_tile_dir');
        $params['tile_type'] = $settings->get('iiifserver_image_tile_type');

        if (empty($params['tile_dir'])) {
            throw new ConfigException('The tile dir is not defined.');
        }

        $processor = $settings->get('iiifserver_image_creator');
        $params['processor'] = $processor === 'Auto' ? '' : $processor;

        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $convertDir = $config['thumbnails']['thumbnailer_options']['imagemagick_dir'];
        $params['convertPath'] = $this->getConvertPath($cli, $convertDir);
        $params['executeStrategy'] = $config['cli']['execute_strategy'];

        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        return new Tile(
            $validator,
            $uploader,
            $tileBuilder,
            $params,
            $basePath
        );
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
