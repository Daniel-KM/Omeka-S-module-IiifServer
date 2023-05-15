<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class IiifManifestLink extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/iiif-manifest-link';

    /**
     * Get a button with the iiif logo to copy the manifest url in clipboard.
     *
     * Managed options:
     * - version
     * - template
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        $view = $this->getView();

        if (empty($options['version'])) {
            $options['version'] = $view->setting('iiifserver_manifest_default_version', '2');
        } else {
            $options['version'] = (string) $options['version'] === '2' ? '2' : '3';
        }

        $vars = [
            'resource' => $resource,
            'version' => $options['version'],
            'options' => $options,
        ];

        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()
            ->prependStylesheet($assetUrl('css/iiif-server.css', 'IiifServer'));
        $view->headScript()
            ->appendFile($assetUrl('js/iiif-server.js', 'IiifServer'), 'text/javascript', ['defer' => 'defer']);

        $template = $options['template'] ?? self::PARTIAL_NAME;
        return $template !== self::PARTIAL_NAME && $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }
}
