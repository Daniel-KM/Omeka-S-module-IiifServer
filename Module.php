<?php
namespace UniversalViewer;

use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\MvcEvent;

class Module extends AbstractModule {
    protected $_settings = array(
        'universalviewer_append_collections_show' => true,
        'universalviewer_append_items_show' => true,
        'universalviewer_max_dynamic_size' => 50000000,
        'universalviewer_licence' => 'http://www.example.org/license.html',
        'universalviewer_attribution' => 'Provided by Example Organization',
        'universalviewer_class' => '',
        'universalviewer_width' => '95%',
        'universalviewer_height' => '600px',
        'universalviewer_locale' => 'en-GB:English (GB),fr-FR:French',
        'universalviewer_iiif_creator' => 'Auto',
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
            throw new ModuleCannotInstallException($t->translate('At least one graphic processor (GD or ImageMagick) is required to use the UniversalViewer.'));
        }

        $js = dirname(__FILE__)
            . DIRECTORY_SEPARATOR . 'view'
            . DIRECTORY_SEPARATOR . 'shared'
            . DIRECTORY_SEPARATOR . 'javascripts'
            . DIRECTORY_SEPARATOR . 'uv'
            . DIRECTORY_SEPARATOR . 'lib'
            . DIRECTORY_SEPARATOR . 'embed.js';
        if (!file_exists($js)) {
            throw new ModuleCannotInstallException($t->translate("UniversalViewer library should be installed. See module's installation documentation."));
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

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach('Omeka\Controller\Site\Item',
            'view.show.after', array($this, 'displayUniversalViewer'));
        $sharedEventManager->attach('Omeka\Controller\Site\Item',
            'view.browse.after', array($this, 'displayUniversalViewer'));
    }

    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getConfigForm(PhpRenderer $renderer) {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        $translator = $serviceLocator->get('MvcTranslator');
        $test = $settings->get('UniversalViewerTest');

        $vars = array(
            'settings' => $settings,
            't' => $translator,
            'processors' => $this->_getProcessors(),
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
