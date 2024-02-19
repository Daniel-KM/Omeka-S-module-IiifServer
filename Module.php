<?php declare(strict_types=1);

/*
 * Copyright 2015-2024 Daniel Berthereau
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

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use IiifServer\Form\ConfigForm;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'Common',
    ];

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        // The autoload doesn’t work with GetId3.
        // @see \IiifServer\Service\ControllerPlugin\MediaDimensionFactory
        require_once __DIR__ . '/vendor/autoload.php';

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            ->allow(
                null,
                [
                    \IiifServer\Controller\MediaController::class,
                    \IiifServer\Controller\NoopServerController::class,
                    \IiifServer\Controller\PresentationController::class,
                ]
            );
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.53')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.53'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        $this->updateWhitelist();
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
            . $this->getConfigFormAuto($renderer);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        /**
         * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
         * @var \IiifServer\Form\ConfigForm $configForm
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $params = $controller->getRequest()->getPost();

        if (!$this->handleConfigFormAuto($controller)) {
            return false;
        }

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

        $this->normalizeMediaApiSettings($params);

        if (empty($params['fieldset_dimensions']['process_dimensions'])) {
            return true;
        }

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);

        $params = ['query' => $params['fieldset_dimensions']['query'] ?: null];
        $job = $dispatcher->dispatch(\IiifServer\Job\MediaDimensions::class, $params);
        $message = 'Storing dimensions of images, audio and video ({link}job #{job_id}{link_end}, {link_log}logs{link_end})'; // @translate
        $message = new PsrMessage(
            $message,
            [
                'link' => sprintf('<a href="%s">',
                    htmlspecialchars($controller->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => $this->isModuleActive('Log')
                    ? sprintf('<a href="%1$s">', $controller->url()->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                    : sprintf('<a href="%1$s">', $controller->url()->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            ]
        );
        $message->setEscapeHtml(false);
        $controller->messenger()->addSuccess($message);
        return true;
    }

    /**
     * Same in Iiif server and Image server.
     *
     * @see \ImageServer\Module::normalizeMediaApiSettings()
     */
    protected function normalizeMediaApiSettings(array $params): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Check and normalize image api versions.
        $defaultVersion = $params['iiifserver_media_api_default_version'] ?: '0';
        $has = ['1' => null, '2' => null, '3' => null];
        foreach ($params['iiifserver_media_api_supported_versions'] ?? [] as $supportedVersion) {
            $service = strtok($supportedVersion, '/');
            $level = strtok('/') ?: '0';
            $has[$service] = isset($has[$service]) && $has[$service] > $level
                ? $has[$service]
                : $level;
        }
        $has = array_filter($has);
        if ($defaultVersion && !isset($has[$defaultVersion])) {
            $has[$defaultVersion] = '0';
        }
        ksort($has);
        $supportedVersions = [];
        foreach ($has as $service => $level) {
            $supportedVersions[] = $service . '/' . $level;
        }
        $settings->set('iiifserver_media_api_default_version', $defaultVersion);
        $settings->set('iiifserver_media_api_supported_versions', $supportedVersions);

        // Avoid to do the computation each time for manifest v2, that supports
        // only one service.
        $defaultSupportedVersion = ['service' => '0', 'level' => '0'];
        foreach ($supportedVersions as $supportedVersion) {
            $service = strtok($supportedVersion, '/');
            if ($service === $defaultVersion) {
                $level = strtok('/') ?: '0';
                $defaultSupportedVersion = [
                    'service' => $service,
                    'level' => $level,
                ];
                break;
            }
        }
        $settings->set('iiifserver_media_api_default_supported_version', $defaultSupportedVersion);
    }

    protected function updateWhitelist(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $whitelist = $settings->get('media_type_whitelist', []);
        $whitelist = array_values(array_unique(array_merge(array_values($whitelist), [
            'image/ktx2',
            'model/gltf-binary',
            'model/gltf+json',
            'model/vnd.threejs+json',
            'application/octet-stream',
        ])));
        $settings->set('media_type_whitelist', $whitelist);

        $whitelist = $settings->get('extension_whitelist', []);
        $whitelist = array_values(array_unique(array_merge(array_values($whitelist), [
            'bin',
            'glb',
            'gltf',
            'json',
            'ktx2',
        ])));
        $settings->set('extension_whitelist', $whitelist);
    }
}
