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
        $useCleanIdentifier = (bool) $services->get('Omeka\Settings')->get('iiifserver_url_clean', true);
        $getIdentifiersFromResources = null;
        if ($useCleanIdentifier) {
            $plugins = $services->get('ViewHelperManager');
            if ($plugins->has('getIdentifiersFromResources')) {
                $getIdentifiersFromResources = $plugins->get('getIdentifiersFromResources');
            }
        }
        return new IiifCleanIdentifiers(
            $getIdentifiersFromResources
        );
    }
}
