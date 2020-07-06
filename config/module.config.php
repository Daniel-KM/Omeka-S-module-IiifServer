<?php
/**
 * @var string $defaultVersion
 * @var bool $versionAppend
 * @var string $prefix
 */
namespace IiifServer;

// Write the default version ("2" or "3") here (and in image server if needed).
if (!isset($defaultVersion)) {
    $defaultVersion = '';
}
if (!isset($versionAppend)) {
    $versionAppend = false;
}
// If the version is set here, the route will skip it.
$version = $versionAppend ? '' : $defaultVersion;

// Write the prefix between the top of the iiif server and the identifier.
// This option allows to manage arks identifiers not url-encoded.
// The url encoding is required for image api, but not for presentation api.
// So prefix can be "ark:/12345/".
if (!isset($prefix)) {
    $prefix = '';
}
if ($prefix) {
    $urlEncodedPrefix = rawurlencode($prefix);
    $constraintPrefix = $prefix . '|' . $urlEncodedPrefix . '|' . str_replace('%3A', ':', $urlEncodedPrefix);
    $prefix = '[:prefix]';
} else {
    $constraintPrefix = '';
    $prefix = '';
}

return [
    'view_manager' => [
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'iiifCollection' => View\Helper\IiifCollection::class,
            'iiifCollection2' => View\Helper\IiifCollection2::class,
            'iiifCollection3' => View\Helper\IiifCollection3::class,
            'iiifCollectionList' => View\Helper\IiifCollectionList::class,
            'iiifCollectionList2' => View\Helper\IiifCollectionList2::class,
            'iiifCollectionList3' => View\Helper\IiifCollectionList3::class,
            'iiifManifest' => View\Helper\IiifManifest::class,
            'iiifCanvas' => View\Helper\IiifCanvas::class,
            'iiifCanvas2' => View\Helper\IiifCanvas2::class,
            'iiifCanvas3' => View\Helper\IiifCanvas3::class,
            'iiifForceBaseUrlIfRequired' => View\Helper\IiifForceBaseUrlIfRequired::class,
        ],
        'factories' => [
            'iiifCleanIdentifiers' => Service\ViewHelper\IiifCleanIdentifiersFactory::class,
            'iiifImageUrl' => Service\ViewHelper\IiifImageUrlFactory::class,
            'iiifManifest2' => Service\ViewHelper\IiifManifest2Factory::class,
            'iiifManifest3' => Service\ViewHelper\IiifManifest3Factory::class,
            'iiifUrl' => Service\ViewHelper\IiifUrlFactory::class,
            'imageSize' => Service\ViewHelper\ImageSizeFactory::class,
            'mediaDimension' => Service\ViewHelper\MediaDimensionFactory::class,
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
    ],
    'controller_plugins' => [
        'invokables' => [
            'iiifJsonLd' => Mvc\Controller\Plugin\IiifJsonLd::class,
        ],
        'factories' => [
            'imageSize' => Service\ControllerPlugin\ImageSizeFactory::class,
            'mediaDimension' => Service\ControllerPlugin\MediaDimensionFactory::class,
        ],
    ],
    'router' => [
        // In order to use clean urls, the identifier "id" can be any string without "/", not only Omeka id.
        // A specific config file is used is used to manage identifiers with "/", like arks.
        'routes' => [
            // The Api version 2 and 3 are supported via the optional "/version".
            // When version is not indicated in url, the default version is the one set in headers, else
            // via the setting "iiifserver_manifest_version".

            // Unlike the Api Image, there is no requirement on the identifier: it may contains
            // "/" and doesn't need to be url encoded.

            // @link https://iiif.io/api/presentation/2.1/#a-summary-of-recommended-uri-patterns
            // There is no recommended uri patterns in presentation 3.0, only a generic pattern.
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
                    // A generic way to build url for all uri, even if they are not managed urls.
                    'uri' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefix:id/:type[/:name][/:subname]",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                // 'id' => '\d+',
                                'id' => '[^\/]+',
                                // Note: content resources should use the original media url, so it is just an alias.
                                // TODO Make a redirection from content resource to original url. Or the inverse so all iiif urls will be standard?
                                'type' => 'annotation-page|annotation-collection|annotation-list|annotation|canvas|collection|content-resource|manifest|range',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'generic',
                            ],
                        ],
                    ],
                    // @deprecated Use route "iiif/set" below instead: "/iiif/set?id[]=xx".
                    //
                    // Special route for the dynamic collections, search or browse pages.
                    // If the list contains only one id, the comma is required to avoid confusion
                    // with a normal collection.
                    // For compatibility with Omeka Classic requests, the id may be prefixed
                    // by a letter "c", "i", "f", or "m" to distinct collections, items and files/media.
                    // It is not required when the identifier is always unique for all of  resources.
                    // The default letter is "i", so it is not required when all ids are items (the
                    // most common case).
                    // This route should be set before the "iiifserver/collection".
                    'collection-list' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/collection/$prefix:id",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                // 'id' => '(?:[cimf]?\-?\d+\,?)+',
                                'id' => '[^/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'list',
                                'is_deprecated' => 'use /set',
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
                            'route' => "[/:version]/collection/$prefix:id",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                // "," is not allowed in order to allow deprecated collection list.
                                'id' => '[^,/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'collection',
                            ],
                        ],
                    ],
                    'collection-manifest' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/collection/$prefix:id/manifest",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'collection',
                            ],
                        ],
                    ],
                    // The redirection is not required for presentation, but a forward is possible.
                    'manifest-id' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefix:id",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'manifest',
                            ],
                        ],
                    ],
                    'manifest' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefix:id/manifest",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'manifest',
                            ],
                        ],
                    ],
                    // In 2.1, canvas id is media id and name is p + index.
                    // In 3.0, canvas id is item id and name is media id.
                    'canvas' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefix:id/canvas/:name",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'canvas',
                            ],
                        ],
                    ],

                    // Special route for the dynamic collections, search or browse pages.
                    // This route is not standard.
                    'set' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '[/:version]/set[/:id]',
                            // Ids are in the query: "?id[]=1&id[]=2", but may be "?id=1,2"
                            // or in route: "/set/1,2".
                            'constraints' => [
                                'version' => '2|3',
                                // 'id' => '(?:\d+\,?)*',
                                'id' => '[^/]*',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'list',
                            ],
                        ],
                    ],
                ],
            ],

            /* @deprecated Will be removed in Omeka version 3.0. */
            // Keep some deprecated routes for compatibility with old modules UniversalViewer, Mirador and Diva.
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
                        '__API__' => true,
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
                        '__API__' => true,
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
                        '__API__' => true,
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
            'iiifserver_manifest_default_version' => '2',
            // Content of the manifest.
            'iiifserver_manifest_description_property' => 'dcterms:bibliographicCitation',
            'iiifserver_manifest_attribution_property' => '',
            'iiifserver_manifest_attribution_default' => 'Provided by Example Organization', // @translate
            'iiifserver_manifest_rights' => 'property_or_url',
            'iiifserver_manifest_rights_property' => 'dcterms:license',
            'iiifserver_manifest_rights_url' => 'http://rightsstatements.org/vocab/CNE/1.0/',
            'iiifserver_manifest_rights_text' => '',
            'iiifserver_manifest_homepage' => 'resource',
            'iiifserver_manifest_homepage_property' => '',
            'iiifserver_manifest_seealso_property' => '',
            'iiifserver_manifest_canvas_label' => 'template',
            'iiifserver_manifest_canvas_label_property' => '',
            'iiifserver_manifest_logo_default' => '',
            'iiifserver_manifest_html_descriptive' => true,
            'iiifserver_manifest_properties_collection_whitelist' => [],
            'iiifserver_manifest_properties_item_whitelist' => [],
            'iiifserver_manifest_properties_media_whitelist' => [],
            'iiifserver_manifest_properties_collection_blacklist' => [],
            'iiifserver_manifest_properties_item_blacklist' => [],
            'iiifserver_manifest_properties_media_blacklist' => [],
            // Urls.
            'iiifserver_url_version_add' => false,
            'iiifserver_identifier_clean' => true,
            'iiifserver_identifier_prefix' => '',
            'iiifserver_identifier_raw' => false,
            // These options allows to bypass a proxy issue.
            'iiifserver_url_force_from' => '',
            'iiifserver_url_force_to' => '',
        ],
    ],
];
