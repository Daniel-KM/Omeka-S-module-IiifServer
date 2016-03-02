<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'UniversalViewer\Controller\Player' => 'UniversalViewer\Controller\PlayerController',
            'UniversalViewer\Controller\Presentation' => 'UniversalViewer\Controller\PresentationController',
            'UniversalViewer\Controller\Image' => 'UniversalViewer\Controller\ImageController',
            'UniversalViewer\Controller\Media' => 'UniversalViewer\Controller\MediaController',
        ),
    ),
    'router' => array(
        'routes' => array(
            'universalviewer_player' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/:recordtype/play/:id',
                    'constraints' => array(
                        'recordtype' => 'collections|items',
                        'id' => '\d+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Player',
                        'action' => 'play',
                    ),
                ),
            ),
            'universalviewer_presentation' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/:recordtype/presentation/:id',
                    'constraints' => array(
                        'recordtype' => 'itemsets|items',
                        'id' => '\d+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Presentation',
                        'action' => 'manifest',
                    ),
                ),
            ),
            'universalviewer_presentation_manifest' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/:recordtype/presentation/:id/manifest',
                    'constraints' => array(
                        'recordtype' => 'itemsets|items',
                        'id' => '\d+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Presentation',
                        'action' => 'manifest',
                    ),
                ),
            ),
            'universalviewer_image' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/image/:id',
                    'constraints' => array(
                        'id' => '\d+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Image',
                        'action' => 'index',
                    ),
                ),
            ),
            'universalviewer_image_info' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/image/:id/info.json',
                    'constraints' => array(
                        'id' => '\d+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Image',
                        'action' => 'info',
                    ),
                ),
            ),
            'universalviewer_image_bad' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/image/:id/:region/:size/:rotation/:quality:.:format',
                    'constraints' => array(
                        'id' => '\d+',
                        'region' => '.+',
                        'size' => '.+',
                        'rotation' => '.+',
                        'quality' => '.+',
                        'format' => '.+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Image',
                        'action' => 'bad',
                    ),
                ),
            ),
            'universalviewer_image_url' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/image/:id/:region/:size/:rotation/:quality:.:format',
                    'constraints' => array(
                        'id' => '\d+',
                        'region' => 'full|\d+,\d+,\d+,\d+|pct:\d+\.?\d*,\d+\.?\d*,\d+\.?\d*,\d+\.?\d*',
                        'size' => 'full|\d+,\d*|\d*,\d+|pct:\d+\.?\d*|!\d+,\d+',
                        'rotation' => '0|90|180|270',
                        'quality' => 'default|color|gray|bitonal',
                        'format' => 'jpg|png|gif',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Image',
                        'action' => 'fetch',
                    ),
                ),
            ),
            'universalviewer_media' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/media/:id',
                    'constraints' => array(
                        'id' => '\d+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Media',
                        'action' => 'index',
                    ),
                ),
            ),
            'universalviewer_media_info' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/media/:id/info.json',
                    'constraints' => array(
                        'id' => '\d+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Media',
                        'action' => 'info',
                    ),
                ),
            ),
            'universalviewer_media_bad' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/media/:id.:format',
                    'constraints' => array(
                        'id' => '\d+',
                        'format' => '.+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Media',
                        'action' => 'bad',
                    ),
                ),
            ),
            'universalviewer_media_url' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/media/:id.:format',
                    'constraints' => array(
                        'id' => '\d+',
                        'format' => 'pdf|mp3|ogg|mp4|webm|ogv',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'UniversalViewer\Controller',
                        'controller' => 'Media',
                        'action' => 'fetch',
                    ),
                ),
            ),
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            OMEKA_PATH . '/modules/UniversalViewer/view',
        ),
    ),
    'view_helpers' => array(
        'invokables' => array(
            'universalViewer' => 'UniversalViewer\View\Helper\UniversalViewer',
            'iiifManifest' => 'UniversalViewer\View\Helper\IiifManifest',
            'iiifInfo' => 'UniversalViewer\View\Helper\IiifInfo',
        ),
    ),
);
