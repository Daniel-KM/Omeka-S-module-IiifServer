<?php declare(strict_types=1);

namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\PdfPageSizes;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class PdfPageSizesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new PdfPageSizes(
            $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files'),
            $services->get('Omeka\Cli'),
            $services->get('Omeka\Connection')
        );
    }
}
