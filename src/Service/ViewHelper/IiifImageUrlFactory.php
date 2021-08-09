<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifImageUrl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifImageUrlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $helpers = $services->get('ViewHelperManager');
        $settings = $services->get('Omeka\Settings');
        return new IiifImageUrl(
            $helpers->get('url'),
            $helpers->get('iiifCleanIdentifiers'),
            $settings->get('iiifserver_media_api_default_version', '2'),
            $settings->get('iiifserver_media_api_supported_versions', ['2/2', '3/2']),
            $settings->get('iiifserver_identifier_prefix', ''),
            $settings->get('iiifserver_url_force_from', ''),
            $settings->get('iiifserver_url_force_to', ''),
            $settings->get('iiifserver_media_api_identifier', '')
        );
    }
}
