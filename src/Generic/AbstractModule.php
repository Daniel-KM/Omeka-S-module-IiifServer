<?php
/*
 * Copyright Daniel Berthereau, 2018-2020
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
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

namespace Generic;

use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Settings\SettingsInterface;
use Omeka\Stdlib\Message;
use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

/**
 * This class allows to manage all methods that should run only once and that
 * are generic to all modules (install and settings).
 *
 * The logic is "config over code": so all settings have just to be set in the
 * main `config/module.config.php` file, inside a key with the lowercase module
 * name,  with sub-keys `config`, `settings`, `site_settings`, `user_settings`
 * and `block_settings`. All the forms have just to be standard Zend form.
 * Eventual install and uninstall sql can be set in `data/install/` and upgrade
 * code in `data/scripts`.
 *
 * See readme.
 */
abstract class AbstractModule extends \Omeka\Module\AbstractModule
{
    public function getConfig()
    {
        return include $this->modulePath() . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->preInstall();
        $this->checkDependency();
        $this->checkDependencies();
        $this->checkAllResourcesToInstall();
        $this->execSqlFromFile($this->modulePath() . '/data/install/schema.sql');
        $this->installAllResources();
        $this->manageConfig('install');
        $this->manageMainSettings('install');
        $this->manageSiteSettings('install');
        $this->manageUserSettings('install');
        $this->postInstall();
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->preUninstall();
        $this->execSqlFromFile($this->modulePath() . '/data/install/uninstall.sql');
        $this->manageConfig('uninstall');
        $this->manageMainSettings('uninstall');
        $this->manageSiteSettings('uninstall');
        // Don't uninstall user settings, they don't belong to admin.
        // $this->manageUserSettings('uninstall');
        $this->postUninstall();
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        $filepath = $this->modulePath() . '/data/scripts/upgrade.php';
        if (file_exists($filepath) && filesize($filepath) && is_readable($filepath)) {
            $this->setServiceLocator($serviceLocator);
            require_once $filepath;
        }
    }

    /**
     * @throws \Omeka\Module\Exception\ModuleCannotInstallException
     */
    public function checkAllResourcesToInstall()
    {
        if (!class_exists(\Generic\InstallResources::class)) {
            if (file_exists(dirname(dirname(dirname(__DIR__))) . '/Generic/InstallResources.php')) {
                require_once dirname(dirname(dirname(__DIR__))) . '/Generic/InstallResources.php';
            } elseif (file_exists(__DIR__ . '/InstallResources.php')) {
                require_once __DIR__ . '/InstallResources.php';
            } else {
                // Nothing to install.
                return true;
            }
        }

        $services = $this->getServiceLocator();
        $installResources = new \Generic\InstallResources($services);
        $installResources->checkAllResources(static::NAMESPACE);
    }

    /**
     * @throws \Omeka\Module\Exception\ModuleCannotInstallException
     */
    public function installAllResources()
    {
        if (!class_exists(\Generic\InstallResources::class)) {
            if (file_exists(dirname(dirname(dirname(__DIR__))) . '/Generic/InstallResources.php')) {
                require_once dirname(dirname(dirname(__DIR__))) . '/Generic/InstallResources.php';
            } elseif (file_exists(__DIR__ . '/InstallResources.php')) {
                require_once __DIR__ . '/InstallResources.php';
            } else {
                // Nothing to install.
                return true;
            }
        }

        $services = $this->getServiceLocator();
        $installResources = new \Generic\InstallResources($services);
        $installResources->createAllResources(static::NAMESPACE);
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $formManager = $services->get('FormElementManager');
        $formClass = static::NAMESPACE . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return '';
        }

        // Simplify config of modules.
        $renderer->ckEditor();

        $settings = $services->get('Omeka\Settings');

        $this->initDataToPopulate($settings, 'config');

        $data = $this->prepareDataToPopulate($settings, 'config');
        if (is_null($data)) {
            return '';
        }

