<?php declare(strict_types=1);

namespace IiifServer\Job;

use IiifServer\Iiif\AbstractResourceType;
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
        $iiifAccessCache = $services->get('ControllerPluginManager')->get('iiifAccessCache');
        $iiifManifest2 = $viewHelpers->get('iiifManifest2');
        $iiifManifest3 = $viewHelpers->get('iiifManifest3');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/item/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $query = $this->getArg('query') ?: [];
        if (is_string($query)) {
            $sQuery = [];
            parse_str($query, $sQuery);
            $query = $sQuery ?: [];
        }

        $ids = $api->search('items', $query, ['returnScalar' => 'id'])->getContent();
        if (!$ids) {
            $this->logger->warn(
                'No items selected.' // @translate
            );
            return;
        }

        // Clear authentication identity so manifests are generated as anonymous
        // user: cached manifests should only contain public media, not private
        // ones.
        $auth = $services->get('Omeka\AuthenticationService');
        $auth->clearIdentity();

        foreach ([2, 3] as $version) {
            if (!$this->ensureDirectory("$basePath/iiif/$version")) {
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
            $created = 0;

            // An item with a reserved resource needs a second manifest, built
            // with all medias for the authorized visitors. An item with only
            // free resources, or any item without the module Access, has a
            // single manifest.
            $accessFilters = [AbstractResourceType::ACCESS_FILTER_FREE];
            if ($iiifAccessCache->reservedResourceId((int) $itemId)) {
                $accessFilters[] = AbstractResourceType::ACCESS_FILTER_ALL;
            } else {
                // The item has no reserved resource any more: remove the second
                // manifest, that would stay orphan and outdated.
                foreach ([2, 3] as $version) {
                    $orphan = $iiifAccessCache->filepath($basePath, (string) $version, (int) $itemId, AbstractResourceType::ACCESS_FILTER_ALL);
                    if (file_exists($orphan) && is_writeable($orphan)) {
                        @unlink($orphan);
                    }
                }
            }

            foreach ([2, 3] as $version) foreach ($accessFilters as $accessFilter) {
                $filepath = $iiifAccessCache->filepath($basePath, (string) $version, (int) $itemId, $accessFilter);
                $relativePath = mb_substr($filepath, mb_strlen($basePath));
                if (file_exists($filepath)
                    && (!is_writeable($filepath) || !unlink($filepath))
                ) {
                    $this->logger->err(
                        'Item #{item_id}: Unable to remove existing file "{file}". Manifest version {version} skipped.', // @translate
                        ['item_id' => $itemId, 'file' => $relativePath, 'version' => $version]
                    );
                    continue;
                }

                try {
                    /** @var \Omeka\Api\Representation\ItemRepresentation $item */
                    $item = $api->read('items', $itemId)->getContent();
                } catch (\Throwable $e) {
                    continue;
                }

                try {
                    $manifest = $version === 2
                        ? $iiifManifest2($item, $accessFilter)
                        : $iiifManifest3($item, $accessFilter);
                } catch (\Throwable $e) {
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
                if ($result) {
                    ++$created;
                } else {
                    $failed = true;
                    $this->logger->err(
                        'Item #{item_id}: Unable to store the manifest version {version}.', // @translate
                        ['item_id' => $itemId, 'version' => $version]
                    );
                }
            }

            // Don't add a message when failed or skipped: already done.
            if (!$failed) {
                if ($created <= 1) {
                    $this->logger->info(
                        'Item #{item_id}: iiif manifest created successfully.', // @translate
                        ['item_id' => $itemId]
                    );
                } else {
                    $this->logger->info(
                        'Item #{item_id}: {count} iiif manifests created successfully.', // @translate
                        ['item_id' => $itemId, 'count' => $created]
                    );
                }
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
