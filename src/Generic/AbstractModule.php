<?php declare(strict_types=1);
/*
 * Copyright Daniel Berthereau, 2018-2021
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

use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Settings\SettingsInterface;
use Omeka\Stdlib\Message;

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

    public function install(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        $translator = $services->get('MvcTranslator');
        $this->preInstall();
        if (!$this->checkDependency()) {
            $message = new Message(
                $translator->translate('This module requires the module "%s".'), // @translate
                $this->dependency
            );
            throw new ModuleCannotInstallException((string) $message);
        }
        if (!$this->checkDependencies()) {
            $message = new Message(
                $translator->translate('This module requires modules "%s".'), // @translate
                implode('", "', $this->dependencies)
            );
            throw new ModuleCannotInstallException((string) $message);
        }
        if (!$this->checkAllResourcesToInstall()) {
            $message = new Message(
                $translator->translate('This module has resources that cannot be installed.') // @translate
            );
            throw new ModuleCannotInstallException((string) $message);
        }
        $this->execSqlFromFile($this->modulePath() . '/data/install/schema.sql');
        $this
            ->installAllResources()
            ->manageConfig('install')
            ->manageMainSettings('install')
            ->manageSiteSettings('install')
            ->manageUserSettings('install')
            ->postInstall();
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        $this->preUninstall();
        $this->execSqlFromFile($this->modulePath() . '/data/install/uninstall.sql');
        $this
            ->manageConfig('uninstall')
            ->manageMainSettings('uninstall')
            ->manageSiteSettings('uninstall')
            // Don't uninstall user settings, they don't belong to admin.
            // ->manageUserSettings('uninstall')
            ->postUninstall();
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services): void
    {
        $filepath = $this->modulePath() . '/data/scripts/upgrade.php';
        if (file_exists($filepath) && filesize($filepath) && is_readable($filepath)) {
            // For compatibility with old upgrade files.
            $serviceLocator = $services;
            $this->setServiceLocator($serviceLocator);
            require_once $filepath;
        }
    }

    public function getInstallResources(): ?InstallResources
    {
        if (!class_exists(\Generic\InstallResources::class)) {
            // Use the module file first, since it must be present with the
            // right version, even if AbstractModule is older in another module.
            if (file_exists($filepath = OMEKA_PATH . '/modules/' . static::NAMESPACE . '/src/Generic/InstallResources.php')) {
                require_once $filepath;
            } elseif (file_exists($filepath = dirname(__DIR__, 3) . '/Generic/InstallResources.php')) {
                require_once $filepath;
            } elseif (file_exists($filepath = __DIR__ . '/InstallResources.php')) {
                require_once $filepath;
            } else {
                return null;
            }
        }
        $services = $this->getServiceLocator();
        return new \Generic\InstallResources($services);
    }

    public function checkAllResourcesToInstall(): bool
    {
        $installResources = $this->getInstallResources();
        return $installResources
            ? $installResources->checkAllResources(static::NAMESPACE)
            // Nothing to install.
            : true;
    }

    /**
     * @return self
     */
    public function installAllResources(): AbstractModule
    {
        $installResources = $this->getInstallResources();
        if (!$installResources) {
            // Nothing to install.
            return $this;
        }
        $installResources->createAllResources(static::NAMESPACE);
        return $this;
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

        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($data);
        $form->prepare();
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

    public function handleMainSettings(Event $event): void
    {
        $this->handleAnySettings($event, 'settings');
    }

    public function handleSiteSettings(Event $event): void
    {
        $this->handleAnySettings($event, 'site_settings');
    }

    public function handleUserSettings(Event $event): void
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Mvc\Status $status */
        $status = $services->get('Omeka\Status');
        if ($status->isAdminRequest()) {
            /** @var \Laminas\Router\Http\RouteMatch $routeMatch */
            $routeMatch = $status->getRouteMatch();
            if (!in_array($routeMatch->getParam('controller'), ['Omeka\Controller\Admin\User', 'user'])) {
                return;
            }
            $this->handleAnySettings($event, 'user_settings');
        }
    }

    protected function modulePath(): string
    {
        return OMEKA_PATH . '/modules/' . static::NAMESPACE;
    }

    protected function preInstall(): void
    {
    }

    protected function postInstall(): void
    {
    }

    protected function preUninstall(): void
    {
    }

    protected function postUninstall(): void
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
     * @return self
     */
    protected function manageConfig(string $process, array $values = []): AbstractModule
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        return $this->manageAnySettings($settings, 'config', $process, $values);
    }

    /**
     * Set, delete or update main settings.
     *
     * @param string $process
     * @param array $values Values to use when process is update.
     * @return self
     */
    protected function manageMainSettings(string $process, array $values = []): AbstractModule
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        return $this->manageAnySettings($settings, 'settings', $process, $values);
    }

    /**
     * Set, delete or update settings of all sites.
     *
     * @todo Replace by a single query (for install, uninstall, main, setting, user).
     *
     * @param string $process
     * @param array $values Values to use when process is update, by site id.
     * @return self
     */
    protected function manageSiteSettings(string $process, array $values = []): AbstractModule
    {
        $settingsType = 'site_settings';
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return $this;
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
                $values[$id] ?? []
            );
        }
        return $this;
    }

    /**
     * Set, delete or update settings of all users.
     *
     * @todo Replace by a single query (for install, uninstall, main, setting, user).
     *
     * @param string $process
     * @param array $values Values to use when process is update, by user id.
     * @return self
     */
    protected function manageUserSettings(string $process, array $values = []): AbstractModule
    {
        $settingsType = 'user_settings';
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return $this;
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
                $values[$id] ?? []
            );
        }
        return $this;
    }

    /**
     * Set, delete or update all settings of a specific type.
     *
     * It processes main settings, or one site, or one user.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @param string $process "install", "uninstall", "update".
     * @param array $values
     * @return $this;
     */
    protected function manageAnySettings(SettingsInterface $settings, string $settingsType, string $process, array $values = []): AbstractModule
    {
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return $this;
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
        return $this;
    }

    /**
     * Prepare a settings fieldset.
     *
     * @param Event $event
     * @param string $settingsType
     * @return \Laminas\Form\Fieldset|null
     */
    protected function handleAnySettings(Event $event, string $settingsType): ?\Laminas\Form\Fieldset
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

        /**
         * @var \Laminas\Form\Fieldset $fieldset
         * @var \Laminas\Form\Form $form
         */
        $fieldset = $services->get('FormElementManager')->get($settingFieldsets[$settingsType]);
        $fieldset->setName($space);
        $form = $event->getTarget();
        // The user view is managed differently.
        if ($settingsType === 'user_settings') {
            // This process allows to save first level elements automatically.
            // @see \Omeka\Controller\Admin\UserController::editAction()
            $formFieldset = $form->get('user-settings');
            foreach ($fieldset->getFieldsets() as $element) {
                $formFieldset->add($element);
            }
            foreach ($fieldset->getElements() as $element) {
                $formFieldset->add($element);
            }
            $formFieldset->populateValues($data);
            $fieldset = $formFieldset;
        } else {
            $form->add($fieldset);
            $form->get($space)->populateValues($data);
        }

        return $fieldset;
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
    protected function initDataToPopulate(SettingsInterface $settings, string $settingsType, $id = null, iterable $values = []): bool
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
    protected function prepareDataToPopulate(SettingsInterface $settings, string $settingsType): ?array
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
     * @return bool
     */
    protected function checkDependency(): bool
    {
        return empty($this->dependency)
            || $this->isModuleActive($this->dependency);
    }

    /**
     * Check if the module has dependencies.
     *
     * @return bool
     */
    protected function checkDependencies(): bool
    {
        return empty($this->dependencies)
            || $this->areModulesActive($this->dependencies);
    }

    /**
     * Check the version of a module.
     */
    protected function isModuleVersionAtLeast(string $module, string $version): bool
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($module);
        if (!$module) {
            return false;
        }

        $moduleVersion = $module->getIni('version');
        return $moduleVersion
            ? version_compare($moduleVersion, $version, '>=')
            : false;
    }

    /**
     * Check if a module is active.
     *
     * @param string $module
     * @return bool
     */
    protected function isModuleActive(string $module): bool
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($module);
        return $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }

    /**
     * Check if a list of modules are active.
     *
     * @param array $modules
     * @return bool
     */
    protected function areModulesActive(array $modules): bool
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        foreach ($modules as $module) {
            $module = $moduleManager->getModule($module);
            if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                return false;
            }
        }
        return true;
    }

    /**
     * Disable a module.
     *
     * @param string $module
     * @return bool
     */
    protected function disableModule(string $module): bool
    {
        // Check if the module is enabled first to avoid an exception.
        if (!$this->isModuleActive($module)) {
            return true;
        }

        // Check if the user is a global admin to avoid right issues.
        $services = $this->getServiceLocator();
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        if (!$user || $user->getRole() !== \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN) {
            return false;
        }

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $managedModule = $moduleManager->getModule($module);
        $moduleManager->deactivate($managedModule);

        $translator = $services->get('MvcTranslator');
        $message = new \Omeka\Stdlib\Message(
            $translator->translate('The module "%s" was automatically deactivated because the dependencies are unavailable.'), // @translate
            $module
        );
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
        $messenger->addWarning($message);

        $logger = $services->get('Omeka\Logger');
        $logger->warn($message);
        return true;
    }

    /**
     * Get each line of a string separately.
     *
     * @deprecated Since 3.3.22. Use \Omeka\Form\Element\ArrayTextarea.
     * @param string $string
     * @return array
     */
    public function stringToList($string): array
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))), 'strlen');
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Windows and Apple copy/paste from a textarea input.
     *
     * @deprecated Since 3.3.22. Use \Omeka\Form\Element\ArrayTextarea.
     * @param string $string
     * @return string
     */
    protected function fixEndOfLine($string): string
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $string);
    }
}
