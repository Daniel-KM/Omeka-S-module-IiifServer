<?php
namespace IiifServer\Service\Form;

use IiifServer\Form\ConfigForm;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ConfigForm(null, $options);
        $form
            ->setTranslator($services->get('MvcTranslator'))
            ->setEventManager($services->get('EventManager'));
        return $form;
    }
}
