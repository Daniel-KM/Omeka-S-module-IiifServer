<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class IiifManifestExternal extends AbstractHelper
{
    /**
     * Get the external manifest of a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return string|null
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource): ?string
    {
        $manifestProperty = $this->view->setting('iiifserver_manifest_external_property');
        // Manage the case where the url is saved as an uri or a text and the
        // case where the property contains other values that are not url.
        $urlManifest = $resource->value($manifestProperty, ['type' => 'uri']);
        if ($urlManifest) {
            return $urlManifest->uri();
        }
        $urlManifest = $resource->value($manifestProperty);
        if ($urlManifest) {
            $urlManifest = (string) $urlManifest;
            if (filter_var($urlManifest, FILTER_VALIDATE_URL)) {
                return $urlManifest;
            }
        }
        return null;
    }
}
