<?php
namespace IiifServer;

return [
    'view_manager' => [
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'iiifCollection' => View\Helper\IiifCollection::class,
            'iiifCollection21' => View\Helper\IiifCollection21::class,
            'iiifCollection30' => View\Helper\IiifCollection30::class,
            'iiifCollectionList' => View\Helper\IiifCollectionList::class,
            'iiifCollectionList21' => View\Helper\IiifCollectionList21::class,
            'iiifCollectionList30' => View\Helper\IiifCollectionList30::class,
            'iiifManifest' => View\Helper\IiifManifest::class,
            'iiifCanvas' => View\Helper\IiifCanvas::class,
            'iiifCanvas21' => View\Helper\IiifCanvas21::class,
            'iiifCanvas30' => View\Helper\IiifCanvas30::class,
            'iiifForceBaseUrlIfRequired' => View\Helper\IiifForceBaseUrlIfRequired::class,
            'iiifUrl' => View\Helper\IiifUrl::class,
        ],
        'factories' => [
            'iiifImageUrl' => Service\ViewHelper\IiifImageUrlFactory::class,
            'iiifManifest21' => Service\ViewHelper\IiifManifest21Factory::class,
            'iiifManifest30' => Service\ViewHelper\IiifManifest30Factory::class,
            'imageSize' => Service\ViewHelper\ImageSizeFactory::class,
            // Currently in module Next and in a pull request for core.
            'defaultSiteSlug' => Service\ViewHelper\DefaultSiteSlugFactory::class,
            'publicResourceUrl' => Service\ViewHelper\PublicResourceUrlFactory::class,
            'tileInfo' => Service\ViewHelper\TileInfoFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\PresentationController::class => Controller\PresentationController::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'iiifJsonLd' => Mvc\Controller\Plugin\IiifJsonLd::class,
        ],
        'factories' => [
            'imageSize' => Service\ControllerPlugin\ImageSizeFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            // @todo It is recommended to use a true identifier (ark, urn…], not an internal id.

            // @link https://iiif.io/api/presentation/2.1/#a-summary-of-recommended-uri-patterns
            // Collection     {scheme}://{host}/{prefix}/collection/{name}
            // Manifest       {scheme}://{host}/{prefix}/{identifier}/manifest
            // Sequence       {scheme}://{host}/{prefix}/{identifier}/sequence/{name}
            // Canvas         {scheme}://{host}/{prefix}/{identifier}/canvas/{name}
            // Annotation     {scheme}://{host}/{prefix}/{identifier}/annotation/{name}
            // AnnotationList {scheme}://{host}/{prefix}/{identifier}/list/{name}
            // Range          {scheme}://{host}/{prefix}/{identifier}/range/{name}
            // Layer          {scheme}://{host}/{prefix}/{identifier}/layer/{name}
            // Content        {scheme}://{host}/{prefix}/{identifier}/res/{name}.{format}

            'iiifserver' => [
                'type' => \Zend\Router\Http\Literal::class,
                'options' => [
                    'route' => '/iiif',
                    'defaults' => [
                        '__API__' => true,
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\PresentationController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    // Special route for the dynamic collections, search or browse pages.
                    // The first letter "c", "i", or "m" is used to distinct collections, items and
                    // media and is not required when the identifier is always unique for all of
                    // resources. The default letter is "i", so it is not required when all ids are
                    // items (the most common case). If the list contains only one id, the comma is
                    // required to avoid confusion with a normal collection.
                    // This route should be set before the "iiifserver/collection".
                    'collection-list' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/collection/:id',
                            'constraints' => [
                                'id' => '(?:[cim]?\-?\d+\,?)+',
                            ],
                            'defaults' => [
                                'action' => 'list',
                            ],
                        ],
                    ],

                    // For collections, the spec doesn't specify a name for the manifest itself.
                    // Libraries use an empty name or "manifests", "manifest.json", "manifest",
                    // "{id}.json", etc. Here, an empty name is used, and a second route is added.
                    // Invert the names of the route to use the generic name for the manifest itself.
                    'collection' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/collection/:id',
                            'constraints' => [
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                'action' => 'collection',
                            ],
                        ],
                    ],
                    'collection-manifest' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/collection/:id/manifest',
                            'constraints' => [
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                'action' => 'collection',
                            ],
                        ],
                    ],
                    // The redirection is not required for presentation, but a forward is possible.
                    'manifest-id' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:id',
                            'constraints' => [
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                'action' => 'manifest',
                            ],
                        ],
                    ],
                    'manifest' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:id/manifest',
                            'constraints' => [
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                'action' => 'manifest',
                            ],
                        ],
                    ],
                    // In 2.1, canvas id is meida id and name is p + index.
                    // In 3.0, canvas id is item id and name is media id.
                    'canvas' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:id/canvas/:name',
                            'constraints' => [
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                'action' => 'canvas',
                            ],
                        ],
                    ],
                ],
            ],

            /** @deprecated */
            // For compatibility with old modules UniversalViewer, Mirador and Diva, keep some deprecated routes.
            'iiifserver_presentation_collection_list' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif/collection/:id',
                    'constraints' => [
                        'id' => '(?:[cim]?\-?\d+\,?)+',
                    ],
                    'defaults' => [
                        '__API__' => true,
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\PresentationController::class,
                        'action' => 'list',
                    ],
                ],
            ],
            'iiifserver_presentation_collection' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif/collection/:id',
                    'constraints' => [
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\PresentationController::class,
                        'action' => 'collection',
                    ],
                ],
            ],
            'iiifserver_presentation_item' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif/:id/manifest',
                    'constraints' => [
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\PresentationController::class,
                        'action' => 'manifest',
                    ],
                ],
            ],
            'iiifserver_presentation_item_redirect' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif/:id',
                    'constraints' => [
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\PresentationController::class,
                        'action' => 'manifest',
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'iiifserver' => [
        'config' => [
            'iiifserver_manifest_version' => '2.1',
            'iiifserver_manifest_description_property' => 'dcterms:bibliographicCitation',
            'iiifserver_manifest_attribution_property' => '',
            'iiifserver_manifest_attribution_default' => 'Provided by Example Organization', // @translate
            'iiifserver_manifest_license_property' => 'dcterms:license',
            'iiifserver_manifest_license_default' => 'http://www.example.org/license.html',
            'iiifserver_manifest_logo_default' => '',
            'iiifserver_manifest_html_descriptive' => true,
            'iiifserver_manifest_properties_collection' => [],
            'iiifserver_manifest_properties_item' => [],
            'iiifserver_manifest_properties_media' => [],
            'iiifserver_manifest_service_image' => '',
            'iiifserver_manifest_service_media' => '',
            // TODO Remove url_from and url_to with external image server? But it fixes proxy issues too.
            'iiifserver_manifest_force_url_from' => '',
            'iiifserver_manifest_force_url_to' => '',
            'iiifserver_manifest_service_iiifsearch' => '',
        ],
    ],
];
