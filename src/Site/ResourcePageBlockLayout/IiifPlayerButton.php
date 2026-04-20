<?php declare(strict_types=1);

namespace IiifServer\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

if (interface_exists('Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface')) {
    class IiifPlayerButton implements \Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface
    {
        use IiifPlayerButtonTrait;
    }
} else {
    class IiifPlayerButton
    {
        use IiifPlayerButtonTrait;
    }
}

trait IiifPlayerButtonTrait
{
    public function getLabel(): string
    {
        return 'IIIF Player Button'; // @translate
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
        return $view->partial('common/resource-page-block-layout/iiif-player-button', [
            'resource' => $resource,
        ]);
    }
}
