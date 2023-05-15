<?php declare(strict_types=1);

namespace IiifServer\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class IiifManifestLink implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'IIIF Manifest Link'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        return $view->partial('common/resource-page-block-layout/iiif-manifest-link', [
            'resource' => $resource,
        ]);
    }
}
