<?php
namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifCleanIdentifiers;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

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
        if ($services->get('Omeka\Settings')->get('iiifserver_url_clean', true)) {
            $plugins = $services->get('ViewHelperManager');
            $getIdentifiersFromResources = $plugins->has('getIdentifiersFromResources')
                ? $plugins->get('getIdentifiersFromResources')
                : null;
        } else {
            $getIdentifiersFromResources = null;
        }
        return new IiifCleanIdentifiers(
            $getIdentifiersFromResources
        );
    }
}
