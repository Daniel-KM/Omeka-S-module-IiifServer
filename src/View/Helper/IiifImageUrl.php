<?php

namespace IiifServer\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\Url;

/**
 * @todo FIXME Rename helper iiifImageUrl, because it is used for media too.
 */
class IiifImageUrl extends AbstractHelper
{
    /**
     * @var string
     */
    protected $forceFrom;

    /**
     * @var string
     */
    protected $forceTo;

    /**
     * @var \Zend\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * @param string $forceUrlFrom
     * @param string $forceUrlTo
     * @param Url $urlHelper
     */
    public function __construct(
        $forceUrlFrom,
        $forceUrlTo,
        Url $urlHelper
    ) {
        $this->forceUrlFrom = $forceUrlFrom;
        $this->forceUrlTo = $forceUrlTo;
        $this->urlHelper = $urlHelper;
    }

    /**
     * Return an iiif image url.
     *
     * It takes care of external server and of the option to force base url.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function __invoke($route, array $params = [])
    {
        $helper = $this->urlHelper;
        $url = $helper($route, $params, ['force_canonical' => true]);
        return $this->forceFrom && (strpos($url, $this->forceFrom) === 0)
            ? substr_replace($url, $this->forceTo, 0, strlen($this->forceFrom))
            : $url;
    }
}
