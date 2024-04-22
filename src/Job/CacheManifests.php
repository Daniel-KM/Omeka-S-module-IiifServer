<?php declare(strict_types=1);

namespace IiifServer\Job;

use Omeka\Job\AbstractJob;

class CacheManifests extends AbstractJob
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function perform(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \IiifServer\View\Helper\IiifManifest2 $iiifManifest2
         * @var \IiifServer\View\Helper\IiifManifest3 $iiifManifest3
         */
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');
        $entityManager = $services->get('Omeka\EntityManager');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $viewHelpers = $services->get('ViewHelperManager');
        $iiifManifest2 = $viewHelpers->get('iiifManifest2');
        $iiifManifest3 = $viewHelpers->get('iiifManifest3');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/item/job_' . $this->job->getId());

        $query = $this->getArg('query');

        $ids = $api->search('items', $query, ['returnScalar' => 'id'])->getContent();
        if (!$ids) {
            $this->logger->warn(
                'No items selected.' // @translate
            );
            return;
        }

        foreach ([2, 3] as $version) {
            if (!$this->ensureDirectory(dirname("$basePath/iiif/$version"))) {
                $this->logger->err(
                    'Unable to create directory "{dir}".', // @translate
                    ['dir' => "/iiif/$version/"]
                );
                return;
            }
        }

        foreach (array_values($ids) as $index => $itemId) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'The job was stopped.' // @translate
                    );
                return;
            }

            $failed = false;
            foreach ([2, 3] as $version) {
                $filepath = "$basePath/iiif/$version/$itemId.manifest.json";
                if (file_exists($filepath)
                    && (!is_writeable($filepath) || !unlink($filepath))
                ) {
                    $this->logger->err(
                        'Item #{item_id}: Unable to remove existing file "{file}". Manifest version {version} skipped.', // @translate
                        ['item_id' => $itemId, 'file' => "/iiif/$version/$itemId.manifest.json", 'version' => $version]
                    );
                    continue;
                }

                try {
                    /** @var \Omeka\Api\Representation\ItemRepresentation $item */
                    $item = $api->read('items', $itemId)->getContent();
                } catch (\Exception $e) {
                    continue;
                }

                try {
                    $manifest = $version === 2 ? $iiifManifest2($item) : $iiifManifest3($item);
                } catch (\Exception $e) {
                    $failed = true;
                    if ($item->media()) {
                        $this->logger->err(
                            'Item #{item_id}: Unable to create manifest {version}.', // @translate
                            ['item_id' => $itemId, 'version' => $version]
                        );
                    }
                    continue;
                }

                if (!$manifest) {
                    $failed = true;
                    continue;
                }

                if (!is_string($manifest)) {
                    $manifest = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }

                $result = file_put_contents($filepath, $manifest);
                if (!$result) {
                    $failed = true;
                    $this->logger->err(
                        'Item #{item_id}: Unable to store the manifest version {version}.', // @translate
                        ['item_id' => $itemId, 'version' => $version]
                    );
                }
            }

            // Don't add a message when failed or skipped: already done.
            if (!$failed) {
                $this->logger->info(
                    'Item #{item_id}: iiif manifests created successfully.', // @translate
                    ['item_id' => $itemId]
                );
            }

            if ((++$index % 100) === 0) {
                $entityManager->clear();
            }
        }
    }

    protected function ensureDirectory(string $dirpath): bool
    {
        if (file_exists($dirpath)) {
            return true;
        }
        return mkdir($dirpath, 0775, true);
    }
}
