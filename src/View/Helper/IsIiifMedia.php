<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use IiifServer\Mvc\Controller\Plugin\IsIiifMedia as IsIiifMediaPlugin;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\MediaRepresentation;

class IsIiifMedia extends AbstractHelper
{
    protected IsIiifMediaPlugin $plugin;

    public function __construct(IsIiifMediaPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function __invoke(MediaRepresentation $media, ?string $type = null): bool
    {
        return $this->plugin->__invoke($media, $type);
    }
}
