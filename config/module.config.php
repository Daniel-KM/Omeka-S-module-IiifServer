<?php
namespace IiifServer;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'iiifCollection' => View\Helper\IiifCollection::class,
            'iiifCollectionList' => View\Helper\IiifCollectionList::class,
            'iiifForceBaseUrlIfRequired' => View\Helper\IiifForceBaseUrlIfRequired::class,
            'iiifUrl' => View\Helper\IiifUrl::class,
        ],
        'factories' => [
            'iiifInfo' => Service\ViewHelper\IiifInfoFactory::class,
            'iiifManifest' => Service\ViewHelper\IiifManifestFactory::class,
            'imageSize' => Service\ViewHelper\ImageSizeFactory::class,
            // Currently in module Next and in a pull request for core.
            'defaultSiteSlug' => Service\ViewHelper\DefaultSiteSlugFactory::class,
            'publicResourceUrl' => Service\ViewHelper\PublicResourceUrlFactory::class,
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
        'factories' => [
            Controller\ImageController::class => Service\Controller\ImageControllerFactory::class,
            Controller\MediaController::class => Service\Controller\MediaControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'jsonLd' => Mvc\Controller\Plugin\JsonLd::class,
            'tileBuilder' => Mvc\Controller\Plugin\TileBuilder::class,
            'tileInfo' => Mvc\Controller\Plugin\TileInfo::class,
            'tileServer' => Mvc\Controller\Plugin\TileServer::class,
        ],
        'factories' => [
            'imageSize' => Service\ControllerPlugin\ImageSizeFactory::class,
        ],
    ],
    'media_ingesters' => [
        'factories' => [
            'tile' => Service\Media\Ingester\TileFactory::class,
        ],
    ],
    'media_renderers' => [
        'factories' => [
            'tile' => Service\Media\Renderer\TileFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            // @todo It is recommended to use a true identifier (ark, urn…], not an internal id.

            // @link http://iiif.io/api/presentation/2.0
            // Collection     {scheme}://{host}/{prefix}/collection/{name}
            // Manifest       {scheme}://{host}/{prefix}/{identifier}/manifest
            // Sequence       {scheme}://{host}/{prefix}/{identifier}/sequence/{name}
            // Canvas         {scheme}://{host}/{prefix}/{identifier}/canvas/{name}
            // Annotation     {scheme}://{host}/{prefix}/{identifier}/annotation/{name}
            // AnnotationList {scheme}://{host}/{prefix}/{identifier}/list/{name}
            // Range          {scheme}://{host}/{prefix}/{identifier}/range/{name}
            // Layer          {scheme}://{host}/{prefix}/{identifier}/layer/{name}
            // Content        {scheme}://{host}/{prefix}/{identifier}/res/{name}.{format}

            // @link http://iiif.io/api/image/2.0
            // Image          {scheme}://{server}{/prefix}/{identifier}

            // Special route for the dynamic collections, search or browse pages.
            // The first letter "c", "i", or "m" is used to distinct collections, items and
            // media and is not required when the identifier is always unique for all of
            // resources. The default letter is "i", so it is not required when all ids are
            // items (the most common case). If the list contains only one id, the comma is
            // required to avoid confusion with a normal collection.
            // This route should be set before the "iiifserver_presentation_collection".
            'iiifserver_presentation_collection_list' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif/collection/:id',
                    'constraints' => [
                        'id' => '(?:[cim]?\-?\d+\,?)+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\PresentationController::class,
                        'action' => 'list',
                    ],
                ],
            ],

            // For collections, the spec doesn't specify a name for the manifest itself.
            // Libraries use an empty name or "manifests", "manifest.json", "manifest",
            // "{id}.json", etc. Here, an empty name is used, and a second route is added.
            // Invert the names of the route to use the generic name for the manifest itself.
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
            'iiifserver_presentation_collection_redirect' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif/collection/:id/manifest',
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
                        'action' => 'item',
                    ],
                ],
            ],
            // The redirection is not required for presentation, but a forward is possible.
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
                        'action' => 'item',
                    ],
                ],
            ],
            // A redirect to the info.json is required by the specification.
            'iiifserver_image' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif-img/:id',
                    'constraints' => [
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\ImageController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'iiifserver_image_info' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif-img/:id/info.json',
                    'constraints' => [
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\ImageController::class,
                        'action' => 'info',
                    ],
                ],
            ],
            // This route is a garbage collector that allows to return an error 400 or 501 to
            // invalid or not implemented requests, as required by specification.
            // This route should be set before the iiifserver_image in order to be
            // processed after it.
            // TODO Simplify to any number of sub elements.
            'iiifserver_image_bad' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif-img/:id/:region/:size/:rotation/:quality:.:format',
                    'constraints' => [
                        'id' => '\d+',
                        'region' => '.+',
                        'size' => '.+',
                        'rotation' => '.+',
                        'quality' => '.+',
                        'format' => '.+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\ImageController::class,
                        'action' => 'bad',
                    ],
                ],
            ],
            // Warning: the format is separated with a ".", not a "/".
            'iiifserver_image_url' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/iiif-img/:id/:region/:size/:rotation/:quality:.:format',
                    'constraints' => [
                        'id' => '\d+',
                        'region' => 'full|\d+,\d+,\d+,\d+|pct:\d+\.?\d*,\d+\.?\d*,\d+\.?\d*,\d+\.?\d*',
                        'size' => 'full|\d+,\d*|\d*,\d+|pct:\d+\.?\d*|!\d+,\d+',
                        'rotation' => '\!?(?:(?:[0-2]?[0-9]?[0-9]|3[0-5][0-9])(?:\.\d*)?|360)',
                        'quality' => 'default|color|gray|bitonal',
                        'format' => 'jpg|png|gif',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\ImageController::class,
                        'action' => 'fetch',
                    ],
                ],
            ],
            // A redirect to the info.json is required by the specification.
            'iiifserver_media' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/ixif-media/:id',
                    'constraints' => [
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\MediaController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'iiifserver_media_info' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/ixif-media/:id/info.json',
                    'constraints' => [
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\MediaController::class,
                        'action' => 'info',
                    ],
                ],
            ],
            // This route is a garbage collector that allows to return an error 400 or 501 to
            // invalid or not implemented requests, as required by specification.
            // This route should be set before the iiifserver_media in order to be
            // processed after it.
            'iiifserver_media_bad' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/ixif-media/:id:.:format',
                    'constraints' => [
                        'id' => '\d+',
                        'format' => '.+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\MediaController::class,
                        'action' => 'bad',
                    ],
                ],
            ],
            // Warning: the format is separated with a ".", not a "/".
            'iiifserver_media_url' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/ixif-media/:id:.:format',
                    'constraints' => [
                        'id' => '\d+',
                        'format' => 'pdf|mp3|ogg|mp4|webm|ogv',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\MediaController::class,
                        'action' => 'fetch',
                    ],
                ],
            ],

            // For IxIF, some json files should be available to describe media for context.
            // This is not used currently: the Wellcome uris are kept because they are set
            // for main purposes in IiifServer.
            // @link https://gist.github.com/tomcrane/7f86ac08d3b009c8af7c

            // If really needed, the two next routes may be uncommented to keep
            // compatibility with the old schemes used by the plugin for Omeka 2
            // before the version 2.4.2.
            // 'iiifserver_presentation_classic' => [
            //     'type' => \Zend\Router\Http\Segment::class,
            //     'options' => [
            //         'route' => '/:resourcename/presentation/:id',
            //         'constraints' => [
            //             'resourcename' => 'item|items|item\-set|item_set|collection|item\-sets|item_sets|collections',
            //             'id' => '\d+',
            //         ],
            //         'defaults' => [
            //             '__NAMESPACE__' => 'IiifServer\Controller',
            //             'controller' => Controller\PresentationController::class,
            //             'action' => 'manifest',
            //         ],
            //     ],
            // ],
            // 'iiifserver_presentation_manifest_classic' => [
            //     'type' => \Zend\Router\Http\Segment::class,
            //     'options' => [
            //         'route' => '/:resourcename/presentation/:id/manifest',
            //         'constraints' => [
            //             'resourcename' => 'item|items|item\-set|item_set|collection|item\-sets|item_sets|collections',
            //             'id' => '\d+',
            //         ],
            //         'defaults' => [
            //             '__NAMESPACE__' => 'IiifServer\Controller',
            //             'controller' => Controller\PresentationController::class,
            //             'action' => 'manifest',
            //         ],
            //     ],
            // ],
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
    'archiverepertory' => [
        'ingesters' => [
            'tile' => [
                'path' => 'tile',
                'extension' => [
                    '.dzi',
                    '.js',
                    // The classes are not available before the end of install.
                    // TileInfo::FOLDER_EXTENSION_DEEPZOOM,
                    '_files',
                    // TileInfo::FOLDER_EXTENSION_ZOOMIFY,
                    '_zdata',
                ],
            ],
        ],
    ],
    'iiifserver' => [
        'config' => [
            'iiifserver_manifest_description_property' => 'dcterms:bibliographicCitation',
            'iiifserver_manifest_attribution_property' => '',
            'iiifserver_manifest_attribution_default' => 'Provided by Example Organization', // @translate
            'iiifserver_manifest_license_property' => 'dcterms:license',
            'iiifserver_manifest_license_default' => 'http://www.example.org/license.html',
            'iiifserver_manifest_media_metadata' => true,
            'iiifserver_manifest_logo_default' => '',
            'iiifserver_manifest_force_url_from' => '',
            'iiifserver_manifest_force_url_to' => '',
            'iiifserver_image_creator' => 'Auto',
            'iiifserver_image_max_size' => 10000000,
            'iiifserver_image_tile_dir' => 'tile',
            'iiifserver_image_tile_type' => 'deepzoom',
        ],
    ],
];
