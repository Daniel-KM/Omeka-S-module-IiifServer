<?php
namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifCleanIdentifiers;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifCleanIdentifiersFactory implements FactoryInterface
{
    /**
     * Create and return the IiifCleanIdentifiers view helper.
     *
     * @return IiifCleanIdentifiers
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // Check use of clean identifiers one time.
        $settings = $services->get('Omeka\Settings');
        if ($settings->get('iiifserver_identifier_clean', true)) {
            $plugins = $services->get('ViewHelperManager');
            $getIdentifiersFromResources = $plugins->has('getIdentifiersFromResources')
                ? $plugins->get('getIdentifiersFromResources')
                : null;
        } else {
            $getIdentifiersFromResources = null;
        }
        return new IiifCleanIdentifiers(
            $getIdentifiersFromResources,
            $settings->get('iiifserver_identifier_prefix', false),
            $settings->get('iiifserver_identifier_raw', false)
        );
    }
}
