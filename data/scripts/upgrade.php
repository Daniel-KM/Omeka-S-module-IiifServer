<?php declare(strict_types=1);

namespace IiifServer;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$defaultConfig = require dirname(__DIR__, 2) . '/config/module.config.php';
$defaultSettings = $defaultConfig['iiifserver']['config'];

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
    $t = $services->get('MvcTranslator');
    $module = $moduleManager->getModule('ArchiveRepertory');
    if ($module) {
        $version = $module->getDb('version');
        // Check if installed.
        if (empty($version)) {
            // Nothing to do.
        } elseif (version_compare($version, '3.15.4', '<')) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                $t->translate('This version requires Archive Repertory 3.15.4 or greater (used for some 3D views).') // @translate
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
    $message = new Message(
        'The module IIIF Server was split into two modules: %1$sIIIF Server%3$s, that creates iiif manifest, and %2$sImage Server%3$s, that provides the tiled images. In that way, it is simpler to use an external image server via core media "IIIF Image". The upgrade is automatic, but you need to install the two modules.', // @translate
        '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer" target="_blank">',
        '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-ImageServer" target="_blank">',
        '</a>'
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

    $message = new Message(
        'The module IIIF Server is now totally independant from the module Image Server and any other external image server can be used.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'Check the config of the image server, if any, in the config of this module.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'The module IIIF Server supports creation of structures through a table-of-contents-like value: see %sreadme%s.', // @translate
        '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents" target="_blank">',
        '</a>'
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.6.8.3', '<')) {
    $message = new Message(
        'XML Alto is supported natively and it can be displayed as an overlay layer if your viewer supports it.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'The xml media-type should be a precise one: "application/alto+xml", not "text/xml" or "application/xml".', // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'New files are automatically managed, but you may need modules Bulk Edit or Easy Admin to fix old ones, if any.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'Badly formatted xml files may be fixed dynamically, but it will affect performance. See %1$sreadme%2$s.', // @translate
        '<a href="https://github.com/symac/Omeka-S-module-IiifSearch">',
        '</a>'
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.6.10', '<')) {
    $settings->set('iiifserver_access_resource_skip', false);
    $message = new Message(
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
    $message = new Message(
        'A new option allows to fix bad xml and invalid utf-8 characters.' // @translate
    );
    $messenger->addSuccess($message);
}
