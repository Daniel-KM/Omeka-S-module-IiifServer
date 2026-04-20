<?php declare(strict_types=1);

namespace IiifServer\Form;

use Common\Form\Element as CommonElement;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    use EventManagerAwareTrait;

    protected $elementGroups = [
        'infra' => 'Infrastructure', // @translate
        'image_server' => 'External image server', // @translate
        'metadata' => 'Metadata and rights', // @translate
        'bulk' => 'Bulk processing', // @translate
    ];

    public function init(): void
    {
        $this
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'iiifserver_manifest_default_version',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'infra',
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
                'name' => 'iiifserver_append_cors_headers',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Append CORS headers to web server response', // @translate
                    'info' => 'CORS ("Cross Origin Resource Sharing") headers are required to share manifests and media. They are generally managed by the web server, but, if not, they can be added here. They must not be appended multiple times, else they are disabled.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#CORS-Cross-Origin-Resource-Sharing)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_append_cors_headers',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_pretty_json',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Output pretty indented json', // @translate
                    'info' => 'Recommended only if your server zip json automatically.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_pretty_json',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_cache',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Cache', // @translate
                    'info' => 'A cache may be required when there are more than 100 to 1000 media, depending on server, or when there are many visitors.', // @translate
                    'value_options' => [
                        '1' => 'Cache manifest for instant access', // @translate'
                        '0' => 'Create manifest in real time', // @translate'
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_cache',
                ],
            ])

            ->add([
                'name' => 'fieldset_more',
                'type' => \Laminas\Form\Fieldset::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Other options', // @translate
                ],
            ])

            // The option is the same in module IIIF Search.
            // TODO Make option to match image and xml an option to set in a property of the item.
            ->add([
                'name' => 'iiifserver_xml_image_match',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Match images and xmls when they are multiple', // @translate
                    'value_options' => [
                        'order' => 'Media order (page_001.jpg, alto_001.xml, page_002.jpg, alto_002.xml, …)', // @translate
                        'basename' => 'Media source base filename (page_001.jpg, page_002.jpg, page_002.xml, page_001.xml…)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_xml_image_match',
                    'value' => 'order',
                ],
            ])

            // The option is the same in module IIIF Search.
            ->add([
                'name' => 'iiifserver_xml_fix_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Fix bad xml and invalid utf-8 characters', // @translate
                    'value_options' => [
                        'no' => 'No', // @translate
                        'dom' => 'Via DOM (quick)', // @translate
                        'regex' => 'Via regex (slow)', // @translate
                        'all' => 'All', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_xml_fix_mode',
                    'value' => 'no',
                ],
            ])

            ->add([
                'name' => 'iiifserver_access_resource_skip',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Skip check of access rights to files for module Access', // @translate
                    'info' => 'If set, all public and restricted files will be displayed.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_access_resource_skip',
                ],
            ])

            ->add([
                'name' => 'iiifserver_access_ocr_skip',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Hide OCR for reserved resources for module Access', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_access_ocr_skip',
                ],
            ])

            ->add([
                'name' => 'fieldset_urls',
                'type' => \Laminas\Form\Fieldset::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Advanced options for urls', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_url_version_add',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Append version to url (to be set inside module.config.php currently)', // @translate
                    'info' => 'If set, the version will be appended to the url of the server: "iiif/3".', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_url_version_add',
                ],
            ])
            ->add([
                'name' => 'iiifserver_identifier_clean',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => class_exists('CleanUrl\Module', false)
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
                    'element_group' => 'infra',
                    'label' => 'Prefix to use for identifier (to be set inside module.config.php currently)', // @translate
                    'info' => 'Allows to check identifiers that contains "/" from "ark:/12345/xxx" and "ark:%2F12345%2Fxxx" (example: "ark:/12345/").', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_identifier_prefix',
                ],
            ])
            // The settings "iiifserver_identifier_raw" and
            // "iiifserver_identifier_apache_preencoding" have been removed.
            // The setting "iiifserver_identifier_encode_slash" is auto-detected
            // and does not need a form element.

            ->add([
                'name' => 'iiifserver_url_force_from',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Force base of url (from)', // @translate
                    'info' => 'When a proxy or a firewall is used, or when the config is specific, it may be needed to change the base url. For example, when the server is secured, the "http:" urls may be replaced by "https:".', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_url_force_from',
                ],
            ])
            ->add([
                'name' => 'iiifserver_url_force_to',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'infra',
                    'label' => 'Force base of url (to)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_url_force_to',
                ],
            ])

            // TODO Use the json from the image server.
            // The same keys are used in the module Image Server.

            ->add([
                'name' => 'iiifserver_media_api_url',
                'type' => Element\Url::class,
                'options' => [
                    'element_group' => 'image_server',
                    'label' => 'External image server base url (required to use an external server)', // @translate
                    'info' => 'When using an external server like Cantaloupe or IIPImage. this url must be set, for example: https://iiif.example.org/iiif. All IIIF image urls will be rewritten to use this base instead of the Omeka one.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_media_api_url',
                ],
            ])
        ;

        // When the module ImageServer is installed, the following settings are
        // owned by its own config form. Hide them here to avoid duplicate UI.
        // The URL field above remains visible because it allows to override the
        // local image server with an external one.
        if (class_exists('ImageServer\Module', false)) {
            $this->add([
                'name' => 'iiifserver_media_api_note',
                'type' => CommonElement\Note::class,
                'options' => [
                    'element_group' => 'image_server',
                    'text' => 'The remaining image server settings are configured in the tab of the module Image Server.', // @translate
                ],
            ]);
        } else {
            $this
                ->add([
                    'name' => 'iiifserver_media_api_default_version',
                    'type' => CommonElement\OptionalRadio::class,
                    'options' => [
                        'element_group' => 'image_server',
                        'label' => 'Default IIIF image api version', // @translate
                        'info' => 'Set the version of the iiif info.json to provide. The image server should support it.', // @translate
                        'value_options' => [
                            '0' => 'No image server', // @translate
                            '1' => 'Image Api 1', // @translate
                            '2' => 'Image Api 2', // @translate
                            '3' => 'Image Api 3', // @translate
                        ],
                    ],
                    'attributes' => [
                        'id' => 'iiifserver_media_api_default_version',
                        'required' => true,
                    ],
                ])

                ->add([
                    'name' => 'iiifserver_media_api_supported_versions',
                    'type' => CommonElement\OptionalMultiCheckbox::class,
                    'options' => [
                        'element_group' => 'image_server',
                        'label' => 'Supported IIIF image api versions and max compliance level', // @translate
                        'value_options' => [
                            'v1' => [
                                'label' => 'Image API 1',
                                'options' => [
                                    '1/0' => 'Level 0', // @translate
                                    '1/1' => 'Level 1', // @translate
                                    '1/2' => 'Level 2', // @translate
                                ],
                            ],
                            'v2' => [
                                'label' => 'Image API 2',
                                'options' => [
                                    '2/0' => 'Level 0', // @translate
                                    '2/1' => 'Level 1', // @translate
                                    '2/2' => 'Level 2', // @translate
                                ],
                            ],
                            'v3' => [
                                'label' => 'Image API 3',
                                'options' => [
                                    '3/0' => 'Level 0', // @translate
                                    '3/1' => 'Level 1', // @translate
                                    '3/2' => 'Level 2', // @translate
                                ],
                            ],
                        ],
                    ],
                    'attributes' => [
                        'id' => 'iiifserver_media_api_supported_versions',
                    ],
                ])

                ->add([
                    'name' => 'iiifserver_media_api_version_append',
                    'type' => CommonElement\OptionalCheckbox::class,
                    'options' => [
                        'element_group' => 'image_server',
                        'label' => 'Append the version to the url (to be set inside module.config.php currently)', // @translate
                        'info' => 'If set, the version will be appended to the url of the server: "iiif/3".', // @translate
                    ],
                    'attributes' => [
                        'id' => 'iiifserver_media_api_version_append',
                    ],
                ])


                /**
                ->add([
                    'name' => 'iiifserver_media_api_prefix',
                    'type' => Element\Text::class,
                    'options' => [
                        'element_group' => 'image_server',
                        'label' => 'Append a prefix to the url (to be set inside module.config.php currently)', // @ translate
                        'info' => 'If set, the prefix will be added after the version: "iiif/3/xxx".', // @ translate
                    ],
                    'attributes' => [
                        'id' => 'iiifserver_media_api_prefix',
                    ],
                ])
                */

                ->add([
                    'name' => 'iiifserver_media_api_identifier',
                    'type' => CommonElement\OptionalRadio::class,
                    'options' => [
                        'element_group' => 'image_server',
                        'label' => 'Media identifier', // @translate
                        'info' => 'Using the full filename with extension for images allows to use an image server like Cantaloupe sharing the Omeka original files directory. In other cases, this option is not recommended because the identifier should not have an extension.', // @translate
                        'value_options' => [
                            'default' => 'Default', // @translate
                            'media_id' => 'Media id', // @translate
                            'storage_id' => 'Filename', // @translate
                            'filename' => 'Filename with extension (all)', // @translate
                            'filename_image' => 'Filename with extension (image only)', // @translate
                        ],
                    ],
                    'attributes' => [
                        'id' => 'iiifserver_media_api_identifier',
                        'required' => true,
                    ],
                ])
            ;
        }

        $this
            ->add([
                'name' => 'iiifserver_media_api_identifier_infojson',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'image_server',
                    'label' => 'Append "info.json" to the image iiif identifier', // @translate
                    'info' => 'May be required with an external image server that doesn’t manage the url rewriting to /info.json (iiif specification requires a redirection with http 303).', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_media_api_identifier_infojson',
                ],
            ])

            ->add([
                'name' => 'iiifserver_media_api_support_non_image',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'image_server',
                    'label' => 'The server support non-image files', // @translate
                    'info' => 'If unchecked, audio, video, models, pdf, etc. will be served through Omeka.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_media_api_support_non_image',
                ],
            ])

            ->add([
                'name' => 'iiifserver_media_api_fix_uv_mp3',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'image_server',
                    'label' => 'Use "audio/mp4" instead of "audio/mpeg" (fix playing mp3 in Universal Viewer v4)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_media_api_fix_uv_mp3',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_external_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                    'element_group' => 'metadata',
                    'label' => 'Content of the manifest', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_summary_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Property to use for summary or description', // @translate
                    'info' => 'If any, the first metadata of the record will be added in all manifests and viewers for main description. It’s recommended to use "Dublin Core:Bibliographic Citation".', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'prepend_value_options' => [
                        'template' => 'Template description', // @translate
                    ],
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_summary_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_attribution_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                    'element_group' => 'metadata',
                    'label' => 'Default attribution', // @translate
                    'info' => 'If any, and if there is no metadata for the property above, this text will be added in all manifests and viewers. It will be used as pop up in the Universal Viewer too, if enabled.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_attribution_default',
                    'data-placeholder' => 'Provided by Example Organization', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_rights',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Rights (license)', // @translate
                    'value_options' => [
                        'none' => 'No mention', // @translate
                        'text' => 'Specified text below (only for iiif 2.0)', // @translate
                        'url' => 'Specified license below', // @translate
                        'property' => 'Property specified below', // @translate
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'name' => 'iiifserver_manifest_rights_uri',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Uri of the license or rights', // @translate
                    // TODO See https://iiif.io/api/presentation/3.0/#rights: uri are http but rendered as https.
                    // It should be http:// rendered as https by clients, but
                    // there is an example with https:// in https://iiif.io/api/presentation/3.0/#b-example-manifest-response.
                    'value_options' => [
                        '' => 'Uri below', // @translate
                        // CreativeCommons.
                        'creative-commons-0' => [
                            'label' => 'Creative Commons 0', // @translate
                            'options' => [
                                'https://creativecommons.org/publicdomain/zero/1.0/' => 'Creative Commons CC0 Universal Public Domain Dedication', // @translate
                            ],
                        ],
                        // v3 international
                        'creative-commons-3' => [
                            'label' => 'Creative Commons 3.0 International', // @translate
                            'options' => [
                                'https://creativecommons.org/licenses/by/3.0/' => 'Creative Commons Attribution 3.0 International', // @translate
                                'https://creativecommons.org/licenses/by-sa/3.0/' => 'Creative Commons Attribution-ShareAlike 3.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nc/3.0' => 'Creative Commons Attribution-NonCommercial 3.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nc-sa/3.0' => 'Creative Commons Attribution-NonCommercial-ShareAlike 3.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nc-nd/3.0' => 'Creative Commons Attribution-NonCommercial-NoDerivatives 3.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nd/3.0' => 'Creative Commons Attribution-NoDerivatives 3.0 International', // @translate
                            ],
                        ],
                        // v4 international
                        'creative-commons-4' => [
                            'label' => 'Creative Commons 4.0 International', // @translate
                            'options' => [
                                'https://creativecommons.org/licenses/by/4.0/' => 'Creative Commons Attribution 4.0 International', // @translate
                                'https://creativecommons.org/licenses/by-sa/4.0/' => 'Creative Commons Attribution-ShareAlike 4.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nc/4.0' => 'Creative Commons Attribution-NonCommercial 4.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nc-sa/4.0' => 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nc-nd/4.0' => 'Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International', // @translate
                                'https://creativecommons.org/licenses/by-nd/4.0' => 'Creative Commons Attribution-NoDerivatives 4.0 International', // @translate
                            ],
                        ],
                        // RightsStatements.
                        'rights-statements' => [
                            'label' => 'Rights Statements', // @translate
                            'options' => [
                                'https://rightsstatements.org/vocab/InC/1.0/' => 'In Copyright', // @translate
                                'https://rightsstatements.org/vocab/InC-RUU/1.0/' => 'In Copyright - Rights-holder(s) Unlocatable or Unidentifiable', // @translate
                                'https://rightsstatements.org/vocab/InC-NC/1.0/' => 'In Copyright - Non-Commercial Use Permitted', // @translate
                                'https://rightsstatements.org/vocab/InC-EDU/1.0/' => 'In Copyright - Educational Use Permitted', // @translate
                                'https://rightsstatements.org/vocab/InC-OW-EU/1.0/' => 'In Copyright - EU Orphan Work', // @translate
                                'https://rightsstatements.org/vocab/NoC-OKLR/1.0/' => 'No Copyright - Other Known Legal Restrictions', // @translate
                                'https://rightsstatements.org/vocab/NoC-CR/1.0/' => 'No Copyright - Contractual Restrictions', // @translate
                                'https://rightsstatements.org/vocab/NoC-NC/1.0/' => 'No Copyright - Non-Commercial Use Only', // @translate
                                'https://rightsstatements.org/vocab/NoC-US/1.0/' => 'No Copyright - United States', // @translate
                                'https://rightsstatements.org/vocab/NKC/1.0/' => 'No Known Copyright', // @translate
                                'https://rightsstatements.org/vocab/UND/1.0/' => 'Copyright Undetermined', // @translate
                                'https://rightsstatements.org/vocab/CNE/1.0/' => 'Copyright Not Evaluated', // @translate
                            ],
                        ],
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rights_uri',
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_rights_url',
                'type' => Element\Url::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Uri of the rights/license when unselected above', // @translate
                    'info' => 'For IIIF v3, the license of the item must be an url from https://creativecommons.org or https://rightsstatements.org.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rights_url',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_rights_text',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Default license text (only for iiif 2.0)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rights_text',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_homepage',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Resource page', // @translate
                    'info' => 'In some cases, the resources are external and the link to it may be specific.', // @translate
                    'value_options' => [
                        'none' => 'No link', // @translate
                        'property' => 'Property specified below', // @translate
                        'resource' => 'Resource page (default site)', // @translate
                        'resources' => 'Resource pages (all sites)', // @translate
                        'property_or_resource' => 'Property if any, else resource page (defaut site)', // @translate
                        'property_or_resources' => 'Property if any, else resource pages (all sites)', // @translate
                        'site' => 'Default site home page (not recommended)', // @translate
                        'sites' => 'Site home pages (not recommended)', // @translate
                        'api' => 'Api (not recommended)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_homepage',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_homepage_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'name' => 'iiifserver_manifest_provider',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Provider', // @translate
                    'info' => 'An organization or person that contributed to providing the content of the resource. The address, web site, logo, etc. can be appended.', // @translate
                    'documentation' => 'https://iiif.io/api/presentation/3.0/#provider',
                    'value_options' => [
                        'none' => 'None', // @translate
                        'property' => 'Property specified below', // @translate
                        'agent' => 'Agent specified below', // @translate
                        'simple' => 'Simple data from main parameters', // @translate
                        'property_or_agent' => 'Property if any, else agent', // @translate
                        'property_or_simple' => 'Property if any, else simple', // @translate
                        // TODO Add a resource (and a value resource).
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_provider',
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_provider_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Property for provider', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_provider_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'iiifserver_manifest_provider_agent',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Provider (as json)', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_provider_agent',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_seealso_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'name' => 'iiifserver_manifest_rendering_skip',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Skip "rendering" links in manifest (download)', // @translate
                    'info' => 'When checked, viewers will not display a download button. When module Access is active, "rendering" is always exposed only for resources with status "free".', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rendering_skip',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_rendering_media_types',
                'type' => CommonElement\MediaTypeSelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Media types of files to include in download', // @translate
                    'prepend_value_options' => [
                        'none' => 'None', // @translate
                        'all' => 'All', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_rendering_media_types',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select media-types to download', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_start_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Property to set the start page (may be an index, a media or a time)', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_start_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_start_primary_media',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Use the primary media as start page, except when property above is filled', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_start_primary_media',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_structures_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Property for structures', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'info' => 'Refer to the following URL for the input format.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents',
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_structures_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_structures_skip_flat',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Skip the flat structure appended when no structure is set', // @translate
                    'info' => 'This flat structure can fix some issues on old versions of viewers.',  // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_structures_skip_flat',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_viewing_direction_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Default viewing direction', // @translate
                    'info' => 'If any, and if there is no metadata for the property above, this value will be added in all manifests.', // @translate
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
                'name' => 'iiifserver_manifest_placeholder_canvas_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Property to use in item or media to set a placeholder canvas for waiting or warning', // @translate
                    'info' => 'May be a url to a placeholder file, a list of media to protect, a string with the value below, or a boolean value, in which case the default placeholder canvas is used.', // @translate
                    'documentation' => 'https://iiif.io/api/presentation/3.0/#placeholdercanvas',
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_placeholder_canvas_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_placeholder_canvas_value',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Value to match to display the placeholder canvas', // @translate
                    'info' => 'This option is used only when the property above is a string, for example "Informed public". The warning with the url below will be displayed when the property has this value.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_placeholder_canvas_value',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_placeholder_canvas_default',
                'type' => Element\Url::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Url to use as a default placeholder canvas', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_placeholder_canvas_default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_behavior_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Default behavior (viewing hint)', // @translate
                    'info' => 'If any, and if there is no metadata for the property above, these values will be added in all manifests and canvases.', // @translate
                    'value_options' => [
                        // Commented values are not allowed for manifest, neither canvas.
                        // @link https://iiif.io/api/presentation/3.0/#a-summary-of-property-requirements
                        'none' => 'None', // @translate
                        // Temporal behaviors.
                        'auto-advance' => 'Auto-advance', // @translate
                        'no-auto-advance' => 'No auto-advance', // @translate
                        'repeat' => 'Repeat', // @translate
                        'no-repeat' => 'No repeat', // @translate
                        // Layout behaviors.
                        'unordered' => 'Unordered', // @translate
                        'individuals' => 'Individuals', // @translate
                        'continuous' => 'Continuous', // @translate
                        'paged' => 'Paged', // @translate
                        'facing-pages' => 'Facing pages', // @translate
                        'non-paged' => 'Non-paged', // @translate
                        // Collection behaviors.
                        // 'multi-part' => 'Collection: Multi-part', // @translate
                        // 'together' => 'Collection: Together', // @translate
                        // Range behaviors.
                        // 'sequence' => 'Range: Sequence', // @translate
                        // 'thumbnail-nav' => 'Range: Thumbnail nav', // @translate
                        // 'no-nav' => 'Range: No nav', // @translate
                        // Miscellaneous behaviors.
                        // 'hidden' => 'Hidden', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_behavior_default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_canvas_label',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Label for each file', // @translate
                    'info' => 'This value can be used to indicate the page number in multi-paged documents. The position is used when there is no value.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                    'value_options' => [
                        'position' => 'Position in sequence', // @translate
                        'template' => 'Template title', // @translate
                        'property' => 'Property specified below', // @translate
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                    'element_group' => 'metadata',
                    'label' => 'Logo of the institution', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_logo_default',
                ],
            ])

            ->add([
                'name' => 'iiifserver_manifest_html_descriptive',
                'type' => CommonElement\OptionalCheckbox::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
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
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'metadata',
                    'label' => 'Skip properties for media in manifest', // @translate
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
        ;

        $this
            ->add([
                'name' => 'fieldset_cache',
                'type' => Fieldset::class,
                'options' => [
                    'element_group' => 'bulk',
                    'label' => 'Cache manifests', // @translate
                ],
            ]);
        $fieldset = $this->get('fieldset_cache');
        $fieldset
            ->add([
                'name' => 'query_cache',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'element_group' => 'bulk',
                    'label' => 'Query to filter items to cache', // @translate
                    'info' => 'This query will be used to select all items whose attached images, audio and video files will be prepared in the background.', // @translate
                    'documentation' => 'https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview',
                ],
                'attributes' => [
                    'id' => 'query_cache',
                ],
            ])
            ->add([
                'name' => 'process_cache',
                'type' => Element\Submit::class,
                'options' => [
                    'element_group' => 'bulk',
                    'label' => ' ',
                ],
                'attributes' => [
                    'id' => 'process_cache',
                    'value' => 'Run caching', // @translate
                ],
            ])
        ;

        // Available in module Derivative Media.
        $this
            ->add([
                'name' => 'fieldset_dimensions',
                'type' => Fieldset::class,
                'options' => [
                    'element_group' => 'bulk',
                    'label' => 'Store dimensions', // @translate
                ],
            ]);
        $fieldset = $this->get('fieldset_dimensions');
        $fieldset
            ->add([
                'name' => 'query',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'element_group' => 'bulk',
                    'label' => 'Query to filter items to size', // @translate
                    'info' => 'This query will be used to select all items whose attached images, audio and video files will be prepared in the background.', // @translate
                    'documentation' => 'https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview',
                ],
                'attributes' => [
                    'id' => 'query',
                ],
            ])
            ->add([
                'name' => 'process_dimensions',
                'type' => Element\Submit::class,
                'options' => [
                    'element_group' => 'bulk',
                    'label' => ' ',
                ],
                'attributes' => [
                    'id' => 'process_dimensions',
                    'value' => 'Run sizing', // @translate
                ],
            ])
        ;

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $filterEvent = new Event('form.add_input_filters', $this, ['inputFilter' => $this->getInputFilter()]);
        $this->getEventManager()->triggerEvent($filterEvent);
    }
}
