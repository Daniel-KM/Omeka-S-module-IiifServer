<?php

namespace IiifServer\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\Url;

class IiifImageUrl extends AbstractHelper
{
    /**
     * @var string
     */
    protected $serviceImage;

    /**
     * @var string
     */
    protected $serviceMedia;

    /**
     * @var string
     */
    protected $forceFrom;

    /**
     * @var string
     */
    protected $forceTo;

    /**
     * @var string
     */
    protected $baseUrlImage;

    /**
     * @var string
     */
    protected $baseUrlMedia;

    /**
     * @var \Zend\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * @param string $serviceImage
     * @param string $serviceMedia
     * @param string $forceUrlFrom
     * @param string $forceUrlTo
     * @param string $baseUrlImage
     * @param string $baseUrlMedia
     * @param Url $urlHelper
     */
    public function __construct(
        $serviceImage,
        $serviceMedia,
        $forceUrlFrom,
        $forceUrlTo,
        $baseUrlImage,
        $baseUrlMedia,
        Url $urlHelper
    ) {
        $this->serviceImage = $serviceImage;
        $this->serviceMedia = $serviceMedia;
        $this->forceUrlFrom = $forceUrlFrom;
        $this->forceUrlTo = $forceUrlTo;
        $this->baseUrlImage = $baseUrlImage;
        $this->baseUrlMedia = $baseUrlMedia;
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

        $isMedia = strtok($route, '/') === 'mediaserver';
        if ($isMedia) {
            if ($this->serviceMedia) {
                return str_replace($this->baseUrlMedia, $this->serviceMedia, $url);
            }
        } elseif ($this->serviceImage) {
            return str_replace($this->baseUrlImage, $this->serviceImage, $url);
        }

        return $this->forceFrom && (strpos($url, $this->forceFrom) === 0)
            ? substr_replace($url, $this->forceTo, 0, strlen($this->forceFrom))
            : $url;
    }
}
