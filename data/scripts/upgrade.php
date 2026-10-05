<?php declare(strict_types=1);

namespace IiifServer;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Common\Stdlib\EasyMeta $easyMeta
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$urlPlugin = $plugins->get('url');
$easyMeta = $services->get('Common\EasyMeta');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$defaultConfig = require dirname(__DIR__, 2) . '/config/module.config.php';
$defaultSettings = $defaultConfig['iiifserver']['config'];

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.93')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.93'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

/**
 * Dispatch a background job during module upgrade.
 *
 * The module state handling (temporarily activating the module so the spawned
 * process can bootstrap it, then restoring its state) is centralized in the
 * Common service; only the module-specific job files to load are provided.
 */
$upgradeJobDispatch = $services->get('Common\UpgradeJobDispatch');
$jobDir = dirname(__DIR__, 2) . '/src/Job/';
$dispatchJobDuringUpgrade = fn (string $jobClass, array $args = []): \Omeka\Entity\Job =>
    $upgradeJobDispatch($jobClass, $args, [
        $jobDir . substr(strrchr('\\' . $jobClass, '\\'), 1) . '.php',
    ]);

$moduleManager = $services->get('Omeka\ModuleManager');
$imageServerModule = $moduleManager->getModule('ImageServer');
if ($imageServerModule) {
    $imageServerVersion = $imageServerModule->getDb('version');
    if ($imageServerVersion && version_compare($imageServerVersion, '3.6.25', '<')) {
        $message = new PsrMessage(
            'The module {module} should be upgraded to version {version} or later.', // @translate
            ['module' => 'ImageServer', 'version' => '3.6.25']
        );
        $messenger->addWarning($message);
    }
}

if (version_compare($oldVersion, '3.5.1', '<')) {
    // The method createTilesMainDir() was removed: tiles are now managed by module ImageServer.
    $settings->set(
        'iiifserver_image_tile_dir',
        $defaultSettings['iiifserver_image_tile_dir']
    );
    $settings->set(
        'iiifserver_image_tile_type',
        $defaultSettings['iiifserver_image_tile_type']
    );
}

if (version_compare($oldVersion, '3.5.8', '<')) {
    $forceHttps = $settings->get('iiifserver_manifest_force_https');
    if ($forceHttps) {
        $settings->set('iiifserver_url_force_from', 'http:');
        $settings->set('iiifserver_url_force_to', 'https:');
    }
    $settings->delete('iiifserver_manifest_force_https');
}

if (version_compare($oldVersion, '3.5.9', '<')) {
    $moduleManager = $services->get('Omeka\ModuleManager');
    $module = $moduleManager->getModule('ArchiveRepertory');
    if ($module) {
        $version = $module->getDb('version');
        // Check if installed.
        if (empty($version)) {
            // Nothing to do.
        } elseif (version_compare($version, '3.15.4', '<')) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                $translate('This version requires Archive Repertory 3.15.4 or greater (used for some 3D views).') // @translate
            );
        }
    }
}

if (version_compare($oldVersion, '3.5.12', '<')) {
    $settings->set(
        'iiifserver_manifest_media_metadata',
        true || $defaultSettings['iiifserver_manifest_media_metadata']
    );
}

if (version_compare($oldVersion, '3.5.14', '<')) {
    $settings->set(
        'iiifserver_manifest_properties_collection',
        $defaultSettings['iiifserver_manifest_properties_collection']
    );
    $settings->set(
        'iiifserver_manifest_properties_item',
        $defaultSettings['iiifserver_manifest_properties_item']
    );
    $value = $settings->get('iiifserver_manifest_media_metadata');
    $settings->set(
        'iiifserver_manifest_properties_media',
        $value === '0' ? ['none'] : $defaultSettings['iiifserver_manifest_properties_media']
    );
    $settings->delete('iiifserver_manifest_media_metadata');
}

