<?php
namespace IiifServer\Service\ControllerPlugin;

use IiifServer\Mvc\Controller\Plugin\Tiler;
use Interop\Container\ContainerInterface;
use Omeka\File\Thumbnailer\ImageMagick;
use Omeka\Stdlib\Cli;
use Zend\ServiceManager\Factory\FactoryInterface;

class TilerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $tileDir = $settings->get('iiifserver_image_tile_dir');
        if (empty($tileDir)) {
            throw new \RuntimeException('The tile dir is not defined.'); // @translate
        }

        $config = $services->get('Config');
        $cli = $services->get('Omeka\Cli');

        $params = [];
        $params['tile_dir'] = $tileDir;
        $params['tile_type'] = $settings->get('iiifserver_image_tile_type');

        $processor = $settings->get('iiifserver_image_creator');
        $params['processor'] = $processor === 'Auto' ? '' : $processor;

        $convertDir = $config['thumbnails']['thumbnailer_options']['imagemagick_dir'];
        $params['convertPath'] = $this->getConvertPath($cli, $convertDir);
        $params['executeStrategy'] = $config['cli']['execute_strategy'];

        $params['basePath'] = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        return new Tiler($params, $services->get('Omeka\Logger'));
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
