<?php
namespace IiifServer\Service\Form;

use IiifServer\Form\ConfigForm;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $settings = $container->get('Omeka\Settings');
        $api = $container->get('Omeka\ApiManager');

        $form = new ConfigForm;
        $form->setSettings($settings);
        $form->setApi($api);
        return $form;
    }
}
