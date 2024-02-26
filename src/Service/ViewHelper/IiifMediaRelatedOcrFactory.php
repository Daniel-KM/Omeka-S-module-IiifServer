<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\IiifMediaRelatedOcr;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IiifMediaRelatedOcrFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $settings = $services->get('Omeka\Settings');
        return new IiifMediaRelatedOcr(
            $plugins->has('isAllowedMediaContent') ? $plugins->get('isAllowedMediaContent') : null,
            (string) $settings->get('iiifserver_xml_image_match', 'order'),
            (bool) $settings->get('iiifserver_access_ocr_skip')
        );
    }
}
