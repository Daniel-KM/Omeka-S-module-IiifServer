<?php declare(strict_types=1);

namespace IiifServer\Form;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\I18n\Translator\TranslatorAwareTrait;
use Omeka\Form\Element\PropertySelect;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use EventManagerAwareTrait;
    use TranslatorAwareTrait;

    /**
     * @var bool
     */
    protected $hasCleanUrl;

    public function init(): void
    {
        $this
            ->add([
                'name' => 'iiifserver_manifest_default_version',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Default IIIF api version of the manifest', // @translate
                    'info' => 'Set the version of the manifest to provide. Note that the version is automatically selected when a request specifies it in headers, or via the specific url (iiif/2/ or iiif/3/).', // @translate
                    'value_options' => [
                        '2' => '2', // @translate
                        '3' => '3', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_default_version',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_external_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property supplying an external manifest', // @translate
                    'info' => 'External or static manifests can be more customized and may be quicker to be loaded. Usually, the property is "dcterms:hasFormat" or "dcterms:isFormatOf".', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_external_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'fieldset_content',
                'type' => \Laminas\Form\Fieldset::class,
                'options' => [
                    'label' => 'Content of the manifest', // @translate
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
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_description_property',
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
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_attribution_property',
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
                    'id' => 'iiifserver_manifest_attribution_default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_rights',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Rights (license)', // @translate
                    'value_options' => [
                        'none' => 'No mention', // @translate
                        'text' => 'Specified text below (only for iiif 2.0)', // @translate
                        'url' => 'Specified license below', // @translate
                        'property' => 'Specified property below', // @translate
                        'property_or_text' => 'Property if any, else specified license text (only for iiif 2.0)', // @translate
                        'property_or_url' => 'Property if any, else specified license', // @translate
                    ],
                    'use_hidden_element' => true,
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
                    'label' => 'Property to use for rights', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rights_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
                'use_hidden_element' => true,
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
                'name' => 'iiifserver_manifest_rights_text',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default license text (only for iiif 2.0)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rights_text',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_homepage',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Resource link', // @translate
                    'info' => 'In some cases, the resources are external and the link to it may be specific.', // @translate
                    'empty_option' => '',
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
                    'id' => 'iiifserver_manifest_homepage',
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_homepage_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property for resource link', // @translate
                    'info' => 'In some cases, the resources are external and the link to it may be specific.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_homepage_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_seealso_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property for machine-readable "See also" links', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_seealso_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_viewing_direction_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to use for viewing direction', // @translate
                    'info' => 'If any, the first value will be added to indicate the viewing direction of the manifest.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_viewing_direction_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_viewing_direction_default',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Default viewing direction', // @translate
                    'info' => $this->translate('If any, and if there is no metadata for the property above, this value will be added in all manifests.') // @translate
                        . ' ' . $this->translate('It will be used as pop up in the Universal Viewer too, if enabled.'),  // @translate
                    'value_options' => [
                        'none' => 'None', // @translate
                        'left-to-right' => 'Left to right', // @translate
                        'right-to-left' => 'Right to left', // @translate
                        'top-to-bottom' => 'Top to bottom', // @translate
                        'bottom-to-top' => 'Bottom to top', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_viewing_direction_default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_behavior_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to use for behavior (viewing hint)', // @translate
                    'info' => 'If any, the first value will be added to indicate the viewing hint of the manifest and the canvas.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_behavior_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_behavior_default',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Default viewing hint', // @translate
                    'info' => $this->translate('If any, and if there is no metadata for the property above, these values will be added in all manifests and canvases.') // @translate
                        . ' ' . $this->translate('It will be used as pop up in the Universal Viewer too, if enabled.'),  // @translate
                    'value_options' => [
                        // Commented values are not allowed for manifest, neither canvas.
                        // @link https://iiif.io/api/presentation/3.0/#a-summary-of-property-requirements
                        'none' => 'None', // @translate
                        'auto-advance' => 'Auto-advance', // @translate
                        'continuous' => 'Continuous', // @translate
                        'facing-pages' => 'Facing pages', // @translate
                        'individuals' => 'Individuals', // @translate
                        // 'multi-part' => 'Multi-part', // @translate
                        'no-auto-advance' => 'No auto-advance', // @translate
                        // 'no-nav' => 'No nav', // @translate
                        'no-repeat' => 'No repeat', // @translate
                        'non-paged' => 'Non-paged', // @translate
                        // 'hidden' => 'Hidden', // @translate
                        'paged' => 'Paged', // @translate
                        'repeat' => 'Repeat', // @translate
                        // 'sequence' => 'Sequence', // @translate
                        // 'thumbnail-nav' => 'Thumbnail nav', // @translate
                        // 'together' => 'Together', // @translate
                        'unordered' => 'Unordered', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_behavior_default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_canvas_label',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Label for each file', // @translate
                    'info' => 'This value can be used to indicate the page number in multi-paged documents. The position is used when there is no value.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'value_options' => [
                        'position' => 'Position in sequence', // @translate
                        'template' => 'Template title', // @translate
                        'property' => 'Specified property below', // @translate
                        'source' => 'File name', // @translate
                        'template_or_source' => 'Template title, else file name', // @translate
                        'property_or_source' => 'Property if any, else file name', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_canvas_label',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select an option…', // @translate
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_canvas_label_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property for files label', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_canvas_label_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_logo_default',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'Logo of the institution', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_logo_default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_html_descriptive',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Link for descriptive metadata', // @translate
                    'info' => 'Some viewers display urls (for resources and uris) as plain text. This option presents them as a html link.',  // @translate
                    'documentation' => 'https://iiif.io/api/presentation/3.0/#31-descriptive-properties',
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_html_descriptive',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_properties_collection_whitelist',
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
                    'id' => 'iiifserver_manifest_properties_collection_whitelist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_item_whitelist',
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
                    'id' => 'iiifserver_manifest_properties_item_whitelist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_media_whitelist',
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
                    'id' => 'iiifserver_manifest_properties_media_whitelist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_properties_collection_blacklist',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Skip properties for collection in manifest', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_properties_collection_blacklist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_item_blacklist',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Skip properties for item in manifest', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_properties_item_blacklist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_media_blacklist',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Skp properties for media in manifest', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_properties_media_blacklist',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ])

            ->add([
                'name' => 'fieldset_urls',
                'type' => \Laminas\Form\Fieldset::class,
                'options' => [
                    'label' => 'Advanced options for urls', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_url_version_add',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Append version to url (to be set inside module.config.php currently)', // @translate
                    'info' => 'If set, the version will be appended to the url of the server: "iiif/3".', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_url_version_add',
                ],
            ])
            ->add([
                'name' => 'iiifserver_identifier_clean',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => $this->hasCleanUrl
                    ? 'Use the identifiers from Clean Url' // @translate
                    : 'Use the identifiers from Clean Url (unavailable)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_identifier_clean',
                ],
            ])
            ->add([
                'name' => 'iiifserver_identifier_prefix',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Prefix to use for identifier (to be set inside module.config.php currently)', // @translate
                    'info' => 'Allows to check identifiers that contains "/" from "ark:/12345/xxx" and "ark:%2F12345%2Fxxx" (example: "ark:/12345/").', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_identifier_prefix',
                ],
            ])
            ->add([
                'name' => 'iiifserver_identifier_raw',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow raw identifier', // @translate
                    'info' => 'So an ark identifier will be available as "ark:/12345/betz" and "ark:%2F12345%2Fbetz".', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_identifier_raw',
                ],
            ])

            ->add([
                'name' => 'iiifserver_url_force_from',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Force base of url (from)', // @translate
                    'info' => $this->translate('When a proxy or a firewall is used, or when the config is specific, it may be needed to change the base url.')
                        . ' ' . $this->translate('For example, when the server is secured, the "http:" urls may be replaced by "https:".'), // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_url_force_from',
                ],
            ])
            ->add([
                'name' => 'iiifserver_url_force_to',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Force base of url (to)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_url_force_to',
                ],
            ])
        ;

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'iiifserver_manifest_external_property',
                'required' => false,
            ])
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
                'name' => 'iiifserver_manifest_viewing_direction_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_viewing_direction_default',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_behavior_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_behavior_default',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_canvas_label',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_canvas_label_property',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_logo_default',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_collection_whitelist',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_item_whitelist',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_media_whitelist',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_collection_blacklist',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_item_blacklist',
                'required' => false,
            ])
            ->add([
                'name' => 'iiifserver_manifest_properties_media_blacklist',
                'required' => false,
            ])
        ;

        $filterEvent = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($filterEvent);
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }

    public function setHasCleanUrl($hasCleanUrl)
    {
        $this->hasCleanUrl = $hasCleanUrl;
        return $this;
    }
}
