<?php
namespace IiifServer\Service\Form;

use IiifServer\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('CleanUrl');
        $hasCleanUrl = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;

        $form = new ConfigForm(null, $options);
        $form
            ->setTranslator($services->get('MvcTranslator'))
            ->setHasCleanUrl($hasCleanUrl)
            ->setEventManager($services->get('EventManager'));
        return $form;
    }
}