if (version_compare($oldVersion, '3.6.0', '<')) {
    $message = new PsrMessage(
        'The module IIIF Server was split into two modules: {link_url}IIIF Server{link_end}, that creates iiif manifest, and {link_url_2}Image Server{link_end}, that provides the tiled images. In that way, it is simpler to use an external image server via core media "IIIF Image". The upgrade is automatic, but you need to install the two modules.', // @translate
        [
            'link_url' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer" target="_blank" rel="noopener">',
            'link_url_2' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-ImageServer" target="_blank" rel="noopener">',
            'link_end' => '</a>',
        ]
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);

    $property = $settings->get('iiifserver_manifest_license_property');
    $settings->set('iiifserver_manifest_rights_property', $property);
    $settings->delete('iiifserver_manifest_license_property');

    $default = $settings->get('iiifserver_manifest_license_default');
    if ($default
        && (
            strpos($default, 'https://creativecommons.org/') === 0
            || strpos($default, 'https://rightsstatements.org/') === 0
            || strpos($default, 'http://creativecommons.org/') === 0
            || strpos($default, 'http://rightsstatements.org/') === 0
        )
    ) {
        if ($property) {
            $settings->set('iiifserver_manifest_rights', 'property_or_url');
        } else {
            $settings->set('iiifserver_manifest_rights', 'url');
        }
        $settings->set('iiifserver_manifest_rights_url', $default);
        $settings->set('iiifserver_manifest_rights_text', '');
    } elseif ($default) {
        if ($property) {
            $settings->set('iiifserver_manifest_rights', 'property_or_text');
        } else {
            $settings->set('iiifserver_manifest_rights', 'text');
        }
        $settings->set('iiifserver_manifest_rights_url', '');
    } elseif ($property) {
        $settings->set('iiifserver_manifest_rights', 'property');
        $settings->set('iiifserver_manifest_rights_url', '');
    } else {
        $settings->set('iiifserver_manifest_rights', 'none');
        $settings->set('iiifserver_manifest_rights_url', '');
    }

    if (!$settings->get('iiifserver_manifest_rights_text')) {
        $settings->set('iiifserver_manifest_rights_text', $settings->get('iiifserver_manifest_license_default', true));
    }
    $settings->delete('iiifserver_manifest_license_default');

    $settings->set('iiifserver_manifest_default_version', $settings->get('iiifserver_manifest_version', '2'));
    $settings->delete('iiifserver_manifest_version');
    $settings->set('iiifserver_url_version_add', $settings->get('iiifserver_manifest_version_append', false));
    $settings->delete('iiifserver_manifest_version_append');
    $settings->set('iiifserver_identifier_clean', $settings->get('iiifserver_manifest_clean_identifier', true));
    $settings->delete('iiifserver_manifest_clean_identifier');
    $settings->set('iiifserver_identifier_prefix', '');
    $settings->set('iiifserver_identifier_raw', '');

    $settings->set('iiifserver_manifest_properties_collection_whitelist', $settings->get('iiifserver_manifest_properties_collection', []));
    $settings->delete('iiifserver_manifest_properties_collection');
    $settings->set('iiifserver_manifest_properties_item_whitelist', $settings->get('iiifserver_manifest_properties_item', []));
    $settings->delete('iiifserver_manifest_properties_item');
    $settings->set('iiifserver_manifest_properties_media_whitelist', $settings->get('iiifserver_manifest_properties_media', []));
    $settings->delete('iiifserver_manifest_properties_media');
    $settings->set('iiifserver_manifest_properties_collection_blacklist', []);
    $settings->set('iiifserver_manifest_properties_item_blacklist', []);
    $settings->set('iiifserver_manifest_properties_media_blacklist', []);

    $settings->set('iiifserver_url_service_image', $settings->get('iiifserver_manifest_service_image', ''));
    $settings->delete('iiifserver_manifest_service_image');
    $settings->set('iiifserver_url_service_media', $settings->get('iiifserver_manifest_service_media', ''));
    $settings->delete('iiifserver_manifest_service_media');
    $settings->set('iiifserver_url_force_from', $settings->get('iiifserver_manifest_force_url_from', ''));
    $settings->delete('iiifserver_manifest_force_url_from');
    $settings->set('iiifserver_url_force_to', $settings->get('iiifserver_manifest_force_url_to', ''));
    $settings->delete('iiifserver_manifest_force_url_to');
    $settings->delete('iiifserver_manifest_service_image');
    $settings->delete('iiifserver_manifest_service_media');
    $settings->delete('iiifserver_manifest_service_iiifsearch');
    $settings->delete('iiifserver_image_server_base_url');
    $settings->delete('iiifserver_image_server_api_version');
    $settings->delete('iiifserver_image_server_compliance_level');

    $settings->set('iiifserver_manifest_behavior_property', $settings->get('iiifserver_manifest_viewing_hint_property', ''));
    $settings->delete('iiifserver_manifest_viewing_hint_property');
    $settings->set('iiifserver_manifest_behavior_default', [$settings->get('iiifserver_manifest_viewing_hint_default', 'none')]);
    $settings->delete('iiifserver_manifest_viewing_hint_default');
}

