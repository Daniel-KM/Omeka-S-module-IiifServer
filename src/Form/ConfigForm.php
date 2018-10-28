<?php
namespace IiifServer\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element\Text;
use Zend\Form\Element\Url;
use Zend\Form\Element\Select;
use Zend\Form\Fieldset;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {
        $processors = $this->listImageProcessors();

        $this->add([
            'name' => 'iiifserver_manifest',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'IIIF manifests', // @translate
                'info' => $this->translate('The module creates manifests with the properties from each resource (item set, item and media).') // @translate
                    . ' ' . $this->translate('The properties below are used when some metadata are missing.') // @translate
                    . ' ' . $this->translate('In all cases, empty properties are not set.') // @translate
                    /*. ' ' . 'Futhermore, the event "iiif.manifest" is available to change any data.' */, // @translate
            ],
        ]);
        $manifestFieldset = $this->get('iiifserver_manifest');

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_description_property',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Property to use for Description', // @translate
                'info' => $this->translate('If any, the first metadata of the record will be added in all manifests and viewers for main description.') // @translate
                    . ' ' . $this->translate('It’s recommended to use "Dublin Core:Bibliographic Citation".'), // @translate
                'empty_option' => 'Select a property…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a property', // @translate
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_attribution_property',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Property to use for Attribution', // @translate
                'info' => 'If any, the first metadata of the resource will be added in all manifests and viewers to indicate the attribution.', // @translate
                'empty_option' => 'Select a property…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a property', // @translate
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_attribution_default',
            'type' => Text::class,
            'options' => [
                'label' => 'Default attribution', // @translate
                'info' => $this->translate('If any, and if there is no metadata for the property above, this text will be added in all manifests and viewers.') // @translate
                    . ' ' . $this->translate('It will be used as pop up in the Universal Viewer too, if enabled.'),  // @translate
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_license_property',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Property to use for license', // @translate
                'info' => $this->translate('If any, the first metadata of the resource will be added in all manifests and viewers to indicate the rights.') // @translate
                    . ' ' . $this->translate('It’s recommended to use "dcterms:license".'), // @translate
                'empty_option' => 'Select a property…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a property', // @translate
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_license_default',
            'type' => Text::class,
            'options' => [
                'label' => 'Default license', // @translate
                'info' => 'If any, and if there is no metadata for the element above, this text will be added in all manifests and viewers to indicate the license.',  // @translate
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_logo_default',
            'type' => Url::class,
            'options' => [
                'label' => 'Logo', // @translate
                'info' => 'If any, this url to an image will be used as logo and displayed in the right panel of the Universal Viewer.',  // @translate
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_force_url_from',
            'type' => Text::class,
            'options' => [
                'label' => 'Force base of url (from)', // @translate
                'info' => $this->translate('When a proxy or a firewall is used, or when the config is specific, it may be needed to change the base url.')
                    . ' ' . $this->translate('For example, when the server is secured, the "http:" urls may be replaced by "https:".'), // @translate
            ],
        ]);

        $manifestFieldset->add([
            'name' => 'iiifserver_manifest_force_url_to',
            'type' => Text::class,
            'options' => [
                'label' => 'Force base of url (to)', // @translate
            ],
        ]);

        $this->add([
            'name' => 'iiifserver_image',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Image server', // @translate
                'info' => 'Images may be processed internally before to be sent to browser.', // @translate
            ],
        ]);
        $imageFieldset = $this->get('iiifserver_image');

        $imageFieldset->add([
            'name' => 'iiifserver_image_creator',
            'type' => Select::class,
            'options' => [
                'label' => 'Image processor', // @translate
                'info' => $this->translate('Generally, GD is a little faster than ImageMagick, but ImageMagick manages more formats.') // @translate
                    . ' ' . $this->translate('Nevertheless, the performance depends on your installation and your server.'), // @translate
                'value_options' => $processors,
            ],
        ]);

        $imageFieldset->add([
            'name' => 'iiifserver_image_max_size',
            'type' => Text::class,
            'options' => [
                'label' => 'Max dynamic size for images', // @translate
                'info' => $this->translate('Set the maximum size in bytes for the dynamic processing of images.') // @translate
                    . ' ' . $this->translate('Beyond this limit, the plugin will require a tiled image.') // @translate
                    . ' ' . $this->translate('Let empty to allow processing of any image.'), // @translate
            ],
        ]);

        $valueOptions = [
            'deepzoom' => 'Deep Zoom Image', // @translate
            'zoomify' => 'Zoomify', // @translate
        ];
        $imageFieldset->add([
            'name' => 'iiifserver_image_tile_type',
            'type' => Select::class,
            'options' => [
                'label' => 'Tiling type', // @translate
                'info' => $this->translate('Deep Zoom Image is a %sfree proprietary format%s from Microsoft largely supported.') // @translate
                    . ' ' . $this->translate('Zoomify is an %sold format%s that was largely supported by proprietary softwares and free viewers.') // @translate
                        . ' ' . $this->translate('All formats are served as native by default, but may be served as IIIF too when a viewer request it.'), // @translate
                'value_options' => $valueOptions,
            ],
        ]);

        $inputFilter = $this->getInputFilter();

        $manifestFilter = $inputFilter->get('iiifserver_manifest');
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_description_property',
            'required' => false,
        ]);
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_attribution_property',
            'required' => false,
        ]);
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_attribution_default',
            'required' => false,
        ]);
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_license_property',
            'required' => false,
        ]);
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_license_default',
            'required' => false,
        ]);
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_logo_default',
            'required' => false,
        ]);
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_force_url_from',
            'required' => false,
        ]);
        $manifestFilter->add([
            'name' => 'iiifserver_manifest_force_url_to',
            'required' => false,
        ]);

        $imageFilter = $inputFilter->get('iiifserver_image');
        $imageFilter->add([
            'name' => 'iiifserver_image_creator',
            'required' => false,
        ]);
        $imageFilter->add([
            'name' => 'iiifserver_image_max_size',
            'required' => false,
        ]);
        $imageFilter->add([
            'name' => 'iiifserver_image_tile_type',
            'required' => false,
        ]);
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }

    /**
     * Check and return the list of available processors.
     *
     * @todo Merge with IiifServer\Module::listImageProcessors()
     *
     * @return array Associative array of available processors.
     */
    protected function listImageProcessors()
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
