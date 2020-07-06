<?php

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifImageUrl;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IiifImageUrlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $settings = $services->get('Omeka\Settings');
        return new IiifImageUrl(
            $settings->get('iiifserver_url_force_from'),
            $settings->get('iiifserver_url_force_to'),
            $urlHelper
        );
    }
}
