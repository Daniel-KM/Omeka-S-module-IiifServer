<?php

/*
 * Copyright 2015  Daniel Berthereau
 * Copyright 2016  BibLibre
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace UniversalViewer;

use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\MvcEvent;
use Omeka\Mvc\Controller\Plugin\Messenger;

class Module extends AbstractModule {
    protected $_settings = array(
        'universalviewer_manifest_description_property' => 'dcterms:bibliographicCitation',
        'universalviewer_manifest_attribution_property' => '',
        'universalviewer_manifest_attribution_default' => 'Provided by Example Organization',
        'universalviewer_manifest_license_property' => 'dcterms:license',
        'universalviewer_manifest_license_default' => 'http://www.example.org/license.html',
        'universalviewer_manifest_logo_default' => '',
        'universalviewer_alternative_manifest_property' => '',
        'universalviewer_append_item_set_show' => true,
        'universalviewer_append_item_show' => true,
        'universalviewer_class' => '',
        'universalviewer_style' => 'background-color: #000; height: 600px;',
        'universalviewer_locale' => 'en-GB:English (GB),fr:French',
        'universalviewer_iiif_creator' => 'Auto',
        'universalviewer_iiif_max_size' => 10000000,
        'universalviewer_force_https' => false,
    );

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(null, 'UniversalViewer\Controller\Player');
        $acl->allow(null, 'UniversalViewer\Controller\Presentation');
        $acl->allow(null, 'UniversalViewer\Controller\Image');
        $acl->allow(null, 'UniversalViewer\Controller\Media');
    }

    public function install(ServiceLocatorInterface $serviceLocator) {
        $t = $serviceLocator->get('MvcTranslator');

        $processors = $this->_getProcessors($serviceLocator);
        if (count($processors) == 1) {
            throw new ModuleCannotInstallException($t->translate('At least one graphic processor (GD or ImageMagick) is required to use the UniversalViewer.')); // @translate
        }

        $js = __DIR__ . '/asset/js/uv/lib/embed.js';
        if (!file_exists($js)) {
            throw new ModuleCannotInstallException($t->translate('UniversalViewer library should be installed. See module’s installation documentation.')); // @translate
        }

        $settings = $serviceLocator->get('Omeka\Settings');

        foreach ($this->_settings as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator) {
        $settings = $serviceLocator->get('Omeka\Settings');

        foreach ($this->_settings as $name => $value) {
            $settings->delete($name);
        }
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        //fix the double json encoding that was stored
        if (version_compare($oldVersion, '3.4.1', '<')) {
            $settings = $serviceLocator->get('Omeka\Settings');

            $settings->set('universalviewer_manifest_description_property',
                $this->_settings['universalviewer_manifest_description_property']);

            $settings->set('universalviewer_manifest_attribution_property',
                $this->_settings['universalviewer_manifest_attribution_property']);

            $settings->set('universalviewer_manifest_attribution_default',
                $settings->get('universalviewer_attribution'));
            $settings->delete('universalviewer_attribution');

            $settings->set('universalviewer_manifest_license_property',
                $this->_settings['universalviewer_manifest_license_property']);

            $settings->set('universalviewer_manifest_license_default',
                $settings->get('universalviewer_licence'));
            $settings->delete('universalviewer_licence');

            $settings->set('universalviewer_manifest_logo_default',
                $this->_settings['universalviewer_manifest_logo_default']);

            $settings->set('universalviewer_append_item_show',
                $settings->get('universalviewer_append_items_show'));
            $settings->delete('universalviewer_append_items_show');

            $settings->set('universalviewer_append_item_set_show',
                $settings->get('universalviewer_append_collections_show'));
            $settings->delete('universalviewer_append_collections_show');

            $style = $this->_settings['universalviewer_style'];
            $width = $settings->get('universalviewer_width') ?: '';
            if (!empty($width)) {
                $width = ' width:' . $width . ';';
            }
            $height = $settings->get('universalviewer_height') ?: '';
            if (!empty($height)) {
                $style = strtok($style, ';');
                $height = ' height:' . $height . ';';
            }
            $settings->set('universalviewer_style', $style . $width . $height);
            $settings->delete('universalviewer_width');
            $settings->delete('universalviewer_height');

            $settings->set('universalviewer_iiif_max_size',
                $settings->get('universalviewer_max_dynamic_size'));
            $settings->delete('universalviewer_max_dynamic_size');
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        if ($settings->get('universalviewer_append_item_show')) {
            $sharedEventManager->attach('Omeka\Controller\Site\Item',
                'view.show.after', array($this, 'displayUniversalViewer'));
        }

        // Note: there is no item-set show, but a special case for items browse.
        if ($settings->get('universalviewer_append_item_set_show')) {
            $sharedEventManager->attach('Omeka\Controller\Site\Item',
                'view.browse.after', array($this, 'displayUniversalViewer'));
        }
    }

    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getConfigForm(PhpRenderer $renderer) {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        $translator = $serviceLocator->get('MvcTranslator');

        $messenger = new Messenger();
        $processors = $this->_getProcessors();

        if (count($processors) == 1) {
            $messenger->addError($translator->translate('Warning: No graphic library is installed: Universaliewer can’t work.')); // @translate
        }

        if (!isset($processors['Imagick'])) {
            $messenger->addWarning($translator->translate('Warning: Imagick is not installed: Only standard images (jpg, png, gif and webp) will be processed.')); // @translate
        }

        $vars = array(
            'settings' => $settings,
            't' => $translator,
            'processors' => $processors,
        );
        return $renderer->render('config-form', $vars);
    }

    public function handleConfigForm(AbstractController $controller) {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function displayUniversalViewer(Event $event)
    {
        $view = $event->getTarget();
        if (isset($view->item)) {
            echo $view->universalViewer($view->item);
        } elseif (isset($view->itemSet)) {
            echo $view->universalViewer($view->itemSet);
        }
    }

    /**
     * Check and return the list of available processors.
     *
     * @return array Associative array of available processors.
     */
    protected function _getProcessors(ServiceLocatorInterface $serviceLocator = null)
    {
        if ($serviceLocator === null) {
            $serviceLocator = $this->getServiceLocator();
        }
        $translator = $serviceLocator->get('MvcTranslator');

        $processors = array(
            'Auto' => $translator->translate('Automatic'),
        );
        if (extension_loaded('gd')) {
            $processors['GD'] = 'GD';
        }
        if (extension_loaded('imagick')) {
            $processors['Imagick'] = 'ImageMagick';
        }

        return $processors;
    }
}
