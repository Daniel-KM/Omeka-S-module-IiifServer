<?php
namespace IiifServer\Job;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception\InvalidArgumentException;
use Omeka\Job\Exception\RuntimeException;
use Omeka\Stdlib\Message;

class Tiler extends AbstractJob
{
    public function perform()
    {
        $media = $this->getMedia();
        if (empty($media)) {
            throw new InvalidArgumentException('The media to tile cannot be identified.'); // @translate
        }

        // Get the storage path of the source to use for the tiling.
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $sourcePath = $basePath . '/original/' . $media->filename();
        if (!file_exists($sourcePath)) {
            $this->endJob();
            throw new InvalidArgumentException('The media file to tile cannot be found.'); // @translate
        }

        $tiler = $services->get('ControllerPluginManager')->get('tiler');
        $result = $tiler($media);

        $this->endJob($result);
    }

    /**
     * Get media via the job id.
     *
     * @return MediaRepresentation|null
     */
    protected function getMedia()
    {
        // If no media, the default process may be not finished, so wait 120 sec.
        $mediaId = $this->getMediaIdViaSql();
        if (empty($mediaId)) {
            sleep(30);
            $mediaId = $this->getMediaIdViaSql();
            if (empty($mediaId)) {
                sleep(30);
                $mediaId = $this->getMediaIdViaSql();
                if (empty($mediaId)) {
                    sleep(30);
                    $mediaId = $this->getMediaIdViaSql();
                    if (empty($mediaId)) {
                        sleep(30);
                        $mediaId = $this->getMediaIdViaSql();
                        if (empty($mediaId)) {
                            return;
                        }
                    }
                }
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