if (version_compare($oldVersion, '3.6.3.2', '<')) {
    $this->updateWhitelist();
}

if (version_compare($oldVersion, '3.6.5.3', '<')) {
    $supportedVersions = $settings->get('iiifserver_manifest_image_api_disabled') ? [] : ['2/2', '3/2'];
    $defaultVersion = $supportedVersions ? $settings->get('imageserver_info_default_version', '2') : '0';
    $settings->set('iiifserver_media_api_default_version', $defaultVersion);
    $settings->set('iiifserver_media_api_supported_versions', $supportedVersions);
    $settings->set('iiifserver_media_api_default_supported_version', $defaultVersion
        ? ['service' => $defaultVersion, 'level' => '2']
        : ['service' => '0', 'level' => '0']
    );
    $settings->delete('iiifserver_manifest_image_api_disabled');

    $message = new PsrMessage(
        'The module IIIF Server is now totally independant from the module Image Server and any other external image server can be used.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'Check the config of the image server, if any, in the config of this module.' // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'The module IIIF Server supports creation of structures through a table-of-contents-like value: see {link_url}readme{link_end}.', // @translate
        [
            'link_url' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents" target="_blank" rel="noopener">',
            'link_end' => '</a>',
        ]
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.8.3', '<')) {
    $message = new PsrMessage(
        'XML Alto is supported natively and it can be displayed as an overlay layer if your viewer supports it.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'The xml media-type should be a precise one: "application/alto+xml", not "text/xml" or "application/xml".', // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'New files are automatically managed, but you may need modules Bulk Edit or Easy Admin to fix old ones, if any.' // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'Badly formatted xml files may be fixed dynamically, but it will affect performance. See {link_url}readme{link_end}.', // @translate
        [
            'link_url' => '<a href="https://github.com/symac/Omeka-S-module-IiifSearch">',
            'link_end' => '</a>',
        ]
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.6.10', '<')) {
    $settings->set('iiifserver_access_resource_skip', false);
    $message = new PsrMessage(
        'An option allows to skip the rights managed by module Access Resource.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.13', '<')) {
    $enableUtf8Fix = ($services->get('Config')['iiifserver']['config']['iiifserver_enable_utf8_fix'] ?? false) === true
        ? 'regex'
        : 'no';
    $settings->delete('iiifserver_enable_utf8_fix');
    $settings->set('iiifserver_xml_fix_mode', $enableUtf8Fix);
    $message = new PsrMessage(
        'A new option allows to fix bad xml and invalid utf-8 characters.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.14', '<')) {
    $message = new PsrMessage(
        'A new option allows to cache manifests in order to delivrate them instantly.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'A new resource block allows to display the iiif manifest link to copy in clipboard.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.17', '<')) {
    $homepage = $settings->get('iiifserver_manifest_homepage', $defaultSettings['iiifserver_manifest_homepage']);
    $settings->set('iiifserver_manifest_homepage', is_array($homepage) ? $homepage : [$homepage]);

    $settings->set('iiifserver_manifest_provider', $defaultSettings['iiifserver_manifest_provider']);

    $message = new PsrMessage(
        'A new option allows to set the provider.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.18', '<')) {
    // Check the optional module Derivative Media for incompatibility.
    if ($this->isModuleActive('DerivativeMedia') && !$this->isModuleVersionAtLeast('DerivativeMedia', '3.4.10')) {
        $message = new \Omeka\Stdlib\Message(
            $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
            'Derivative Media', '3.4.10'
        );
        $messenger->addError($message);
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
    }

    // Fix previous upgrade.
    $homepage = $settings->get('iiifserver_manifest_homepage', $defaultSettings['iiifserver_manifest_homepage']);
    $settings->set('iiifserver_manifest_homepage', is_array($homepage) ? $homepage : [$homepage]);

    $message = new PsrMessage(
        'A new option allows to limit the files types to download.' // @translate
    );
    $messenger->addSuccess($message);

    $settings->set('iiifserver_manifest_cache', true);
    $settings->delete('iiifserver_manifest_cache_derivativemedia');

    $message = new PsrMessage(
        'A new option allows to cache the manifests on save. It is set on, but may be disabled in settings.' // @translate
    );
    $messenger->addSuccess($message);

    $hasUniversalViewer = $this->checkModuleActiveVersion('UniversalViewer', '3.6.8');
    $settings->set('iiifserver_media_api_fix_uv_mp3', $hasUniversalViewer);
    if ($hasUniversalViewer) {
        $message = new PsrMessage(
            'A new option allows to fix playing mp3 with Universal Viewer v4.' // @translate
        );
        $messenger->addSuccess($message);
    }

    /* // Disable caching on upgrade.
    require_once dirname(__DIR__, 2) . '/src/Job/CacheManifests.php';
    $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
    $args = ['query' => []];
    $dispatcher->dispatch(\IiifServer\Job\CacheManifests::class, $args);
    */
}

if (version_compare($oldVersion, '3.6.19', '<')) {
    // IiifServer is the parent module: never block its upgrade because of an
    // older ImageServer. ImageServer enforces its own minimum IiifServer in its
    // preInstall, which is the right direction for the dependency chain.
    if ($this->isModuleActive('ImageServer') && !$this->isModuleVersionAtLeast('ImageServer', '3.6.16')) {
        $message = new PsrMessage(
            'The module {module} should be upgraded to version {version} or later after this upgrade.', // @translate
            ['module' => 'ImageServer', 'version' => '3.6.16']
        );
        $messenger->addWarning($message);
    }

    $settings->set('iiifserver_manifest_summary_property', $settings->get('iiifserver_manifest_description_property', 'template'));
    $settings->delete('iiifserver_manifest_description_property');

    $message = new PsrMessage(
        'The creation of manifests is now a lot quicker and can be done in real time in most of the cases. The cache is still available for big manifests and for instant access.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'If you use the feature "table of contents", a new format was integrated. Management of the old ones will be removed in version 3.6.20. A job is added in module EasyAdmin (version 3.4.17) to do the conversion.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.6.20', '<')) {
    $structureProperty = $settings->get('iiifserver_manifest_structures_property');
    $structurePropertyId = $easyMeta->propertyId($structureProperty);
    if ($structurePropertyId) {
        $qb = $connection->createQueryBuilder();
        $qb
            ->select('COUNT(value.id)')
            ->from('value', 'value')
            ->innerJoin('value', 'item', 'item', 'item.id = value.resource_id')
            ->where('value.property_id = ' . $structurePropertyId)
            ->andWhere('value.value IS NOT NULL')
            ->andWhere('value.value != ""')
            ->orderBy('value.id', 'asc');
        $structures = $connection->executeQuery($qb->getSQL())->fetchOne();
        if ($structures) {
            $job = $dispatchJobDuringUpgrade(\IiifServer\Job\UpgradeStructures::class);
            $message = new PsrMessage(
                'A job was launched to upgrade the format of "table of contents". Replaced tocs will be stored in logs ({link}job #{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
                [
                    'link' => sprintf('<a href="%s">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                    'job_id' => $job->getId(),
                    'link_end' => '</a>',
                    'link_log' => class_exists('Log\Module', false)
                        ? sprintf('<a href="%1$s">', htmlspecialchars($urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])))
                        : sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))),
                ]
            );
            $message->setEscapeHtml(false);
            $messenger->addWarning($message);
        }
    }
}

