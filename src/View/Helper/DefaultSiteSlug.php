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

    /**
     * Construct the helper.
     *
     * @param string|null $defaultSiteSlug
     */
    public function __construct($defaultSiteSlug)
    {
        $this->defaultSiteSlug = $defaultSiteSlug;
    }

    /**
     * Return the default site slug, or the first one.
     *
     * @return string|null
     */
    public function __invoke()
    {
        return $this->defaultSiteSlug;
    }
}
