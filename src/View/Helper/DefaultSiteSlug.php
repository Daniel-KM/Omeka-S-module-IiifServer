<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper to get the default site slug, or the first one.
 */
class DefaultSiteSlug extends AbstractHelper
{
    /**
     * @var string
     */
    protected $defaultSiteSlug;

    public function __construct(?string $defaultSiteSlug)
    {
        $this->defaultSiteSlug = $defaultSiteSlug;
    }

    /**
     * Return the default site slug, or the first one.
     */
    public function __invoke(): ?string
    {
        return $this->defaultSiteSlug;
    }
}
