<?php
namespace IiifServer\Service\Form;

use IiifServer\Form\Config;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ConfigFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $settings = $container->get('Omeka\Settings');
        $api = $container->get('Omeka\ApiManager');

        $form = new Config;
        $form->setSettings($settings);
        $form->setApi($api);
        return $form;
    }
}
