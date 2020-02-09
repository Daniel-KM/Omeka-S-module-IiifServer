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
        $baseUrlImage = $urlHelper('imageserver', [], ['force_canonical' => true]);
        $baseUrlMedia = $urlHelper('mediaserver', [], ['force_canonical' => true]);

        $settings = $services->get('Omeka\Settings');
        return new IiifImageUrl(
            $settings->get('iiifserver_manifest_service_image'),
            $settings->get('iiifserver_manifest_force_url_from'),
            $settings->get('iiifserver_manifest_force_url_to'),
            $baseUrlImage,
            $baseUrlMedia,
            $urlHelper
        );
    }
}
