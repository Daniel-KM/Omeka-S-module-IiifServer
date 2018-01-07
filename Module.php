<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
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

use IiifServer\Form\ConfigForm;
// use IiifServer\Mvc\Controller\Plugin\TileInfo;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(null, 'IiifServer\Controller\Presentation');
        $acl->allow(null, 'IiifServer\Controller\Image');
        $acl->allow(null, 'IiifServer\Controller\Media');
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
        $settings = $serviceLocator->get('Omeka\Settings');
        $t = $serviceLocator->get('MvcTranslator');
        $messenger = new Messenger();

        $checkDeepzoom = __DIR__
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'daniel-km'
            . DIRECTORY_SEPARATOR . 'deepzoom'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'DeepzoomFactory.php';
        $checkZoomify = __DIR__
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'daniel-km'
            . DIRECTORY_SEPARATOR . 'zoomify'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'ZoomifyFactory.php';
        if (!file_exists($checkDeepzoom) || !file_exists($checkZoomify)) {
            throw new ModuleCannotInstallException(
                $t->translate('You should run "composer install" from the root of the module, or install a release with the dependencies.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.')); // @translate
        }

        $processors = $this->listProcessors($serviceLocator);

        $config = include __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['settings'];

        // Convert settings from old releases of Universal Viewer, if installed.
        $module = $moduleManager->getModule('UniversalViewer');
        if ($module) {
            $version = $module->getDb('version');
            // Check if installed.
            if (empty($version)) {
                // Nothing to do.
            } elseif (version_compare($version, '3.4.3', '<')) {
                $messenger->addWarning(
                    $t->translate('Warning: The module Universal Viewer was not upgraded to version 3.4.3.') // @translate
                    . ' ' . $t->translate('The settings are set to default.'));
            } elseif (version_compare($version, '3.4.3', '=')) {
                $messenger->addSuccess(
                    $t->translate('The settings were upgraded from Universal Viewer 3.4.3.') // @translate
                    . ' ' . $t->translate('You can now upgrade Universal Viewer to 3.5.')); // @translate

                foreach ([
                    'universalviewer_manifest_description_property' => 'iiifserver_manifest_description_property',
                    'universalviewer_manifest_attribution_property' => 'iiifserver_manifest_attribution_property',
                    'universalviewer_manifest_attribution_default' => 'iiifserver_manifest_attribution_default',
                    'universalviewer_manifest_license_property' => 'iiifserver_manifest_license_property',
                    'universalviewer_manifest_license_default' => 'iiifserver_manifest_license_default',
                    'universalviewer_manifest_logo_default' => 'iiifserver_manifest_logo_default',
                    'universalviewer_force_https' => 'iiifserver_manifest_force_https',
                    'universalviewer_iiif_creator' => 'iiifserver_image_creator',
                    'universalviewer_iiif_max_size' => 'iiifserver_image_max_size',
                ] as $uvSetting => $iiifSetting) {
                    $defaultSettings[$iiifSetting] = $settings->get($uvSetting);
                }
            }
        }

        $module = $moduleManager->getModule('ArchiveRepertory');
        if ($module) {
            $version = $module->getDb('version');
            // Check if installed.
            if (empty($version)) {
                // Nothing to do.
            } elseif (version_compare($version, '3.15.4', '<')) {
                throw new ModuleCannotInstallException(
                    $t->translate('This version requires Archive Repertory 3.15.4 or greater (used for some 3D views).')); // @translate
            }
        }

        $this->createTilesMainDir($serviceLocator);

        foreach ($defaultSettings as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');

        // Nuke all the tiles.
        $basePath = $serviceLocator->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tileDIr = $settings->get('iiifserver_image_tile_dir');
        if (empty($tileDir)) {
            $messenger = new Messenger();
            $messenger->addWarning('The tile dir is not defined and was not removed.'); // @translate
        } else {
            $tileDir = $basePath . DIRECTORY_SEPARATOR . $tileDir;

            // A security check.
            $removable = $tileDir == realpath($tileDir);
            if ($removable) {
                $this->rrmdir($dir);
            } else {
                $messenger = new Messenger();
                $messenger->addWarning(
                    'The tile dir "%s" is not a real path and was not removed.', $tileDir); // @translate
            }
        }

        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
    }

    protected function manageSettings($settings, $process, $key = 'settings')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    public function warnUninstall(Event $event)
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $basePath = $serviceLocator->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tileDir = $settings->get('iiifserver_image_tile_dir');
        if (empty($tileDir)) {
            $message = new Message('The tile dir is not defined and won’t be removed.'); // @translate
        } else {
            $tileDir = $basePath . DIRECTORY_SEPARATOR . $tileDir;
            $removable = $tileDir == realpath($tileDir);
            if ($removable) {
                $message = 'All tiles will be removed!'; // @translate
            } else {
                $message = new Message('The tile dir "%d" is not a real path and cannot be removed.', $tileDir); // @translate
            }
        }

        // TODO Add a checkbox to let the choice to remove or not.
        $html = '<ul class="messages"><li class="warning">';
        $html .= '<strong>';
        $html .= 'WARNING'; // @translate
        $html .= '</strong>' . ' ';
        $html .= $message;
        $html .= '</li></ul>';
        if ($removable) {
            $html .= '<p>';
            $html .= new Message(
                'To keep the tiles, rename the dir "%s" before and after uninstall.', // @translate
                $tileDir);
            $html .= '</p>';
        }
        echo $html;
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        if (version_compare($oldVersion, '3.5.1', '<')) {
            $config = require __DIR__ . '/config/module.config.php';
            $defaultSettings = $config[strtolower(__NAMESPACE__)]['settings'];
            $settings = $serviceLocator->get('Omeka\Settings');
            $this->createTilesMainDir($serviceLocator);
            $settings->set('iiifserver_image_tile_dir',
                $defaultSettings['iiifserver_image_tile_dir']);
            $settings->set('iiifserver_image_tile_type',
                $defaultSettings['iiifserver_image_tile_type']);
        }

        if (version_compare($oldVersion, '3.5.8', '<')) {
            $settings = $serviceLocator->get('Omeka\Settings');
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
            $messenger = new Messenger();
            $module = $moduleManager->getModule('ArchiveRepertory');
            if ($module) {
                $version = $module->getDb('version');
                // Check if installed.
                if (empty($version)) {
                    // Nothing to do.
                } elseif (version_compare($version, '3.15.4', '<')) {
                    throw new ModuleCannotInstallException(
                        $t->translate('This version requires Archive Repertory 3.15.4 or greater (used for some 3D views).')); // @translate
                }
            }
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );

        $sharedEventManager->attach(
            \Omeka\Entity\Media::class,
            'entity.remove.post',
            [$this, 'deleteMediaTiles']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $formElementManager = $services->get('FormElementManager');

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['settings'];
        foreach ($defaultSettings as $name => $value) {
            $data['iiifserver_manifest'][$name] = $settings->get($name);
            $data['iiifserver_image'][$name] = $settings->get($name);
        }

        $form = $formElementManager->get(ConfigForm::class);
        $form->init();
        $form->setData($data);
        return $renderer->render('iiif-server/module/config', [
            'form' => $form,
        ]);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();

        $form = $this->getServiceLocator()->get('FormElementManager')
            ->get(ConfigForm::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        array_walk_recursive($params, function($v, $k) use (&$params) { $params[$k] = $v; });
        unset($params['iiifserver_manifest']);
        unset($params['iiifserver_image']);

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['settings'];
        foreach ($params as $name => $value) {
            if (isset($defaultSettings[$name])) {
                $settings->set($name, $value);
            }
        }
    }

    /**
     * Check and return the list of available processors.
     *
     * @return array Associative array of available processors.
     */
    protected function listProcessors(ServiceLocatorInterface $serviceLocator = null)
    {
        if ($serviceLocator === null) {
            $serviceLocator = $this->getServiceLocator();
        }
        $translator = $serviceLocator->get('MvcTranslator');

        $processors = [];
        $processors['Auto'] = $translator->translate('Automatic'); // @translate
        if (extension_loaded('gd')) {
            $processors['GD'] = 'GD';
        }
        if (extension_loaded('imagick')) {
            $processors['Imagick'] = 'Imagick';
        }
        // TODO Check if available.
        $processors['ImageMagick'] = 'ImageMagick';
        return $processors;
    }

    protected function createTilesMainDir(ServiceLocatorInterface $serviceLocator)
    {
        // The local store "files" may be hard-coded.
        $config = include __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['settings'];
        $basePath = $serviceLocator->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tileDir = $defaultSettings['iiifserver_image_tile_dir'];
        if (empty($tileDir)) {
            throw new ModuleCannotInstallException(new Message(
                'The tile dir is not defined.', $dir)); // @translate
        }

        $dir = $basePath . DIRECTORY_SEPARATOR . $tileDir;

        // Check if the directory exists in the archive.
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new ModuleCannotInstallException(new Message(
                    'The directory "%s" cannot be created: a file exists.', $dir)); // @translate
            }
            if (!is_writeable($dir)) {
                throw new ModuleCannotInstallException(new Message(
                    'The directory "%s" is not writeable.', $dir)); // @translate
            }
        } else {
            $result = mkdir($dir, 0755, true);
            if (!$result) {
                throw new ModuleCannotInstallException(new Message(
                    'The directory "%s" cannot be created.', $dir)); // @translate
            }
        }

        $messenger = new Messenger();
        $messenger->addSuccess(new Message(
            'The tiles will be saved in the directory "%s".', $dir));

        @copy(
            $baseDir. DIRECTORY_SEPARATOR . 'index.html',
            $dir . DIRECTORY_SEPARATOR . 'index.html');
    }

    /**
     * Delete all tiles associated with a removed Media entity.
     *
     * @param Event $event
     */
    public function deleteMediaTiles(Event $event)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        $basePath = $serviceLocator->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tileDir = $settings->get('iiifserver_image_tile_dir');
        if (empty($tileDir)) {
            $logger = $serviceLocator->get('Omeka\logger');
            $logger->err(new Message('Tile dir is not defined, so media tiles cannot be removed.')); // @translate
            return;
        }

        $tileDir = $basePath . DIRECTORY_SEPARATOR . $tileDir;

        // Remove all files and folders, whatever the format or the source.
        $media = $event->getTarget();
        $storageId = $media->getStorageId();
        $filepath = $tileDir . DIRECTORY_SEPARATOR . $storageId . '.dzi';
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $filepath = $tileDir . DIRECTORY_SEPARATOR . $storageId . '.js';
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $filepath = $tileDir . DIRECTORY_SEPARATOR . $storageId . '_files';
        if (file_exists($filepath) && is_dir($filepath)) {
            $this->rrmdir($filepath);
        }
        $filepath = $tileDir . DIRECTORY_SEPARATOR . $storageId . '_zdata';
        if (file_exists($filepath) && is_dir($filepath)) {
            $this->rrmdir($filepath);
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
