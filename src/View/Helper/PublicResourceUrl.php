<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceRepresentation;

/**
 * View helper to return the url to the public default site page of a resource.
 */
class PublicResourceUrl extends AbstractHelper
{
    /**
     * @var string|null
     */
    protected $defaultSiteSlug;

    public function __construct(?string $defaultSiteSlug)
    {
        $this->defaultSiteSlug = $defaultSiteSlug;
    }

    /**
     * Return the url to the public default site page or a resource.
     *
     * @uses AbstractResourceRepresentation::siteUrl()
     */
    public function __invoke(AbstractResourceRepresentation $resource, bool $canonical = false): string
    {
        // Manage the case where there is no site.
        return $this->defaultSiteSlug
            ? $resource->siteUrl($this->defaultSiteSlug, $canonical)
            : '';
    }
}
