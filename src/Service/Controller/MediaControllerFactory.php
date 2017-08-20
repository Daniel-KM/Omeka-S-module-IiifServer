<?php
namespace IiifServer\Service\Controller;

use IiifServer\Controller\MediaController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MediaControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $store = $services->get('Omeka\File\Store');
        $basePath = OMEKA_PATH . DIRECTORY_SEPARATOR . 'files';
        return new MediaController($store, $basePath);
    }
}
