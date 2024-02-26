<?php declare(strict_types=1);
namespace IiifServer\Service\ViewHelper;

use IiifServer\View\Helper\PublicResourceUrl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the PublicResourceUrlFactory view helper.
 *
 * @todo Set a setting for the default site of the user.
 */
class PublicResourceUrlFactory implements FactoryInterface
{
    /**
     * Create and return the PublicResourceUrl view helper
     *
     * @return PublicResourceUrl
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Common\View\Helper\DefaultSite $defaultSite */
        $defaultSite = $services->get('ViewHelperManager')->get('defaultSite');
        return new PublicResourceUrl($defaultSite('slug'));
    }
}