        $form = $services->get('FormElementManager')->get($formClass);
        $form->init();
        $form->setData($data);
        return $renderer->formCollection($form);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space]['config'])) {
            return true;
        }

        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $formClass = static::NAMESPACE . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return true;
        }

        $params = $controller->getRequest()->getPost();

        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        $settings = $services->get('Omeka\Settings');
        $defaultSettings = $config[$space]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
        return true;
    }

    public function handleMainSettings(Event $event)
    {
        $this->handleAnySettings($event, 'settings');
    }

    public function handleSiteSettings(Event $event)
    {
        $this->handleAnySettings($event, 'site_settings');
    }

    public function handleUserSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Mvc\Status $status */
        $status = $services->get('Omeka\Status');
        if ($status->isAdminRequest()) {
            /** @var \Laminas\Router\Http\RouteMatch $routeMatch */
            $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
            if (!in_array($routeMatch->getParam('controller'), ['Omeka\Controller\Admin\User', 'user'])) {
                return;
            }
            $this->handleAnySettings($event, 'user_settings');
        }
    }

    /**
     * @return string
     */
    protected function modulePath()
    {
        return OMEKA_PATH . '/modules/' . static::NAMESPACE;
    }

    protected function preInstall()
    {
    }

    protected function postInstall()
    {
    }

    protected function preUninstall()
    {
    }

    protected function postUninstall()
    {
    }

    /**
     * Execute a sql from a file.
     *
     * @param string $filepath
     * @return int|null
     */
    protected function execSqlFromFile($filepath)
    {
        if (!file_exists($filepath) || !filesize($filepath) || !is_readable($filepath)) {
            return null;
        }
        $services = $this->getServiceLocator();
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $sql = file_get_contents($filepath);

        // Use single statements for execution.
        // See core commit #2689ce92f.
        $sqls = array_filter(array_map('trim', explode(";\n", $sql)));
        foreach ($sqls as $sql) {
            $result = $connection->exec($sql);
        }

        return $result;
    }

    /**
     * Set, delete or update settings of the config of a module.
     *
     * @param string $process
     * @param array $values Values to use when process is update.
     */
    protected function manageConfig($process, array $values = [])
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $this->manageAnySettings($settings, 'config', $process, $values);
    }

    /**
     * Set, delete or update main settings.
     *
     * @param string $process
     * @param array $values Values to use when process is update.
     */
    protected function manageMainSettings($process, array $values = [])
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $this->manageAnySettings($settings, 'settings', $process, $values);
    }

    /**
     * Set, delete or update settings of all sites.
     *
     * @todo Replace by a single query (for install, uninstall, main, setting, user).
     *
     * @param string $process
     * @param array $values Values to use when process is update, by site id.
     */
    protected function manageSiteSettings($process, array $values = [])
    {
        $settingsType = 'site_settings';
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');
        $ids = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
        foreach ($ids as $id) {
            $settings->setTargetId($id);
            $this->manageAnySettings(
                $settings,
                $settingsType,
                $process,
                isset($values[$id]) ? $values[$id] : []
            );
        }
    }

    /**
     * Set, delete or update settings of all users.
     *
     * @todo Replace by a single query (for install, uninstall, main, setting, user).
     *
     * @param string $process
     * @param array $values Values to use when process is update, by user id.
     */
    protected function manageUserSettings($process, array $values = [])
    {
        $settingsType = 'user_settings';
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings\User');
        $api = $services->get('Omeka\ApiManager');
        $ids = $api->search('users', [], ['returnScalar' => 'id'])->getContent();
        foreach ($ids as $id) {
            $settings->setTargetId($id);
            $this->manageAnySettings(
                $settings,
                $settingsType,
                $process,
                isset($values[$id]) ? $values[$id] : []
            );
        }
    }

    /**
     * Set, delete or update all settings of a specific type.
     *
     * It processes main settings, or one site, or one user.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @param string $process
     * @param array $values
     */
    protected function manageAnySettings(SettingsInterface $settings, $settingsType, $process, array $values = [])
    {
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $defaultSettings = $config[$space][$settingsType];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
                case 'update':
                    if (array_key_exists($name, $values)) {
                        $settings->set($name, $values[$name]);
                    }
                    break;
            }
        }
    }

    /**
     * Prepare a settings fieldset.
     *
     * @param Event $event
     * @param string $settingsType
     * @return \Laminas\Form\Form|null
     */
    protected function handleAnySettings(Event $event, $settingsType)
    {
        global $globalNext;

        $services = $this->getServiceLocator();

        $settingsTypes = [
            // 'config' => 'Omeka\Settings',
            'settings' => 'Omeka\Settings',
            'site_settings' => 'Omeka\Settings\Site',
            'user_settings' => 'Omeka\Settings\User',
        ];
        if (!isset($settingsTypes[$settingsType])) {
            return null;
        }

        // TODO Check fieldsets in the config of the module.
        $settingFieldsets = [
            // 'config' => static::NAMESPACE . '\Form\ConfigForm',
            'settings' => static::NAMESPACE . '\Form\SettingsFieldset',
            'site_settings' => static::NAMESPACE . '\Form\SiteSettingsFieldset',
            'user_settings' => static::NAMESPACE . '\Form\UserSettingsFieldset',
        ];
        if (!isset($settingFieldsets[$settingsType])) {
            return null;
        }

        $settings = $services->get($settingsTypes[$settingsType]);

        switch ($settingsType) {
            case 'settings':
                $id = null;
                break;
            case 'site_settings':
                $site = $services->get('ControllerPluginManager')->get('currentSite');
                $id = $site()->id();
                break;
            case 'user_settings':
                /** @var \Laminas\Router\Http\RouteMatch $routeMatch */
                $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
                $id = $routeMatch->getParam('id');
                break;
        }

        // Simplify config of settings.
        if (empty($globalNext)) {
            $globalNext = true;
            $ckEditorHelper = $services->get('ViewHelperManager')->get('ckEditor');
            $ckEditorHelper();
        }

        // Allow to use a form without an id, for example to create a user.
        if ($settingsType !== 'settings' && !$id) {
            $data = [];
        } else {
            $this->initDataToPopulate($settings, $settingsType, $id);
            $data = $this->prepareDataToPopulate($settings, $settingsType);
            if (is_null($data)) {
                return null;
            }
        }

        $space = strtolower(static::NAMESPACE);

        /** @var \Laminas\Form\Form $form */
        $fieldset = $services->get('FormElementManager')->get($settingFieldsets[$settingsType]);
        $fieldset->setName($space);
        $form = $event->getTarget();
        // The user view is managed differently.
        if ($settingsType === 'user_settings') {
            // This process allows to save first level elements automatically.
            // @see \Omeka\Controller\Admin\UserController::editAction()
            $formFieldset = $form->get('user-settings');
            foreach ($fieldset->getElements() as $element) {
                $formFieldset->add($element);
            }
            $formFieldset->populateValues($data);
        } else {
            $form->add($fieldset);
            $form->get($space)->populateValues($data);
        }

        return $form;
    }

    /**
     * Initialize each original settings, if not ready.
     *
     * If the default settings were never registered, it means an incomplete
     * config, install or upgrade, or a new site or a new user. In all cases,
     * check it and save default value first.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @param int $id Site id or user id.
     * @param array $values Specific values to populate, e.g. translated strings.
     * @param bool True if processed.
     */
    protected function initDataToPopulate(SettingsInterface $settings, $settingsType, $id = null, array $values = [])
    {
        // This method is not in the interface, but is set for config, site and
        // user settings.
        if (!method_exists($settings, 'getTableName')) {
            return false;
        }

        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return false;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        if ($id) {
            if (!method_exists($settings, 'getTargetIdColumnName')) {
                return false;
            }
            $sql = sprintf('SELECT id, value FROM %s WHERE %s = :target_id', $settings->getTableName(), $settings->getTargetIdColumnName());
            $stmt = $connection->executeQuery($sql, ['target_id' => $id]);
        } else {
            $sql = sprintf('SELECT id, value FROM %s', $settings->getTableName());
            $stmt = $connection->query($sql);
        }

        $currentSettings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $defaultSettings = $config[$space][$settingsType];
        // Skip settings that are arrays, because the fields "multi-checkbox"
        // and "multi-select" are removed when no value are selected, so it's
        // not possible to determine if it's a new setting or an old empty
        // setting currently. So fill them via upgrade in that case or fill the
        // values.
        // TODO Find a way to save empty multi-checkboxes and multi-selects (core fix).
        $defaultSettings = array_filter($defaultSettings, function ($v) {
            return !is_array($v);
        });
        $missingSettings = array_diff_key($defaultSettings, $currentSettings);

        foreach ($missingSettings as $name => $value) {
            $settings->set($name, array_key_exists($name, $values) ? $values[$name] : $value);
        }

        return true;
    }

    /**
     * Prepare data for a form or a fieldset.
     *
     * To be overridden by module for specific keys.
     *
     * @todo Use form methods to populate.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @return array|null
     */
    protected function prepareDataToPopulate(SettingsInterface $settings, $settingsType)
    {
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        // Use isset() instead of empty() to give the possibility to display a
        // specific form.
        if (!isset($config[$space][$settingsType])) {
            return null;
        }

        $defaultSettings = $config[$space][$settingsType];

        $data = [];
        foreach ($defaultSettings as $name => $value) {
            $val = $settings->get($name, is_array($value) ? [] : null);
            $data[$name] = $val;
        }

        return $data;
    }

    /**
     * Check if the module has a dependency.
     *
     * This method is distinct of checkDependencies() for performance purpose.
     *
     * @throws ModuleCannotInstallException
     */
    protected function checkDependency()
    {
        if (empty($this->dependency) || $this->isModuleActive($this->dependency)) {
            return;
        }

        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $message = new Message(
            $translator->translate('This module requires the module "%s".'), // @translate
            $this->dependency
        );
        throw new ModuleCannotInstallException($message);
    }

    /**
     * Check if the module has dependencies.
     *
     * @throws ModuleCannotInstallException
     */
    protected function checkDependencies()
    {
        if (empty($this->dependencies) || $this->areModulesActive($this->dependencies)) {
            return;
        }

        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $message = new Message(
            $translator->translate('This module requires modules "%s".'), // @translate
            implode('", "', $this->dependencies)
        );
        throw new ModuleCannotInstallException($message);
    }

    /**
     * Check if a module is active.
     *
     * @param string $moduleClass
     * @return bool
     */
    protected function isModuleActive($moduleClass)
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($moduleClass);
        return $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }

    /**
     * Check if a list of modules are active.
     *
     * @param array $moduleClasses
     * @return bool
     */
    protected function areModulesActive(array $moduleClasses)
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        foreach ($moduleClasses as $moduleClass) {
            $module = $moduleManager->getModule($moduleClass);
            if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                return false;
            }
        }
        return true;
    }

    /**
     * Disable a module.
     *
     * @param string $moduleClass
     */
    protected function disableModule($moduleClass)
    {
        // Check if the module is enabled first to avoid an exception.
        if (!$this->isModuleActive($moduleClass)) {
            return;
        }

        // Check if the user is a global admin to avoid right issues.
        $services = $this->getServiceLocator();
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        if (!$user || $user->getRole() !== \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN) {
            return;
        }

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($moduleClass);
        $moduleManager->deactivate($module);

        $translator = $services->get('MvcTranslator');
        $message = new \Omeka\Stdlib\Message(
            $translator->translate('The module "%s" was automatically deactivated because the dependencies are unavailable.'), // @translate
            $moduleClass
        );
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
        $messenger->addWarning($message);

        $logger = $services->get('Omeka\Logger');
        $logger->warn($message);
    }

    /**
     * Get each line of a string separately.
     *
     * @param string $string
     * @return array
     */
    public function stringToList($string)
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))), 'strlen');
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Windows and Apple copy/paste from a textarea input.
     *
     * @param string $string
     * @return string
     */
    protected function fixEndOfLine($string)
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $string);
    }
}
