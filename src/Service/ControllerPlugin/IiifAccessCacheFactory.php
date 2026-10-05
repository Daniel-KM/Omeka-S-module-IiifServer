<?php declare(strict_types=1);

namespace IiifServer\Service\ControllerPlugin;

use IiifServer\Mvc\Controller\Plugin\IiifAccessCache;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifAccessCacheFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): IiifAccessCache
    {
        // The digital objects are linked to the item by values of the properties
        // set in the module Digital Object, like in its helper digitalObjectInline.
        $digitalObjectPropertyIds = [];
        if (class_exists('DigitalObject\Module', false)) {
            $properties = $services->get('Omeka\Settings')->get('digitalobject_inline_properties', [])
                ?: ($services->get('Config')['digitalobject']['config']['digitalobject_inline_properties'] ?? []);
            $digitalObjectPropertyIds = $properties
                ? array_values(array_filter($services->get('Common\EasyMeta')->propertyIds($properties)))
                : [];
        }

        return new IiifAccessCache(
            $services->get('Omeka\Connection'),
            $services->get('ControllerPluginManager')->has('isAllowedMediaContent'),
            $digitalObjectPropertyIds
        );
    }
}
