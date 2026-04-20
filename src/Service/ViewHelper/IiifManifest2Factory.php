<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifManifest2;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class IiifManifest2Factory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $isAllowedViewAll = $services->get('Omeka\Acl')
            ->userIsAllowed('Omeka\Entity\Resource', 'view-all');
        $plugins = $services->get('ControllerPluginManager');
        $accessStatus = $plugins->has('accessStatus') ? $plugins->get('accessStatus') : null;
        return new IiifManifest2(
            $services->get('Omeka\Settings'),
            $services->get('Omeka\File\TempFileFactory'),
            $basePath,
            $isAllowedViewAll,
            $accessStatus
        );
    }
}
