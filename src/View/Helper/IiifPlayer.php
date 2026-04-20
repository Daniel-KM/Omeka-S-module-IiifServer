<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Render an IIIF player block (overlay triggered by a button or any custom
 * trigger) for any resource (item, item set, media). Usable from theme
 * templates to instantiate multiple players per page (e.g. OpenSeadragon on a
 * thumbnail click + Mirador on a secondary "advanced viewer" button).
 */
class IiifPlayer extends AbstractHelper
{
    /**
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options
     *     - player (string):
     *     diva|mirador|universalviewer|mirador_core|openseadragon_core.
     *         When absent, falls back to site setting `iiifserver_player`.
     *     - inline (bool): render the player directly without button/overlay.
     *     - height (string): stage height in inline mode (default: 600px).
     *     - trigger (string): raw HTML replacing the button label.
     *     - label (string): button text (used only if `trigger` is not set).
     *     - lazy (bool): override site setting (ignored in inline mode).
     *     - sidebarPosition (string): top|bottom|left|right (OSD only).
     */
    public function __invoke(
        AbstractResourceEntityRepresentation $resource,
        array $options = []
    ): string {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $siteSettings = $plugins->get('siteSetting');

        $player = $options['player'] ?? $siteSettings('iiifserver_player', 'openseadragon_core');
        $inline = (bool) ($options['inline'] ?? false);
        $lazy = $inline
            ? false
            : (array_key_exists('lazy', $options)
                ? (bool) $options['lazy']
                : (bool) $siteSettings('iiifserver_player_button_lazy', false));
        $label = $options['label'] ?? $siteSettings('iiifserver_player_button_label', $view->translate('Open IIIF viewer'));
        $trigger = $options['trigger'] ?? null;
        $sidebarPosition = $options['sidebarPosition']
            ?? $siteSettings('iiifserver_player_osd_sidebar', 'bottom');
        $height = $options['height'] ?? $siteSettings('iiifserver_player_inline_height', '600px');

        return $view->partial('common/iiif-player', [
            'resource' => $resource,
            'player' => $player,
            'label' => $label,
            'trigger' => $trigger,
            'lazy' => $lazy,
            'sidebarPosition' => $sidebarPosition,
            'inline' => $inline,
            'height' => $height,
        ]);
    }
}
