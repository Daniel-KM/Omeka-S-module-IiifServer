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
        $fileLock = new \Common\Stdlib\FileLock();
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

        // The urls of the manifests use the server url, that is unknown when
        // the job is run via cli without it (empty host) or set to a
        // placeholder (localhost), so the cached manifests would be unusable.
        if (!$this->checkServerUrl()) {
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
                // The previous manifest is served until the new one replaces it.
                if (!is_writeable(dirname($filepath))) {
                    $this->logger->err(
                        'Item #{item_id}: Unable to write the file "{file}". Manifest version {version} skipped.', // @translate
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
                    @unlink($filepath);
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
                    @unlink($filepath);
                    continue;
                }

                if (!is_string($manifest)) {
                    $manifest = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }

                $result = $fileLock->write($filepath, $manifest);
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

    /**
     * Check that the server url is a real one, used in the urls of manifests.
     *
     * When the job is run via cli, the server url should be set with the option
     * "--server-url" of the script (core perform-job.php, or task.php of module
     * EasyAdmin, that uses "http://localhost" without it). A local server is
     * allowed only when the job is launched manually by an admin, for a site
     * served on localhost.
     */
    protected function checkServerUrl(): bool
    {
        $serverUrl = $this->getServiceLocator()->get('ViewHelperManager')->get('ServerUrl')->__invoke();
        $host = strtolower((string) parse_url($serverUrl, PHP_URL_HOST));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        if ($host !== '' && (!$isLocal || $this->getArg('manual'))) {
            return true;
        }
        $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
        $this->logger->err(
            'The server url is unknown or local ("{url}"), so the manifests would contain invalid urls. Run the job from the admin interface or via the cli script with the option "--server-url".', // @translate
            ['url' => $serverUrl]
        );
        return false;
    }

    /**
     * Create the directory of the cache and deny the direct web access to it.
     *
     * The cache is read via the controller only, that checks the rights.
     */
    protected function ensureDirectory(string $dirpath): bool
    {
        $directoryManager = $this->getServiceLocator()->get('Common\DirectoryManager');
        return $directoryManager->checkDestinationDir(dirname($dirpath), true)
            && $directoryManager->checkDestinationDir($dirpath, true);
    }
}
