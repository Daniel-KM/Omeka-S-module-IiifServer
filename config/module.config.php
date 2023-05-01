<?php declare(strict_types=1);
/**
 * Some variables should be manually set for now.
 *
 * @var string $defaultVersion
 * @var bool $versionAppend
 * @var string $prefix
 *
 * @var string $defaultVersionMedia
 * @var bool $versionAppendMedia
 * @var string $prefixMedia
 *
 * Unlike presentation api, the identifier must be url encoded in image api.
 * Nevertheless, when a prefix is set in the config, it can be bypassed to allow
 * the raw or the url-encoded identifier.
 * @link https://iiif.io/api/image/3.0/#9-uri-encoding-and-decoding
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

// Idem to get urls for the image/media server.

// Write the default version ("2" or "3") here (and in image server if needed).
if (!isset($defaultVersionMedia)) {
    $defaultVersionMedia = '';
}
if (!isset($versionAppendMedia)) {
    $versionAppendMedia = false;
}
// If the version is set here, the route will skip it.
$versionMedia = $versionAppendMedia ? '' : $defaultVersionMedia;

// Write the prefix between the top of the iiif server and the identifier.
// This option allows to manage arks identifiers not url-encoded.
// The url encoding is required for image api, but not for presentation api.
// So prefix can be "ark:/12345/".
if (!isset($prefixMedia)) {
    $prefixMedia = '';
}
if ($prefixMedia) {
    $urlEncodedPrefixMedia = rawurlencode($prefixMedia);
    $constraintPrefixMedia = $prefixMedia . '|' . $urlEncodedPrefixMedia . '|' . str_replace('%3A', ':', $urlEncodedPrefixMedia);
    $prefixMedia = '[:prefix]';
} else {
    $constraintPrefixMedia = '';
    $prefixMedia = '';
}

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
            'iiifAnnotationPageLine' => View\Helper\IiifAnnotationPageLine::class,
            'iiifAnnotationPageLine2' => View\Helper\IiifAnnotationPageLine2::class,
            'iiifAnnotationPageLine3' => View\Helper\IiifAnnotationPageLine3::class,
            'iiifCollection' => View\Helper\IiifCollection::class,
            'iiifCollection2' => View\Helper\IiifCollection2::class,
            'iiifCollection3' => View\Helper\IiifCollection3::class,
            'iiifCollectionList' => View\Helper\IiifCollectionList::class,
            'iiifCollectionList2' => View\Helper\IiifCollectionList2::class,
            'iiifCollectionList3' => View\Helper\IiifCollectionList3::class,
            'iiifCanvas' => View\Helper\IiifCanvas::class,
            'iiifCanvas2' => View\Helper\IiifCanvas2::class,
            'iiifCanvas3' => View\Helper\IiifCanvas3::class,
            'iiifInfo' => View\Helper\IiifInfo::class,
            'iiifManifest' => View\Helper\IiifManifest::class,
            'iiifManifestExternal' => View\Helper\IiifManifestExternal::class,
        ],
        'factories' => [
            'iiifCleanIdentifiers' => Service\ViewHelper\IiifCleanIdentifiersFactory::class,
            'iiifInfo2' => Service\ViewHelper\IiifInfo2Factory::class,
            'iiifInfo3' => Service\ViewHelper\IiifInfo3Factory::class,
            'iiifMediaUrl' => Service\ViewHelper\IiifMediaUrlFactory::class,
            'iiifManifest2' => Service\ViewHelper\IiifManifest2Factory::class,
            'iiifManifest3' => Service\ViewHelper\IiifManifest3Factory::class,
            'iiifTileInfo' => Service\ViewHelper\IiifTileInfoFactory::class,
            'iiifUrl' => Service\ViewHelper\IiifUrlFactory::class,
            'imageSize' => Service\ViewHelper\ImageSizeFactory::class,
            'mediaDimension' => Service\ViewHelper\MediaDimensionFactory::class,
            'rangeToArray' => Service\ViewHelper\RangeToArrayFactory::class,
            // Currently in module Next and in a pull request for core.
            'defaultSiteSlug' => Service\ViewHelper\DefaultSiteSlugFactory::class,
            'publicResourceUrl' => Service\ViewHelper\PublicResourceUrlFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\OptionalUrl::class => Form\Element\OptionalUrl::class,
        ],
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\NoopServerController::class => Controller\NoopServerController::class,
            Controller\PresentationController::class => Controller\PresentationController::class,
        ],
        'factories' => [
            Controller\MediaController::class => Service\Controller\MediaControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'iiifImageJsonLd' => Mvc\Controller\Plugin\IiifImageJsonLd::class,
            'iiifJsonLd' => Mvc\Controller\Plugin\IiifJsonLd::class,
            'rangeToArray' => Mvc\Controller\Plugin\RangeToArray::class,
        ],
        'factories' => [
            'fixUtf8' => Service\ControllerPlugin\FixUtf8Factory::class,
            'imageSize' => Service\ControllerPlugin\ImageSizeFactory::class,
            'mediaDimension' => Service\ControllerPlugin\MediaDimensionFactory::class,
        ],
    ],
    'router' => [
        // In order to use clean urls, the identifier "id" can be any string without "/", not only Omeka id.
        // A specific config file is used is used to manage identifiers with "/", like arks.

        // The routes for the image server are set too: the IIIF server should
        // be able to create urls to it, whether it is the module Image Server
        // or an external one.

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
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/iiif',
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\PresentationController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    // A generic way to build url for all uri, even if they are not managed urls.
                    'uri' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefix:id/:type[/:name][/:subtype][/:subname]",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                // 'id' => '\d+',
                                'id' => '[^\/]+',
                                // Note: content resources should use the original media url, so it is just an alias.
                                // TODO Make a redirection from content resource to original url. Or the inverse so all iiif urls will be standard?
                                // "canvas-segment" may be used as id (uri, not a real url) for start: https://iiif.io/api/presentation/3.0/#start. Nevertheless, it is not used for now (use subtype).
                                'type' => 'annotation-page|annotation-collection|annotation-list|annotation|canvas-segment|canvas|collection|content-resource|manifest|range',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'generic',
                            ],
                        ],
                    ],

                    // For collections, the spec doesn't specify a name for the manifest itself.
                    // Libraries use an empty name or "manifests", "manifest.json", "manifest",
                    // "{id}.json", etc. Here, an empty name is used, and a second route is added.
                    // Invert the names of the route to use the generic name for the manifest itself.
                    'collection' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/collection/$prefix:id",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'collection',
                            ],
                        ],
                    ],
                    'collection-manifest' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/collection/$prefix:id/manifest",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'collection',
                            ],
                        ],
                    ],
                    // The redirection is not required for presentation, but a forward is possible.
                    'id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefix:id",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'id',
                            ],
                        ],
                    ],
                    // The type "manifest" is included in route "uri", but allows to create the url simpler.
                    'manifest' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefix:id/manifest",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'manifest',
                            ],
                        ],
                    ],

                    // Special route for the dynamic collections, search or browse pages.
                    // This route is not standard.
                    'set' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '[/:version]/set[/:id]',
                            // Ids are in the query: "?id[]=1&id[]=2", but may be "?id=1,2"
                            // or in route: "/set/1,2".
                            'constraints' => [
                                'version' => '2|3',
                                // 'id' => '(?:\d+\,?)*',
                                'id' => '[^\/]*',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'list',
                            ],
                        ],
                    ],
                ],
            ],

            // The following routes allow to build the urls to the media
            // on the image server. They are required even when there is
            // no image server. The real urls may be rerouted by the web
            // server (apache/nginx) or overridden by the module Image Server.

            // The Api version 2 and 3 are supported via the optional "/version".
            // When version is not indicated in url, the default version is the one set in headers, else
            // via the setting "iiifserver_media_api_default_version".

            // @link http://iiif.io/api/image/2.0
            // @link http://iiif.io/api/image/3.0
            // Image          {scheme}://{server}{/prefix}/{identifier}

            'imageserver' => [
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/iiif',
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\NoopServerController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    // This is the same url than the manifest, so a check is done
                    // to redirect to "manifest" or "info.json", that is the
                    // image server if the web server proxies to it.
                    // It allows to check rights too (the image server may not
                    // aware of it).
                    // This is the uri of the image (@id) and the base url.
                    // The specification requires a 303 redirect to the info.json.
                    'id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefixMedia:id",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefixMedia,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $versionMedia,
                                'action' => 'id',
                            ],
                        ],
                    ],
                    // This route should be set before imageserver/media in
                    // order to be processed by module ImageServer.
                    'media-bad' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/iiif-media-bad-fake',
                        ],
                    ],
                    'info' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefixMedia:id/info.json",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefixMedia,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $versionMedia,
                                'action' => 'info',
                            ],
                        ],
                    ],
                    // There is not check of the right media: the module Iiif Server
                    // is not a media server.
                    'media' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefixMedia:id/:region/:size/:rotation/:quality:.:format",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefixMedia,
                                'id' => '[^\/]+',
                                'region' => '[^\/]+',
                                'size' => '[^\/]+',
                                'rotation' => '[^\/]+',
                                'quality' => '[^\/]+',
                                'format' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $versionMedia,
                                'action' => 'fetch',
                            ],
                        ],
                    ],

                    // Special route for the canvas placeholder in manifest v2,
                    // that is used in particular when there is no image server
                    // or when the media is private.
                    // @see \IiifServer\View\Helper\IiifManifest2::_iiifCanvasPlaceholder()
                    'placeholder' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '[/:version]/ixif-message-0/res/placeholder',
                            'constraints' => [
                                'version' => '2|3',
                            ],
                            'defaults' => [
                                'version' => $versionMedia,
                                'action' => 'placeholder',
                            ],
                        ],
                    ],
                ],
            ],

            'mediaserver' => [
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/iiif',
                    'defaults' => [
                        '__NAMESPACE__' => 'IiifServer\Controller',
                        'controller' => Controller\MediaController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    // Same as iiifserver/id and imageserver/id, but needed to create urls.
                    // A redirect to the info.json is required by the specification.
                    'id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefixMedia:id",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefixMedia,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $versionMedia,
                                'action' => 'id',
                            ],
                        ],
                    ],
                    // This route is a garbage collector that allows to return an error 400 or 501 to
                    // invalid or not implemented requests, as required by specification.
                    // This route should be set before the mediaserver/media in order to be
                    // processed after it.
                    'media-bad' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefixMedia:id:.:format",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefix,
                                'id' => '[^\/]+',
                                'format' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $version,
                                'action' => 'bad',
                            ],
                        ],
                    ],
                    // Same as imageserver/info, but needed to create urls.
                    'info' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefixMedia:id/info.json",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefixMedia,
                                'id' => '[^\/]+',
                            ],
                            'defaults' => [
                                'version' => $versionMedia,
                                'action' => 'info',
                            ],
                        ],
                    ],
                    // Warning: the format is separated with a ".", not a "/".
                    // TODO pdf is not an audio video media.
                    'media' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => "[/:version]/$prefixMedia:id:.:format",
                            'constraints' => [
                                'version' => '2|3',
                                'prefix' => $constraintPrefixMedia,
                                'id' => '[^\/]+',
                                'format' => 'pdf|mp3|ogg|mp4|webm|ogv',
                                // To support a proprietary format that is not supported by many browsers/os:
                                // Add it in src/Iiif/TraitMedia.php too.
                                // 'format' => 'pdf|mp3|ogg|mp4|webm|wmv|ogv',
                                // 'format' => '.+',
                            ],
                            'defaults' => [
                                'version' => $versionMedia,
                                'action' => 'fetch',
                            ],
                        ],
                    ],
                ],
            ],

            // For IXIF, some json files should be available to describe media for context.
            // This is not used currently: the Wellcome uris are kept because they are set
            // for main purposes in ImageServer.
            // @link https://gist.github.com/tomcrane/7f86ac08d3b009c8af7c

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
            'iiifserver_manifest_external_property' => 'dcterms:hasFormat',
            // Content of the manifest.
            'iiifserver_manifest_description_property' => 'dcterms:bibliographicCitation',
            'iiifserver_manifest_attribution_property' => '',
            'iiifserver_manifest_attribution_default' => 'Provided by Example Organization', // @translate
            'iiifserver_manifest_rights' => 'property_or_url',
            'iiifserver_manifest_rights_property' => 'dcterms:license',
            'iiifserver_manifest_rights_uri' => 'https://rightsstatements.org/vocab/CNE/1.0/',
            'iiifserver_manifest_rights_url' => '',
            'iiifserver_manifest_rights_text' => '',
            'iiifserver_manifest_homepage' => 'resource',
            'iiifserver_manifest_homepage_property' => '',
            'iiifserver_manifest_seealso_property' => '',
            'iiifserver_manifest_start_property' => '',
            'iiifserver_manifest_start_primary_media' => false,
            'iiifserver_manifest_viewing_direction_property' => '',
            'iiifserver_manifest_viewing_direction_default' => 'left-to-right',
            'iiifserver_manifest_placeholder_canvas_property' => '',
            'iiifserver_manifest_placeholder_canvas_default' => '',
            'iiifserver_manifest_behavior_property' => '',
            'iiifserver_manifest_behavior_default' => ['none'],
            'iiifserver_manifest_canvas_label' => 'template',
            'iiifserver_manifest_canvas_label_property' => '',
            'iiifserver_manifest_logo_default' => '',
            'iiifserver_manifest_html_descriptive' => true,
            'iiifserver_manifest_properties_collection_whitelist' => [],
            'iiifserver_manifest_properties_item_whitelist' => [],
            'iiifserver_manifest_properties_media_whitelist' => [],
            'iiifserver_manifest_properties_collection_blacklist' => [
                'dcterms:tableOfContents',
                'bibo:content',
                'extracttext:extracted_text',
            ],
            'iiifserver_manifest_properties_item_blacklist' => [
                'dcterms:tableOfContents',
                'bibo:content',
                'extracttext:extracted_text',
            ],
            'iiifserver_manifest_properties_media_blacklist' => [
                'dcterms:tableOfContents',
                'bibo:content',
                'extracttext:extracted_text',
            ],
            'iiifserver_manifest_structures_property' => '',
            'iiifserver_manifest_structures_skip_flat' => false,
            'iiifserver_xml_image_match' => 'order',
            'iiifserver_xml_fix_mode' => 'no',
            'iiifserver_access_resource_skip' => false,
            // Urls.
            'iiifserver_url_version_add' => false,
            'iiifserver_identifier_clean' => true,
            'iiifserver_identifier_prefix' => '',
            'iiifserver_identifier_raw' => false,
            // These options allows to bypass a proxy issue.
            'iiifserver_url_force_from' => '',
            'iiifserver_url_force_to' => '',
            // TODO Fetch the config of the image server directly via the endpoint.
            // The same keys for Iiif Image Api are used in module Image server (except url).
            // This option is used by module Bulk Import and for a future improvement.
            'iiifserver_media_api_url' => '',
            'iiifserver_media_api_default_version' => '2',
            'iiifserver_media_api_supported_versions' => [
                '2/2',
                '3/2',
            ],
            // The version and the prefix should be set in module config routing for now.
            'iiifserver_media_api_version_append' => false,
            'iiifserver_media_api_prefix' => '',
            'iiifserver_media_api_identifier' => 'media_id',
            'iiifserver_media_api_identifier_infojson' => false,
            'iiifserver_media_api_support_non_image' => false,
            // Hidden option.
            'iiifserver_media_api_default_supported_version' => [
                'service' => '2',
                'level' => '2',
            ],
        ],
    ],
];
