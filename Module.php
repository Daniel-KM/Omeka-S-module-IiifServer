<?php declare(strict_types=1);

/*
 * Copyright 2015-2024 Daniel Berthereau
 * Copyright 2016-2017 BibLibre
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace IiifServer;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use IiifServer\Form\ConfigForm;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Entity\Item;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'Common',
    ];

    public function init(ModuleManager $moduleManager): void
    {
        // The autoload doesn’t work with GetId3.
        // @see \IiifServer\Service\ControllerPlugin\MediaDimensionFactory
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            ->allow(
                null,
                [
                    \IiifServer\Controller\MediaController::class,
                    \IiifServer\Controller\NoopServerController::class,
                    \IiifServer\Controller\PresentationController::class,
                ]
            );

        // Re-encode decoded slashes in identifiers so Segment routes match.
        $event->getApplication()->getEventManager()
            ->attach(MvcEvent::EVENT_ROUTE, [$this, 'reencodeIdentifierSlashes'], 1000);
    }

    /**
     * Re-encode decoded slashes in iiif url identifiers.
     *
     * When apache or a reverse proxy does not preserve encoded slashes
     * (%2F), they get decoded to "/" in the path, breaking segment routes
     * that expect the identifier as a single path segment. This listener
     * detects where the identifier ends by looking for known iiif keywords
     * (manifest, canvas, info.json, etc.) from the right of the path, then
     * re-encodes all "/" within the identifier portion.
     *
     * This is a no-op when identifiers contain no decoded slashes (e.g.
     * simple numeric ids or already-encoded identifiers).
     *
     * @see https://iiif.io/api/presentation/3.0/
     * @see https://iiif.io/api/presentation/2.1/
     * @see https://iiif.io/api/image/3.0/
     */
    public function reencodeIdentifierSlashes(MvcEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request instanceof \Laminas\Http\PhpEnvironment\Request) {
            return;
        }

        $path = $request->getUri()->getPath();

        // Quick check: only process iiif presentation/image/media paths.
        if (strpos($path, '/iiif/') === false) {
            return;
        }

        // Skip fixed literal routes (e.g. ixif placeholder).
        if (strpos($path, 'ixif-message') !== false) {
            return;
        }

        // Parse: /iiif[/version][/collection|set]/{identifier}[/suffix]
        // Version is any single digit to be future-proof (v2, v3, v4…).
        if (!preg_match('#^(/iiif(?:/\d+)?)(?:(/collection|/set))?(/[^?]*)$#', $path, $matches)) {
            return;
        }

        $iiifBase = $matches[1];
        $routePrefix = $matches[2] ?? '';
        $remainder = $matches[3] ?? '';

        if ($remainder === '' || $remainder === '/') {
            return;
        }

        // Remove leading slash.
        $remainder = substr($remainder, 1);
        $segments = explode('/', $remainder);
        $count = count($segments);

        // A single segment means no slashes in the identifier.
        if ($count <= 1) {
            return;
        }

        // Known iiif presentation types and keywords that appear after the
        // identifier in the URL path. Used as anchors to detect where the
        // identifier portion ends.
        // @see IiifServer config: route "uri" type constraint.
        static $iiifKeywords = [
            'manifest' => true,
            'info.json' => true,
            'annotation-page' => true,
            'annotation-collection' => true,
            'annotation-list' => true,
            'annotation' => true,
            'canvas-segment' => true,
            'canvas' => true,
            'collection' => true,
            'content-resource' => true,
            'range' => true,
            // Iiif presentation 2.1 types.
            'sequence' => true,
            'layer' => true,
            'list' => true,
            'res' => true,
        ];

        // Scan from the right to find the first iiif keyword.
        // Start at index 1 (the identifier needs at least one segment).
        $suffixCount = 0;
        for ($i = $count - 1; $i >= 1; $i--) {
            if (isset($iiifKeywords[$segments[$i]])) {
                $suffixCount = $count - $i;
                break;
            }
        }

        // If no keyword found, check for Image API request pattern:
        // {id}/{region}/{size}/{rotation}/{quality}.{format}
        // where quality is one of: default, color, gray, bitonal.
        if ($suffixCount === 0 && $count >= 5) {
            if (preg_match('/^(?:default|color|gray|bitonal)\.\w+$/', $segments[$count - 1])) {
                $suffixCount = 4;
            }
        }

        $identifierCount = $count - $suffixCount;
        if ($identifierCount <= 1) {
            return;
        }

        // Re-encode slashes within the identifier portion.
        $identifierParts = array_slice($segments, 0, $identifierCount);
        $encodedIdentifier = implode('%2F', $identifierParts);

        $suffixParts = array_slice($segments, $identifierCount);
        $newRemainder = $suffixParts
            ? $encodedIdentifier . '/' . implode('/', $suffixParts)
            : $encodedIdentifier;

        $newPath = $iiifBase . $routePrefix . '/' . $newRemainder;

        if ($newPath !== $path) {
            $request->getUri()->setPath($newPath);
        }
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.79')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.79'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        // Check the optional module Derivative Media for incompatibility.
        if ($this->isModuleActive('DerivativeMedia') && !$this->isModuleVersionAtLeast('DerivativeMedia', '3.4.10')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Derivative Media', '3.4.10'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        if (!$this->checkDestinationDir($basePath . '/iiif/2')) {
            $message = new PsrMessage(
                'The directory "{directory}" is not writeable.', // @translate
                ['directory' => $basePath . '/iiif']
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
        }

        if (!$this->checkDestinationDir($basePath . '/iiif/3')) {
            $message = new PsrMessage(
                'The directory "{directory}" is not writeable.', // @translate
                ['directory' => $basePath . '/iiif']
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
        }
    }

    protected function postInstall(): void
    {
        $this->updateWhitelist();

        /** @var \Omeka\Settings\Settings $settings */
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        // Set default setting for cors according to the config of the server.
        $corsHeaders = $this->checkCorsHeaders();
        $settings->set('iiifserver_manifest_append_cors_headers', !$corsHeaders);
        if ($corsHeaders > 1) {
            $this->messageCors();
        }
    }

    protected function postUninstall(): void
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $dirPath = $basePath . '/iiif';
        $this->rmDir($dirPath);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Update dimensions of medias on item save.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.hydrate.post',
            [$this, 'handleBeforeSaveItem']
        );

        // TODO Update manifests on media update.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'handleAfterSaveItem']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'handleAfterSaveItem']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.post',
            [$this, 'handleAfterDeleteItem']
        );

        // Add a job to upgrade structures once from v3.
        $sharedEventManager->attach(
            \EasyAdmin\Form\CheckAndFixForm::class,
            'form.add_elements',
            [$this, 'handleEasyAdminJobsForm']
        );
        $sharedEventManager->attach(
            \EasyAdmin\Controller\Admin\CheckAndFixController::class,
            'easyadmin.job',
            [$this, 'handleEasyAdminJobs']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $this->messageCors();

        $translate = $renderer->plugin('translate');
        return '<p>'
            . $translate('The module creates manifests with the properties from each resource (item set, item and media).') // @translate
            . ' ' . $translate('The properties below are used when some metadata are missing.') // @translate
            . ' ' . $translate('In all cases, empty properties are not set.') // @translate
            . ' ' . $translate('Futhermore, the event "iiifserver.manifest" is available to change any data.') // @translate
            . '</p>'
            . $this->getConfigFormAuto($renderer);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $this->messageCache();

        /**
         * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
         * @var \IiifServer\Form\ConfigForm $configForm
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $params = $controller->getRequest()->getPost();

        if (!$this->handleConfigFormAuto($controller)) {
            return false;
        }

        // Form is already validated in parent.
        $form->init();
        $form->setData($params);
        $form->isValid();
        $params = $form->getData();

        // Specific options.
        foreach (['iiifserver_manifest_collection_properties', 'iiifserver_manifest_item_properties', 'iiifserver_manifest_media_properties'] as $key) {
            $value = empty($params[$key]) || in_array('', $params[$key])
                ? []
                : (in_array('none', $params[$key]) ? ['none'] : $params[$key]);
            $settings->set($key, $value);
        }

        $this->normalizeMediaApiSettings($params);

        if (empty($params['fieldset_cache']['process_cache'])
            && empty($params['fieldset_dimensions']['process_dimensions'])
        ) {
            return true;
        }

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);

        if (!empty($params['fieldset_cache']['process_cache'])) {
            $query = [];
            parse_str($params['fieldset_cache']['query_cache'] ?? '', $query);
            $args = ['query' => $query ?: []];
            $job = $dispatcher->dispatch(\IiifServer\Job\CacheManifests::class, $args);
            $message = 'Caching manifests ({link}job #{job_id}{link_end}, {link_log}logs{link_end})'; // @translate
        } elseif (!empty($params['fieldset_dimensions']['process_dimensions'])) {
            $query = [];
            parse_str($params['fieldset_dimensions']['query'] ?? '', $query);
            $args = ['query' => $query ?: []];
            $job = $dispatcher->dispatch(\IiifServer\Job\MediaDimensions::class, $args);
            $message = 'Storing dimensions of images, audio and video ({link}job #{job_id}{link_end}, {link_log}logs{link_end})'; // @translate
        }

        $urlPlugin = $controller->url();
        $message = new PsrMessage(
            $message,
            [
                'link' => sprintf('<a href="%s">',
                    htmlspecialchars($controller->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => class_exists('Log\Module', false)
                    ? sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                    : sprintf('<a href="%1$s" target="_blank">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            ]
        );
        $message->setEscapeHtml(false);
        $controller->messenger()->addSuccess($message);
        return true;
    }

    public function handleBeforeSaveItem(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');

        // Don't run sizing during a batch edit of items, because it runs one
        // job by item and it is slow. A batch process is always partial.
        if ($request->getOption('isPartial')) {
            return;
        }

        /** @var \Omeka\Api\Adapter\ItemAdapter $itemAdapter */
        $itemAdapter = $event->getTarget();
        if (!$itemAdapter->shouldHydrate($request, 'o:media')) {
            return;
        }

        /** @var \Omeka\Entity\Item $item */
        $item = $event->getParam('entity');
        $this->prepareSizeItem($item);
    }

    public function handleAfterSaveItem(Event $event): void
    {
        // Don't run creation of manifests during sizing during a batch edit of
        // items, because it runs one job by item and it is slow.
        // A batch process is always partial.
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        if ($request->getOption('isPartial')) {
            return;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        if (!$settings->get('iiifserver_manifest_cache')) {
            return;
        }

        /** @var \Omeka\Entity\Item $item */
        $item = $event->getParam('response')->getContent();
        $args = [
            'query' => ['id' => $item->getId()],
        ];

        // TODO Use a direct strategy since manifest is created quickly now.

        /** @var \Omeka\Job\Dispatcher $dispatcher */
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $dispatcher->dispatch(\IiifServer\Job\CacheManifests::class, $args);
    }

    public function handleAfterDeleteItem(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $itemId = $request->getId();
        if (!$itemId) {
            return;
        }

        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        foreach ([2, 3] as $version) {
            $filepath = "$basePath/iiif/$version/$itemId.manifest.json";
            if (file_exists($filepath) && is_writeable($filepath)) {
                @unlink($filepath);
            }
        }
    }

    public function handleEasyAdminJobsForm(Event $event): void
    {
        /**
         * @var \EasyAdmin\Form\CheckAndFixForm $form
         * @var \Laminas\Form\Element\Radio $process
         */
        $form = $event->getTarget();
        $fieldset = $form->get('module_tasks');
        $process = $fieldset->get('process');
        $valueOptions = $process->getValueOptions();
        $valueOptions['iiifserver_cache_manifests'] = 'Iiif Server: Cache manifests'; // @translate
        $valueOptions['iiifserver_store_dimensions'] = 'Iiif Server: Store dimensions of medias'; // @translate
        $valueOptions['iiifserver_upgrade_structure'] = 'Iiif Server: Upgrade old tables of contents to new format with four columns (to do only one time for old external manifests)'; // @translate
        $process->setValueOptions($valueOptions);
        $fieldset
            ->add([
                'type' => \Laminas\Form\Fieldset::class,
                'name' => 'iiifserver_cache_manifests',
                'options' => [
                    'label' => 'Options to cache manifests', // @translate
                ],
                'attributes' => [
                    'class' => 'iiifserver_cache_manifests',
                ],
            ])
            ->get('iiifserver_cache_manifests')
            ->add([
                'name' => 'query',
                'type' => \Omeka\Form\Element\Query::class,
                'options' => [
                    'label' => 'Limit cache of manifests to specific items', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_cache_manifests-query',
                ],
            ]);
        $fieldset
            ->add([
                'type' => \Laminas\Form\Fieldset::class,
                'name' => 'iiifserver_store_dimensions',
                'options' => [
                    'label' => 'Options to store dimensions', // @translate
                ],
                'attributes' => [
                    'class' => 'iiifserver_store_dimensions',
                ],
            ])
            ->get('iiifserver_store_dimensions')
            ->add([
                'name' => 'query',
                'type' => \Omeka\Form\Element\Query::class,
                'options' => [
                    'label' => 'Limit storage of dimensions to specific items', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_store_dimensions-query',
                ],
            ]);
        $fieldset
            ->add([
                'type' => \Laminas\Form\Fieldset::class,
                'name' => 'iiifserver_upgrade_structure',
                'options' => [
                    'label' => 'Options to upgrade table of contents', // @translate
                ],
                'attributes' => [
                    'class' => 'iiifserver_upgrade_structure',
                ],
            ])
            ->get('iiifserver_upgrade_structure')
            ->add([
                'name' => 'query',
                'type' => \Omeka\Form\Element\Query::class,
                'options' => [
                    'label' => 'Limit upgrade of tables of contents to specific items', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_upgrade_structure-query',
                ],
            ]);
    }

    public function handleEasyAdminJobs(Event $event): void
    {
        $process = $event->getParam('process');
        if ($process === 'iiifserver_cache_manifests') {
            $params = $event->getParam('params');
            $event->setParam('job', \IiifServer\Job\CacheManifests::class);
            $event->setParam('args', $params['module_tasks']['iiifserver_cache_manifests'] ?? []);
        } elseif ($process === 'iiifserver_store_dimensions') {
            $params = $event->getParam('params');
            $event->setParam('job', \IiifServer\Job\MediaDimensions::class);
            $event->setParam('args', $params['module_tasks']['iiifserver_store_dimensions'] ?? []);
        } elseif ($process === 'iiifserver_upgrade_structure') {
            $params = $event->getParam('params');
            $event->setParam('job', \IiifServer\Job\UpgradeStructures::class);
            $event->setParam('args', $params['module_tasks']['iiifserver_upgrade_structure'] ?? []);
        }
    }

    /**
     * Same in Iiif server and Image server.
     *
     * @see \IiifServer\Module::normalizeMediaApiSettings()
     * @see \ImageServer\Module::normalizeMediaApiSettings()
     */
    protected function normalizeMediaApiSettings(array $params): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Check and normalize image api versions.
        $defaultVersion = $params['iiifserver_media_api_default_version'] ?: '0';
        $has = ['1' => null, '2' => null, '3' => null];
        foreach ($params['iiifserver_media_api_supported_versions'] ?? [] as $supportedVersion) {
            $service = strtok($supportedVersion, '/');
            $level = strtok('/') ?: '0';
            $has[$service] = isset($has[$service]) && $has[$service] > $level
                ? $has[$service]
                : $level;
        }
        $has = array_filter($has);
        if ($defaultVersion && !isset($has[$defaultVersion])) {
            $has[$defaultVersion] = '0';
        }
        ksort($has);
        $supportedVersions = [];
        foreach ($has as $service => $level) {
            $supportedVersions[] = $service . '/' . $level;
        }
        $settings->set('iiifserver_media_api_default_version', $defaultVersion);
        $settings->set('iiifserver_media_api_supported_versions', $supportedVersions);

        // Avoid to do the computation each time for manifest v2, that supports
        // only one service.
        $defaultSupportedVersion = ['service' => '0', 'level' => '0'];
        foreach ($supportedVersions as $supportedVersion) {
            $service = strtok($supportedVersion, '/');
            if ($service === $defaultVersion) {
                $level = strtok('/') ?: '0';
                $defaultSupportedVersion = [
                    'service' => $service,
                    'level' => $level,
                ];
                break;
            }
        }
        $settings->set('iiifserver_media_api_default_supported_version', $defaultSupportedVersion);
    }

    protected function updateWhitelist(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $whitelist = $settings->get('media_type_whitelist', []);
        $whitelist = array_values(array_unique(array_merge(array_values($whitelist), [
            'image/ktx2',
            'model/gltf-binary',
            'model/gltf+json',
            'model/vnd.threejs+json',
            'application/octet-stream',
        ])));
        $settings->set('media_type_whitelist', $whitelist);

        $whitelist = $settings->get('extension_whitelist', []);
        $whitelist = array_values(array_unique(array_merge(array_values($whitelist), [
            'bin',
            'glb',
            'gltf',
            'json',
            'ktx2',
        ])));
        $settings->set('extension_whitelist', $whitelist);
    }

    /**
     * Check how many times the cors header is returned (zero or one).
     *
     * Returning the cors header "Access-Control-Allow-Origin" multiple times
     * disable it.
     */
    protected function checkCorsHeaders(): int
    {
        // Check cors on a default file, because there may not be any media file.
        $services = $this->getServiceLocator();
        $assetUrl = $services->get('ViewHelperManager')->get('assetUrl');
        $checkServerUrl = $assetUrl('css/style.css', 'Omeka', false, true, true);

        // Remove warning, related to proxy or specific domain issue.
        $headers = @get_headers($checkServerUrl, version_compare(PHP_VERSION, '8.0.0', '<') ? 1 : true);
        if (!$headers) {
            return 0;
        }

        return array_key_exists('Access-Control-Allow-Origin', $headers)
            ? count((array) $headers['Access-Control-Allow-Origin'])
            : 0;
    }

    protected function messageCors(): void
    {
        $services = $this->getServiceLocator();

        /** @var \Omeka\Settings\Settings $settings */
        $settings = $services->get('Omeka\Settings');
        $appendCorsHeaders = (bool) $settings->get('iiifserver_manifest_append_cors_headers');

        $plugins = $services->get('ControllerPluginManager');
        $messenger = $plugins->get('messenger');

        $checkCorsHeaders = $this->checkCorsHeaders();
        if (!$checkCorsHeaders && !$appendCorsHeaders) {
            $message = new PsrMessage(
                'The cors headers are disabled. You shall enable them to allow other libraries to access to your manifests.' // @translate
            );
            $messenger->addWarning($message);
        } elseif ($checkCorsHeaders > 1
            || ($checkCorsHeaders === 1 && $appendCorsHeaders)
        ) {
            $message = new PsrMessage(
                'The cors headers are enabled multiple times in the config of the module and in the config of Apache or in the file .htaccess. Duplicating the cors headers makes them disabled. You should disable the option in the config of the module, in the Apache config or in the file .htaccess.' // @translate
            );
            $messenger->addError($message);
        }
    }

    protected function messageCache(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $settings = $services->get('Omeka\Settings');
        $messenger = $plugins->get('messenger');

        $isCacheEnabled = $settings->get('iiifserver_manifest_cache');
        if ($isCacheEnabled) {
            $message = new PsrMessage(
                'When cache is enabled, you should recreate it each time the config is updated.' // @translate
            );
            $messenger->addWarning($message);
        }
    }

    /**
     * Only unsized medias are dimensionned.
     *
     * @todo Get size for external media iiif?
     *
     * @see \IiifServer\Module::prepareSizeItem()
     * @see \IiifServer\Job\MediaDimensions::prepareSize()
     */
    protected function prepareSizeItem(Item $item): void
    {
        $medias = $item->getMedia();
        if (!$medias->count()) {
            return;
        }

        /** @var \IiifServer\Mvc\Controller\Plugin\MediaDimension $mediaDimension */
        $services = $this->getServiceLocator();
        $imageTypes = array_keys($services->get('Config')['thumbnails']['types']);
        $mediaDimension = $services->get('ControllerPluginManager')->get('mediaDimension');

        /** @var \Omeka\Entity\Media $media */
        foreach ($medias as $media) {
            $mainMediaType = strtok((string) $media->getMediaType(), '/');
            if (!in_array($mainMediaType, ['image', 'audio', 'video'])) {
                continue;
            }

            // Keep possible data added by another module.
            $mediaData = $media->getData() ?: [];

            // Don't redo sizing here.
            if (($mainMediaType === 'image' && !empty($mediaData['dimensions']['large']['width']))
                || ($mainMediaType === 'audio' && !empty($mediaData['dimensions']['original']['duration']))
                || ($mainMediaType === 'video' && !empty($mediaData['dimensions']['original']['duration']))
            ) {
                continue;
            }

            // Reset dimensions to make the sizer working.
            // TODO In rare cases, the original file is removed once the thumbnails are built.
            $mediaData['dimensions'] = [];
            $media->setData($mediaData);

            $failedTypes = [];
            foreach ($mainMediaType === 'image' ? $imageTypes : ['original'] as $imageType) {
                $result = $mediaDimension->__invoke($media, $imageType);
                $result = array_filter($result);
                if (!$result) {
                    $failedTypes[] = $imageType;
                }
                // Store the dimensions in all cases (empty array), to know that
                // the dimensions were processed.
                $mediaData['dimensions'][$imageType] = $result;
            }

            if (count($failedTypes)) {
                $this->getServiceLocator()->get('Omeka\Logger')->err(
                    'Item #{item_id} / media #{media_id}: Error getting dimensions for types "{types}".', // @translate
                    [
                        'item_id' => $item->getId(),
                        'media_id' => $media->getId(),
                        'types' => implode('", "', $failedTypes),
                    ]
                );
            }

            $media->setData($mediaData);
        }

        // No flush.
    }
}
