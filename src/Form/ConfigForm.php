<?php
namespace IiifServer\Form;

use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Zend\Form\Form;

class ConfigForm extends Form
{

    protected $api;
    protected $settings;

    public function init()
    {
        $settings = $this->getSettings();
        $properties = $this->listProperties();
        $processors = $this->listProcessors();

        $this->add([
            'type' => 'Fieldset',
            'name' => 'iiifserver_manifest',
            'options' => [
                'label' => 'IIIF Manifests', // @translate
                'info' => 'The module creates manifests with the properties from each resource (item set, item and media).' // @translate
                    . ' ' . 'The properties below are used when some metadata are missing.' // @translate
                    . ' ' . 'In all cases, empty properties are not set.' // @translate
                    /*. ' ' . 'Futhermore, the event "iiif.manifest" is available to change any data.' */, // @translate
            ],
        ]);
        $manifestFieldset = $this->get('iiifserver_manifest');

        $manifestFieldset->add([
            'type' => 'Select',
            'name' => 'iiifserver_manifest_description_property',
            'options' => [
                'label' => 'Property to use for Description', // @translate
                'info' => 'If any, the first metadata of the record will be added in all manifests and viewers for main description.' // @translate
                    . ' ' . 'It’s recommended to use "Dublin Core:Bibliographic Citation".', // @translate
                'empty_option' => 'Select a property...', // @translate
                'value_options' => $properties,
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_manifest_description_property'),
            ],
        ]);

        $manifestFieldset->add([
            'type' => 'Select',
            'name' => 'iiifserver_manifest_attribution_property',
            'options' => [
                'label' => 'Property to use for Attribution', // @translate
                'info' => 'If any, the first metadata of the resource will be added in all manifests and viewers to indicate the attribution.', // @translate
                'empty_option' => 'Select a property...', // @translate
                'value_options' => $properties,
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_manifest_attribution_property'),
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_attribution_default',
            'type' => 'Text',
            'options' => [
                'label' => 'Default Attribution', // @translate
                'info' => 'If any, and if there is no metadata for the property above, this text will be added in all manifests and viewers.' // @translate
                    . ' ' . 'It will be used as pop up in the Universal Viewer too, if enabled.',  // @translate
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_manifest_attribution_default'),
            ],
        ]);

        $manifestFieldset->add([
            'type' => 'Select',
            'name' => 'iiifserver_manifest_license_property',
            'options' => [
                'label' => 'Property to use for License', // @translate
                'info' => 'If any, the first metadata of the resource will be added in all manifests and viewers to indicate the rights.' // @translate
                    . ' ' . 'It’s recommended to use "dcterms:license".', // @translate
                'empty_option' => 'Select a property...', // @translate
                'value_options' => $properties,
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_manifest_license_property'),
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_license_default',
            'type' => 'Text',
            'options' => [
                'label' => 'Default License', // @translate
                'info' => 'If any, and if there is no metadata for the element above, this text will be added in all manifests and viewers to indicate the license.',  // @translate
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_manifest_license_default'),
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_logo_default',
            'type' => 'Url',
            'options' => [
                'label' => 'Logo', // @translate
                'info' => 'If any, this url to an image will be used as logo and displayed in the right panel of the Universal Viewer.',  // @translate
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_manifest_logo_default'),
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_force_https',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Force https', // @translate
                'info'  => 'In some cases, the json files (manifest and info) on a secured site (https) contains some urls with the scheme "http".' // @translate
                    . ' ' . 'This option forces all Omeka absolute urls in these files to start with the scheme "https".' // @translate
                    . ' ' . 'Of course, this should be unchecked on a http-only site.', // @translate
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_manifest_force_https'),
            ],
        ]);

        $this->add([
            'type' => 'Fieldset',
            'name' => 'iiifserver_image',
            'options' => [
                'label' => 'Image Service', // @translate
                'info' => 'Images may be processed internally before to be sent to browser.', // @translate
            ],
        ]);
        $imageFieldset = $this->get('iiifserver_image');

        $imageFieldset->add([
            'type' => 'Select',
            'name' => 'iiifserver_image_creator',
            'options' => [
                'label' => 'Image processor', // @translate
                'info' => 'Generally, GD is a little faster than ImageMagick, but ImageMagick manages more formats.' // @translate
                    . ' ' . 'Nevertheless, the performance depends on your installation and your server.', // @translate
                'value_options' => $processors,
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_image_creator'),
            ],
        ]);

        $imageFieldset->add([
            'name' => 'iiifserver_image_max_size',
            'type' => 'Text',
            'options' => [
                'label' => 'Max dynamic size for images', // @translate
                'info' => 'Set the maximum size in bytes for the dynamic processing of images.' // @translate
                    . ' ' .'Beyond this limit, the plugin will require a tiled image, for example made by the module OpenLayersZoom.' // @translate
                    . ' ' .'Let empty to allow processing of any image.', // @translate
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_image_max_size'),
            ],
        ]);

        $valueOptions = [
            'deepzoom' => 'Deep Zoom Image (recommended)', // @translate
            'zoomify' => 'Zoomify', // @translate
        ];
        $imageFieldset->add([
            'type' => 'Select',
            'name' => 'iiifserver_image_tile_format',
            'options' => [
                'label' => 'Tiling Format', // @translate
                'info' => 'Deep Zoom Image is an %sfree proprietary format%s from Microsoft largely supported.' // @translate
                    . ' ' . 'Zoomify is an %sold format%s that was largely supported by proprietary softwares and free viewers.' // @translate
                    . ' ' . 'All formats are served via the IIIF Server, but a viewer not compliant with IIIF may require a specific format.', // @translate
                'value_options' => $valueOptions,
            ],
            'attributes' => [
                'value' => $settings->get('iiifserver_image_tile_format'),
            ],
        ]);
    }

    /**
     * @param ApiManager $api
     */
    public function setApi(ApiManager $api)
    {
        $this->api = $api;
    }

    /**
     * @return ApiManager
     */
    protected function getApi()
    {
        return $this->api;
    }

    /**
     * @param Settings $settings
     */
    public function setSettings(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return Settings
     */
    protected function getSettings()
    {
        return $this->settings;
    }

    /**
     * Helper to prepare the true list of properties (not the internal ids).
     *
     * @return array
     */
    protected function listProperties()
    {
        $properties = [];
        $response = $this->getApi()->search('vocabularies');
        foreach ($response->getContent() as $vocabulary) {
            $options = [];
            foreach ($vocabulary->properties() as $property) {
                $options[] = [
                    'label' => $property->label(),
                    'value' => $property->term(),
                ];
            }
            if (!$options) {
                continue;
            }
            $properties[] = [
                'label' => $vocabulary->label(),
                'options' => $options,
            ];
        }
        return $properties;
    }

    /**
     * Check and return the list of available processors.
     *
     * @todo Merge with IiifServer\Module::listProcessors()
     *
     * @return array Associative array of available processors.
     */
    protected function listProcessors()
    {
        $processors = [];
        $processors['Auto'] = 'Automatic (GD when possible, else Imagick, else command line)'; // @translate
        if (extension_loaded('gd')) {
            $processors['GD'] = 'GD (php extension)'; // @translate
        }
        if (extension_loaded('imagick')) {
            $processors['Imagick'] = 'Imagick (php extension)'; // @translate
        }
        $processors['ImageMagick'] = 'ImageMagick (command line)'; // @translate
        return $processors;
    }
}
