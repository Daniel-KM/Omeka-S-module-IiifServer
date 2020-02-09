<?php

/*
 * Copyright 2015-2020 Daniel Berthereau
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

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use IiifServer\Form\ConfigForm;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            ->allow(
                null,
                [
                    Controller\PresentationController::class,
                ]
            );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $translate = $renderer->plugin('translate');
        return '<p>'
            . $translate('The module creates manifests with the properties from each resource (item set, item and media).') // @translate
            . ' ' . $translate('The properties below are used when some metadata are missing.') // @translate
            . ' ' . $translate('In all cases, empty properties are not set.') // @translate
            . ' ' . $translate('Futhermore, the event "iiifserver.manifest" is available to change any data.') // @translate
            . '</p>'
            . parent::getConfigForm($renderer);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        if (!parent::handleConfigForm($controller)) {
            return false;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $params = $controller->getRequest()->getPost();

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
    }
}
