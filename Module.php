<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
 * Copyright 2015-2017 BibLibre
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
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Zend\EventManager\Event;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{

    protected $settings = array(
        'iiifserver_manifest_description_property' => 'dcterms:bibliographicCitation',
        'iiifserver_manifest_attribution_property' => '',
        'iiifserver_manifest_attribution_default' => 'Provided by Example Organization',
        'iiifserver_manifest_license_property' => 'dcterms:license',
        'iiifserver_manifest_license_default' => 'http://www.example.org/license.html',
        'iiifserver_manifest_logo_default' => '',
        'iiifserver_manifest_force_https' => false,
        'iiifserver_image_creator' => 'Auto',
        'iiifserver_image_max_size' => 10000000,
    );

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

        $processors = $this->listProcessors($serviceLocator);
        if (count($processors) == 1) {
            throw new ModuleCannotInstallException($t->translate('At least one graphic processor (GD or ImageMagick) is required to use the IIIF Server.')); // @translate
        }

        // Convert settings from old releases of Universal Viewer, if installed.
        $module = $moduleManager->getModule('UniversalViewer');
        if ($module) {
            $version = $module->getDb('version');
            if (version_compare($version, '3.4.3', '<')) {
                $messenger->addWarning(
                    $t->translate('Warning: The module Universal Viewer was not upgraded to version 3.4.3.')
                    . ' ' . $t->translate('The settings are set to default.'));
            } elseif (version_compare($version, '3.4.3', '=')) {
                $messenger->addSuccess(
                    $t->translate('The settings were upgraded from Universal Viewer 3.4.3.')
                    . ' ' . $t->translate('You can now upgrade Universal Viewer to 3.5.'));

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
                    $this->settings[$iiifSetting] = $settings->get($uvSetting);
                }
            }
        }

        foreach ($this->settings as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');

        foreach ($this->settings as $name => $value) {
            $settings->delete($name);
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $serviceLocator = $this->getServiceLocator();
        $formElementManager = $serviceLocator->get('FormElementManager');
        $form = $formElementManager->get(ConfigForm::class);

        $messenger = new Messenger();
        $processors = $this->listProcessors();
        if (count($processors) == 1) {
            $messenger->addError(
                'Warning: No graphic library is installed: the IIIF Server can’t work.'); // @translate
        }
        if (!isset($processors['Imagick'])) {
            $messenger->addWarning(
                'Warning: Imagick is not installed: Only standard images (jpg, png, gif and webp) will be processed.'); // @translate
        }

        // In this form, fieldsets are only used for the view.
        $vars = [];
        $vars['form'] = $form;
        return $renderer->render('admin/iiif-server/config-form.phtml', $vars);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();
        // Manage fieldsets of params automatically (only used for the view).
        foreach ($params as $name => $value) {
            if (isset($this->settings[$name])) {
                $settings->set($name, $value);
            } elseif (is_array($value)) {
                foreach ($value as $subname => $subvalue) {
                    if (isset($this->settings[$subname])) {
                        $settings->set($subname, $subvalue);
                    }
                }
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
            $processors['Imagick'] = 'ImageMagick';
        }
        return $processors;
    }
}
