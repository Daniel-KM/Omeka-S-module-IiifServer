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
    $settings->set('iiifserver_image_tile_dir',
        $defaultSettings['iiifserver_image_tile_dir']);
    $settings->set('iiifserver_image_tile_type',
        $defaultSettings['iiifserver_image_tile_type']);
}

if (version_compare($oldVersion, '3.5.8', '<')) {
    $forceHttps = $settings->get('iiifserver_manifest_force_https');
    if ($forceHttps) {
        $settings->set('iiifserver_manifest_force_url_from', 'http:');
        $settings->set('iiifserver_manifest_force_url_to', 'https:');
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
                $t->translate('This version requires Archive Repertory 3.15.4 or greater (used for some 3D views).')); // @translate
        }
    }
}
