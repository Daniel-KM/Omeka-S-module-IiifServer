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

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.62')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.62'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.5.1', '<')) {
    $this->createTilesMainDir($services);
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
    $settings->delete('iiifserver_manifest_default_version');
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
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
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
    if ($this->isModuleActive('ImageServer') && !$this->isModuleVersionAtLeast('ImageServer', '3.6.16')) {
        $message = new \Omeka\Stdlib\Message(
            $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
            'ImageServer', '3.6.16'
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
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
        // Module classes are not available during upgrade.
        $qb = $connection->createQueryBuilder();
        $qb
            ->select('COUNT(value.id)')
            ->from('value', 'value')
            ->innerJoin('value', 'item', 'item', 'item.id = value.resource_id')
            ->where('value.property_id = ' . $structurePropertyId)
            ->andWhere('value.value IS NOT NULL')
            ->andWhere('value.value != ""')
            ->orderBy('value.id', 'asc');
        $structures = $connection->executeQuery($qb)->fetchOne();
        if ($structures) {
            require_once dirname(__DIR__, 2) . '/src/Job/UpgradeStructures.php';
            $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
            $job = $dispatcher->dispatch(\IiifServer\Job\UpgradeStructures::class);
            $message = new PsrMessage(
                'A job was launched to upgrade the format of "table of contents". Replaced tocs will be stored in logs ({link}job #{job_id}{link_end}, {link_log}logs{link_end}).', // @translate' // @translate
                [
                    'link' => sprintf('<a href="%s">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                    'job_id' => $job->getId(),
                    'link_end' => '</a>',
                    'link_log' => class_exists('Log\Entity\Log')
                        ? sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                        : sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
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
