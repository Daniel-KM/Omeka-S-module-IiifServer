<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * This helper is copied in viewers, with the specific check for iiif server.
 */
class IiifManifestExternal extends AbstractHelper
{
    /**
     * Get the external manifest url or the collection url of a resource.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $useCollection = false): ?string
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $manifestProperty = $plugins->get('setting')->__invoke('iiifserver_manifest_external_property');
        if (empty($manifestProperty)) {
            return null;
        }

        $urls = [];

        // Manage the case where the url is saved as an uri or a text and the
        // case where the property contains other values that are not url.
        foreach ($resource->value($manifestProperty, ['all' => true]) as $value) {
            if ($value->type() === 'uri') {
                $urls[] = $value->uri();
                continue;
            }
            $urlManifest = (string) $value;
            if (filter_var($urlManifest, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                $urls[] = $urlManifest;
            }
        }

        if (!count($urls)) {
            return null;
        }

        if (!$useCollection || count($urls) === 1) {
            return reset($urls);
        }

        // The external manifest is a dynamic url with the current resource id,
        // even if it is not an item set.
        return $plugins->get('url')->__invoke('iiifserver/collection', ['id' => $resource->id()], [
            'force_canonical' => true,
        ], true);
    }
}