if (version_compare($oldVersion, '3.6.21', '<')) {
    $this->messageCors();
    $this->messageCache();
}

if (version_compare($oldVersion, '3.6.27', '<')) {
    // To keep existing config for apache.
    if ($settings->get('iiifserver_identifier_apache_preencoding') === null) {
        $settings->set('iiifserver_identifier_apache_preencoding', true);
    }
}

if (version_compare($oldVersion, '3.6.28', '<')) {
    $value = $settings->get('iiifserver_manifest_append_cors_headers');
    if ($value !== null) {
        $settings->set('iiifserver_append_cors_headers', $value);
        $settings->delete('iiifserver_manifest_append_cors_headers');
    }

    // Migrate deprecated settings to the new encode_slash setting.
    // If apache preencoding was enabled, the server likely supports
    // encoded slashes, so enable the new setting.
    $apachePreencoding = (bool) $settings->get('iiifserver_identifier_apache_preencoding', false);
    $settings->set('iiifserver_identifier_encode_slash', $apachePreencoding);
    $settings->delete('iiifserver_identifier_raw');
    $settings->delete('iiifserver_identifier_apache_preencoding');

    $message = new PsrMessage(
        'The settings "iiifserver_identifier_raw" and "iiifserver_identifier_apache_preencoding" have been replaced by "iiifserver_identifier_encode_slash" (auto-detected on config save).' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.6.29', '<')) {
    // Recompute all media dimensions to fix EXIF orientation.
    $args = [
        'query' => [],
        'filter' => 'all',
    ];
    $job = $dispatchJobDuringUpgrade(\IiifServer\Job\MediaDimensions::class, $args);
    $message = new PsrMessage(
        'A job was launched to recompute all media dimensions with EXIF orientation fix ({link}job #{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
        [
            'link' => sprintf('<a href="%s">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
            'job_id' => $job->getId(),
            'link_end' => '</a>',
            'link_log' => class_exists('Log\Module', false)
                ? sprintf('<a href="%1$s">', htmlspecialchars($urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])))
                : sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))),
        ]
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.32', '<')) {
    $message = new PsrMessage(
        'Two new resource blocks are available: "IIIF Viewer" (viewer embedded inline in the page) and "IIIF Viewer Button" (button opening the viewer in a full-page overlay). It embeds OpenSeadragon or the viewers of modules.' // @translate
    );
    $messenger->addSuccess($message);

    // Derive the new skip switch from the existing media types selector: "none"
    // disables rendering completely.
    $mediaTypes = $settings->get('iiifserver_manifest_rendering_media_types') ?: ['all'];
    $settings->set('iiifserver_manifest_rendering_skip', in_array('none', $mediaTypes));

    $message = new PsrMessage(
        'A new option allows to skip the "rendering" links in manifests (used by viewers to display a download button).' // @translate
    );
    $messenger->addSuccess($message);

    if ($this->isModuleActive('Access')) {
        $message = new PsrMessage(
            'Module Access is active: "rendering" links are now exposed only for resources with status "free". Other resources will not advertise a download link in their manifest.' // @translate
        );
        $messenger->addWarning($message);
    }
}

