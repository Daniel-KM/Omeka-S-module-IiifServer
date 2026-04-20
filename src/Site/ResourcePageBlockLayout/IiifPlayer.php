<?php declare(strict_types=1);

namespace IiifServer\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

if (interface_exists('Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface')) {
    class IiifPlayer implements \Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface
    {
        use IiifPlayerTrait;
    }
} else {
    class IiifPlayer
    {
        use IiifPlayerTrait;
    }
}

trait IiifPlayerTrait
{
    public function getLabel(): string
    {
        return 'IIIF Viewer'; // @translate
    }

    public function getCompatibleResourceNames(): array
    {
        return [
            'items',
            'item_sets',
            'media',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string
    {
        return $view->partial('common/resource-page-block-layout/iiif-player', [
            'resource' => $resource,
        ]);
    }
}
