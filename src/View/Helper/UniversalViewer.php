<?php
namespace UniversalViewer\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

class UniversalViewer extends AbstractHelper
{

    /**
     * Get the specified UniversalViewer.
     *
     * @param $resource Omeka resource
     * @param array $options Associative array of optional values:
     *   - (string) class
     *   - (string) width
     *   - (string) height
     *   - (string) locale
     *
     * @return string. The html string corresponding to the UniversalViewer.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $options = array())
    {
        // Some specific checks.
        switch ($resource->resourceName()) {
            case 'Item':
                // Currently, item without files is unprocessable.
                if ($resource->fileCount() == 0) {
                    return __('This item has no files and is not displayable.');
                }
                break;
            case 'Collection':
                if ($resource->totalItems() == 0) {
                    return __('This collection has no item and is not displayable.');
                }
                break;
        }

        $serviceLocator = $this->view->getHelperPluginManager()->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $class = isset($options['class'])
            ? $options['class']
            : $settings->get('universalviewer_class');
        if (!empty($class)) {
            $class = ' ' . $class;
        }
        $width = isset($options['width'])
            ? $options['width']
            : $settings->get('universalviewer_width');
        if (!empty($width)) {
            $width = ' width:' . $width . ';';
        }
        $height = isset($options['height'])
            ? $options['height']
            : $settings->get('universalviewer_height');
        if (!empty($height)) {
            $height = ' height:' . $height . ';';
        }
        $locale = isset($options['locale'])
            ? $options['locale']
            : $settings->get('universalviewer_locale');
        if (!empty($locale)) {
            $locale = ' data-locale="' . $locale . '"';
        }

        $urlManifest = $this->view->url('universalviewer_presentation_manifest', array(
            'recordtype' => $resource->resourceName(),
            'id' => $resource->id(),
        ));

        $config = $this->view->basePath('/modules/UniversalViewer/view/public/universal-viewer/config.json');
        $urlJs = $this->view->basePath('/modules/UniversalViewer/view/shared/javascripts/uv/lib/embed.js');

        $html = sprintf('<div class="uv%s" data-config="%s" data-uri="%s"%s style="background-color: #000;%s%s"></div>',
            $class,
            $config,
            $urlManifest,
            $locale,
            $width,
            $height);
        $html .= sprintf('<script type="text/javascript" id="embedUV" src="%s"></script>', $urlJs);
        $html .= '<script type="text/javascript">/* wordpress fix */</script>';
        return $html;
    }
}
