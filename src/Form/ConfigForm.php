<?php
namespace IiifServer\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {
        $this
            ->add([
                'name' => 'iiifserver_manifest_version',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Default IIIF api version (manifest)', // @translate
                    'info' => 'Set the version of the manifest to provide. Note that the version is automatically selected when a request specifies it.', // @translate
                    'value_options' => [
                        '2' => '2', // @translate
                        '3' => '3', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_version',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_description_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to use for Description', // @translate
                    'info' => $this->translate('If any, the first metadata of the record will be added in all manifests and viewers for main description.') // @translate
                        . ' ' . $this->translate('It’s recommended to use "Dublin Core:Bibliographic Citation".'), // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-description-property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_attribution_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to use for Attribution', // @translate
                    'info' => 'If any, the first metadata of the resource will be added in all manifests and viewers to indicate the attribution.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-attribution-property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_attribution_default',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default attribution', // @translate
                    'info' => $this->translate('If any, and if there is no metadata for the property above, this text will be added in all manifests and viewers.') // @translate
                        . ' ' . $this->translate('It will be used as pop up in the Universal Viewer too, if enabled.'),  // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-attribution-default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_rights',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Rights', // @translate
                    'value_options' => [
                        'none' => 'No mention', // @translate
                        'text' => 'Specified text below (only for iiif 2.0)', // @translate
                        'url' => 'Specified license below', // @translate
                        'property' => 'Specified property below', // @translate
                        'property_or_text' => 'Property if any, else specified license text (only for iiif 2.0)', // @translate
                        'property_or_url' => 'Property if any, else specified license', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rights',
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_rights_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to use for rights (license)', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-rights-property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_rights_url',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'Url of the license', // @translate
                    'info' => 'The license of the item must be an url from https://creativecommons.org or https://rightsstatements.org.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rights_url',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_license_default',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default license text (only for iiif 2.0)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-license-default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_homepage',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Resource link', // @translate
                    'info' => 'In some cases, the resources are external and the link to it may be specific.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'value_options' => [
                        'none' => 'No link', // @translate
                        'site' => 'Homepage', // @translate
                        'resource' => 'Resource page', // @translate
                        'property' => 'Specified property below', // @translate
                        'property_or_resource' => 'Property if any, else resource page', // @translate
                        'property_and_resource' => 'Property if any, and resource page', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-homepage',
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_homepage_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to use for resource link', // @translate
                    'info' => 'In some cases, the resources are external and the link to it may be specific.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-homepage-property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_seealso_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to use for machine-readable "See also" links', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-seealso-property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_logo_default',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'Logo', // @translate
                    'info' => 'If any, this url to an image will be used as logo and displayed in the right panel of the Universal Viewer.',  // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-logo-default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_html_descriptive',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Link for descriptive metadata', // @translate
                    'info' => 'Some viewers display urls (for resources and uris) as plain text. This option presents them as a html link.',  // @translate
                    'documentation' => 'https://iiif.io/api/presentation/2.1/#descriptive-properties',
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_html_descriptive',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_properties_collection',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Limit properties for collection in manifest', // @translate
                    'info' => 'If empty, all public values will be output.', // @translate
                    'empty_option' => 'All', // @translate
                    'prepend_value_options' => [
                        'none' => 'None', // @translate
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_properties_collection',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_properties_item',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Limit properties for item in manifest', // @translate
                    'info' => 'If empty, all public values will be output.', // @translate
                    'empty_option' => 'All', // @translate
                    'prepend_value_options' => [
                        'none' => 'None', // @translate
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_properties_item',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_properties_media',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Limit properties for media in manifest', // @translate
                    'info' => 'If empty, all public values will be output.', // @translate
                    'empty_option' => 'All', // @translate
                    'prepend_value_options' => [
                        'none' => 'None', // @translate
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_properties_media',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_service_image',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'IIIF Image base url', // @translate
                    'info' => 'This url will be used to create the image parts of the manifests. The module ImageServer is automatically managed, but not external image servers.',  // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_service_media',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'IIIF Media base url', // @translate
                    'info' => 'This url will be used to manage media that are not image. The module ImageServer is automatically managed, but not external media servers.',  // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_service_iiifsearch',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'IIIF Search base url', // @translate
                    'info' => 'If any, this url to IIIF Search API will be used in search service and display search bar in the bottom panel of the viewer.',  // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_force_url_from',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Force base of url (from)', // @translate
                    'info' => $this->translate('When a proxy or a firewall is used, or when the config is specific, it may be needed to change the base url.')
                        . ' ' . $this->translate('For example, when the server is secured, the "http:" urls may be replaced by "https:".'), // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-force-url-from',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_force_url_to',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Force base of url (to)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver-manifest-force-url-to',
                ],
            ])
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'iiifserver_manifest_description_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_attribution_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_rights',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_rights_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_rights_url',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_homepage',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_homepage_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_seealso_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_logo_default',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_collection',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_item',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_media',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_service_image',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_service_media',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_service_iiifsearch',
                'required' => false,
            ])
        ;
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }
}
