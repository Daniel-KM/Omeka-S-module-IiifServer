<?php
namespace IiifServer\Job;

use IiifServer\Mvc\Controller\Plugin\TileBuilder;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\Thumbnailer\ImageMagick;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception\InvalidArgumentException;
use Omeka\Job\Exception\RuntimeException;
use Omeka\Stdlib\Cli;
use Omeka\Stdlib\Message;

class Tiler extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $cli = $services->get('Omeka\Cli');
        $settings = $services->get('Omeka\Settings');
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $tileDir = $settings->get('iiifserver_image_tile_dir');
        if (empty($tileDir)) {
            throw new InvalidArgumentException('The tile dir is not defined.'); // @translate
        }

        // Get the storage path of the source to use for the tiling.
        $media = $this->getMedia();
        if (empty($media)) {
            throw new InvalidArgumentException('The media to tile cannot be identified.'); // @translate
        }

        $source = $basePath
            . DIRECTORY_SEPARATOR . 'original'
            . DIRECTORY_SEPARATOR . $media->filename();
        if (!file_exists($source)) {
            $this->endJob();
            throw new InvalidArgumentException('The media file to tile cannot be found.'); // @translate
        }

        $params = [];
        $params['tile_dir'] = $tileDir;
        $params['tile_type'] = $settings->get('iiifserver_image_tile_type');

        $processor = $settings->get('iiifserver_image_creator');
        $params['processor'] = $processor === 'Auto' ? '' : $processor;

        $convertDir = $config['thumbnails']['thumbnailer_options']['imagemagick_dir'];
        $params['convertPath'] = $this->getConvertPath($cli, $convertDir);
        $params['executeStrategy'] = $config['cli']['execute_strategy'];

        // When a specific store or Archive Repertory are used, the storage id
        // may contain a subdir, so it should be added. There is no change with
        // the default simple storage id.
        $storageId = $media->storageId();
        $params['storageId'] = basename($storageId);
        $tileDir = $basePath . DIRECTORY_SEPARATOR . $tileDir;
        $tileDir = dirname($tileDir . DIRECTORY_SEPARATOR . $storageId);

        $tileBuilder = new TileBuilder();
        $result = $tileBuilder($source, $tileDir, $params);

        $this->endJob($result);
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

    /**
     * Get media via the job id.
     *
     * @return MediaRepresentation|null
     */
    protected function getMedia()
    {
        // If no media, the default process may be not finished, so wait 60 sec.
        $mediaId = $this->getMediaIdViaSql();
        if (empty($mediaId)) {
            sleep(60);
            $mediaId = $this->getMediaIdViaSql();
            if (empty($mediaId)) {
                return;
            }
        }

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $media = $api->read('media', $mediaId)->getContent();
        return $media;
    }

    /**
     * Get the media of the current job via sql.
     *
     * @return int|null
     */
    protected function getMediaIdViaSql()
    {
        $jobId = (int) $this->job->getId();
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $sql = <<<SQL
SELECT id FROM media WHERE data = '{"job":$jobId}' LIMIT 1
SQL;
        return $connection->fetchColumn($sql);
    }

    /**
     * Check if the media still exists and clean data of the media.
     *
     * @param array|null $result
     */
    protected function endJob($result = null)
    {
        $mediaId = $this->getMediaIdViaSql();

        // Clean the tiles if the media was removed between upload and the end
        // of the tiling.
        if (empty($mediaId)) {
            if (!empty($result['tile_file'])) {
                @unlink($result['tile_file']);
            }
            if (!empty($result['tile_dir'])) {
                $this->rrmdir($result['tile_dir']);
            }
            return;
        }

        // Clean media data. They cannot be updated via api, so use a query.
        // TODO Use doctrine repository.
        $mediaId = (int) $mediaId;
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $sql = <<<SQL
UPDATE media SET data = NULL WHERE id = $mediaId
SQL;
        $connection->exec($sql);

        // If there is an issue in the tiling itself, the cleaning should be done.
        if ($result && empty($result['result'])) {
            throw new RuntimeException(new Message(
                'An error occurred during the tiling of media #%d.', // @translate
                $mediaId
            ));
        }
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dir Directory name.
     * @return bool
     */
    private function rrmdir($dir)
    {
        if (!file_exists($dir)
            || !is_dir($dir)
            || !is_readable($dir)
            || !is_writable($dir)
        ) {
            return false;
        }

        $scandir = scandir($dir);
        if (!is_array($scandir)) {
            return false;
        }

        $files = array_diff($scandir, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                unlink($path);
            }
        }

        return @rmdir($dir);
    }
}
