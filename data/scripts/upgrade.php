<?php
namespace IiifServer;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $oldVersion
 * @var string $newVersion
 */
$services = $serviceLocator;

/**
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Api\Manager $api
 * @var array $config
 */
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$api = $services->get('Omeka\ApiManager');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
$settings = $services->get('Omeka\Settings');

if (version_compare($oldVersion, '3.5.1', '<')) {
    $this->createTilesMainDir($serviceLocator);
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
    $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
    $t = $serviceLocator->get('MvcTranslator');
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

    if (!$settings->set('iiifserver_manifest_rights_text')) {
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
}