if (version_compare($oldVersion, '3.6.33', '<')) {
    /** @var \Omeka\Settings\SiteSettings $siteSettings */
    $siteSettings = $services->get('Omeka\Settings\Site');
    $defaultSiteSettings = $defaultConfig['iiifserver']['site_settings'] ?? [];
    // Preserve historical look for existing sites: no inline label.
    $upgradeDialog = ['copy_on_click', 'drag_icon', 'what_is_iiif'];

    $sites = $api->search('sites')->getContent();
    foreach ($sites as $site) {
        $siteSettings->setTargetId($site->id());
        if ($siteSettings->get('iiifserver_manifest_link_dialog', null) === null) {
            $siteSettings->set('iiifserver_manifest_link_dialog', $upgradeDialog);
        }
    }

    $message = new PsrMessage(
        'A new per-site option was added to the button IIIF manifest link to allow drag-and-drop and to display a "What is IIIF?" link.' // @translate
    );
    $messenger->addSuccess($message);

    // OCR is now produced exclusively by the IiifSearch module; IiifServer no
    // longer pairs ALTO with images nor repairs OCR XML. Drop the obsolete
    // settings from the table.
    $settings->delete('iiifserver_xml_image_match');
    $settings->delete('iiifserver_xml_fix_mode');

    $message = new PsrMessage(
        'All features related to ocr, text and alto were moved to the module Iiif Search. The module Iiif Search includes now automated processes to manage all the common cases: search, highlight, extraction with or without provided alto or pdf, extraction from images, tei and hocr, management of multi-pages or single pages files, etc.' // @translate
    );
    $messenger->addSuccess($message);

    // The institution logo can now be chosen from the Omeka asset library in
    // addition to the legacy url setting. The asset takes precedence when set.
    if ($settings->get('iiifserver_manifest_logo_default_asset') === null) {
        $settings->set('iiifserver_manifest_logo_default_asset', null);
    }

    $message = new PsrMessage(
        'A new option allows to pick the institution logo from the Omeka asset library. It takes precedence over the legacy url setting when both are set.' // @translate
    );
    $messenger->addSuccess($message);

    // The single multi-checkbox listing supported image api version/level pairs
    // is replaced by one radio per api version, so each version has a single
    // max compliance level (or "not supported"), which is the only meaningful
    // choice.
    // Installs that never saved the config form have no row for the legacy
    // setting: it is resolved at read time from the module default. Use that
    // same default here, otherwise the conversion would disable every version.
    $supported = $settings->get('iiifserver_media_api_supported_versions');
    if ($supported === null) {
        $supported = ['2/2', '3/2'];
    }
    $levels = ['1' => '', '2' => '', '3' => ''];
    foreach ((array) $supported as $versionLevel) {
        $version = strtok((string) $versionLevel, '/');
        $level = strtok('/');
        if (isset($levels[$version]) && $level !== false) {
            // Keep the highest selected level when several were checked.
            $levels[$version] = max($levels[$version], $level);
        }
    }
    foreach ($levels as $version => $level) {
        $settings->set('iiifserver_media_api_supported_version_' . $version, $level);
    }
    $settings->delete('iiifserver_media_api_supported_versions');

    $message = new PsrMessage(
        'The supported image api versions are now set with one option per version, each defining its single max compliance level.' // @translate
    );
    $messenger->addSuccess($message);

    // A version-less manifest request used to cache to "iiif/{id}.manifest.json"
    // (empty version in the path), an orphan never rewritten by the manifest
    // cache job. Now that version-less requests reuse the versioned cache,
    // remove these stale orphans; they are regenerated on demand if needed.
    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    $orphans = glob($basePath . '/iiif/*.manifest.json') ?: [];
    foreach ($orphans as $orphan) {
        @unlink($orphan);
    }

    $message = new PsrMessage(
        'Removed {count} stale version-less manifest cache files.', // @translate
        ['count' => count($orphans)]
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.34', '<')) {
    // The manifests are now cached in two variants when the module Access is
    // used: without the reserved medias for the unauthorized visitors, with all
    // medias for the authorized ones. The existing files contain all medias
    // whatever the status, so they are removed and rebuilt on the fly.
    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    $removed = 0;
    foreach ([2, 3] as $version) {
        foreach (glob($basePath . '/iiif/' . $version . '/*.manifest.json') ?: [] as $filepath) {
            $removed += (int) @unlink($filepath);
        }
    }
    $message = new PsrMessage(
        'Removed {count} cached manifests: they are rebuilt with or without the reserved medias, according to the rights of the visitor.', // @translate
        ['count' => $removed]
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The table of contents of a tei file attached to an item (module IIIF Search) can be used as structures of the manifest, with or instead of the property (option "Structures from property and TEI file").' // @translate
    );
    $messenger->addSuccess($message);
}
