<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifUrl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifUrlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $helpers = $services->get('ViewHelperManager');
        $settings = $services->get('Omeka\Settings');
        return new IiifUrl(
            $helpers->get('url'),
            $helpers->get('iiifCleanIdentifiers'),
            $helpers->get('iiifMediaUrl'),
            $helpers->get('ServerUrl')($helpers->get('BasePath')()),
            // TODO Remove dependencies to image server.
            $settings->get('iiifserver_manifest_default_version', '2'),
            $settings->get('iiifserver_url_force_from'),
            $settings->get('iiifserver_url_force_to'),
            $settings->get('iiifserver_identifier_prefix', '')
        );
    }
}
