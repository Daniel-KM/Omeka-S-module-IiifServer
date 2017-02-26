<?php

/*
 * Copyright 2015  Daniel Berthereau
 * Copyright 2016  BibLibre
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace UniversalViewer\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

class UniversalViewer extends AbstractHelper
{

    /**
     * Get the Universal Viewer for the provided resource.
     *
     * Proxies to {@link render()}.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options Associative array of optional values:
     *   - (string) class
     *   - (string) locale
     *   - (string) style
     *   - (string) config
     * @return string. The html string corresponding to the UniversalViewer.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $options = array())
    {
        if (empty($resource)) {
            return $this;
        }

        return $this->render($resource, $options);
    }

    /**
     * Render a universal viewer from the provided resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options Associative array of optional values:
     *   - (string) class
     *   - (string) locale
     *   - (string) style
     *   - (string) config
     * @return string
     */
    public function render(AbstractResourceEntityRepresentation $resource, $options = array())
    {
        // Determine if we should get the manifest from a field in the metadata.
        $urlManifest = '';
        $manifestProperty = $this->view->setting('universalviewer_alternative_manifest_property');
        if ($manifestProperty) {
            $urlManifest = $resource->value($manifestProperty);
        }

        $resourceName = $resource->resourceName();

        // Some specific checks.
        switch ($resourceName) {
            case 'items':
                // Currently, an item without files is unprocessable.
                if (count($resource->media()) == 0 && $urlManifest == '') {
                    // return $this->view->translate('This item has no files and is not displayable.');
                    return '';
                }
                $route = 'universalviewer_presentation_item';
                break;
            case 'item_sets':
                if ($resource->itemCount() == 0 && $urlManifest == '') {
                    // return $this->view->translate('This collection has no item and is not displayable.');
                    return '';
                }
                $route = 'universalviewer_presentation_collection';
                break;
        }

        // If manifest not provided in metadata, point to manifest created from
        // Omeka files.
        if (empty($urlManifest)) {
            $urlManifest = $this->view->url($route, array(
                'id' => $resource->id(),
            ));
            $urlManifest = $this->view->uvForceHttpsIfRequired($urlManifest);
        }

        $class = isset($options['class'])
            ? $options['class']
            : $this->view->setting('universalviewer_class');
        if (!empty($class)) {
            $class = ' ' . $class;
        }
        $locale = isset($options['locale'])
            ? $options['locale']
            : $this->view->setting('universalviewer_locale');
        if (!empty($locale)) {
            $locale = ' data-locale="' . $locale . '"';
        }
        $style = isset($options['style'])
            ? $options['style']
            : $this->view->setting('universalviewer_style');
        if (!empty($style)) {
            $style = ' style="' . $style . '"';
        }

        // Default configuration file.
        $config = empty($args['config'])
            ? $this->view->basePath('/modules/UniversalViewer/view/public/universal-viewer/config.json')
            : $args['config'];
        $urlJs = $this->view->assetUrl('js/uv/lib/embed.js', 'UniversalViewer');

        $html = sprintf('<div class="uv%s" data-config="%s" data-uri="%s"%s%s></div>',
            $class,
            $config,
            $urlManifest,
            $locale,
            $style);
        $html .= sprintf('<script type="text/javascript" id="embedUV" src="%s"></script>', $urlJs);
        $html .= '<script type="text/javascript">/* wordpress fix */</script>';
        return $html;
    }
}
