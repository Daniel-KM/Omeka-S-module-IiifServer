<?php declare(strict_types=1);

/*
 * Copyright 2015-2023 Daniel Berthereau
 * Copyright 2016-2017 BibLibre
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\AssetRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFileFactory;

/**
 * The casting to (object) that was used because the json encoding converts array into object or array, was removed.
 */
class IiifManifest2 extends AbstractHelper
{
    use \IiifServer\Iiif\TraitRights;

    /**
     * @var int
     */
    protected $defaultHeight = 400;

    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * Full path to the files.
     *
     * @var string
     */
    protected $basePath;

    /**
     * @var \Omeka\View\Helper\Setting
     */
    protected $setting;

    /**
     * @var string
     */
    protected $_baseUrl;

    /**
     * @var string
     */
    protected $_baseUrlImageServer;

    public function __construct(TempFileFactory $tempFileFactory, $basePath)
    {
        $this->tempFileFactory = $tempFileFactory;
        $this->basePath = $basePath;
    }

    /**
     * Get the IIIF manifest for the specified resource (API Presentation 2.1).
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return Object|null
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $resourceName = $resource->resourceName();
        if ($resourceName == 'items') {
            return $this->buildManifestItem($resource);
        }

        if ($resourceName == 'item_sets') {
            return $this->view->iiifCollection2($resource);
        }
    }

    /**
     * Get the IIIF manifest for the specified item.
     *
     * @todo Use a representation/context with a getResource(), a toString()
     * that removes empty values, a standard json() without ld and attach it to
     * event in order to modify it if needed.
     * @todo Replace all data by standard classes.
     * @todo Replace web root by routes, even if main ones are only urn.
     *
     * @param ItemRepresentation $item
     * @return Object|null. The object corresponding to the manifest.
     */
    protected function buildManifestItem(ItemRepresentation $item)
    {
        // Prepare values needed for the manifest. Empty values will be removed.
        // Some are required.
        $manifest = [
            '@context' => '',
            '@id' => '',
            '@type' => 'sc:Manifest',
            'label' => '',
            'description' => '',
            'thumbnail' => '',
            'license' => '',
            'attribution' => '',
            // A logo to add at the end of the information panel.
            'logo' => '',
            'service' => [],
            // For example the web page of the item.
            'related' => '',
            // Other formats of the same data.
            'seeAlso' => '',
            'within' => '',
            'metadata' => [],
            'mediaSequences' => [],
            'sequences' => [],
        ];

        $manifest['@id'] = $this->view->iiifUrl($item, 'iiifserver/manifest', '2');

        // Required for TraitRights.
        $helpers = $this->view->getHelperPluginManager();
        $this->setting = $helpers->get('setting');

        // The base url for some other ids to quick process.
        $this->_baseUrl = $this->view->iiifUrl($item, 'iiifserver/uri', '2', [
            'type' => 'annotation-page',
            'name' => '',
        ]);
        $this->_baseUrl = mb_substr($this->_baseUrl, 0, (int) mb_strpos($this->_baseUrl, '/annotation-page'));
        $this->_baseUrlImageServer = rtrim($this->view->setting('iiifserver_media_api_url'), '/ ') ?: $this->_baseUrl;

        $metadata = $this->iiifMetadata($item);
        $manifest['metadata'] = $metadata;

        // Don't use html in a title!
        $label = $this->view->escapeHtml($item->displayTitle('') ?: $manifest['@id']);
        $label = html_entity_decode($label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $manifest['label'] = $label;

        $descriptionProperty = $this->view->setting('iiifserver_manifest_description_property');
        if ($descriptionProperty) {
            $description = strip_tags((string) $item->value($descriptionProperty, ['default' => '']));
        } else {
            $description = '';
        }
        $manifest['description'] = $description;

        // A license url is not a requirement in v2.1, but a recommandation.
        $license = $this->rightsResource($item);
        $isLicenseUrl = $license
            && (substr($license, 0, 8) === 'https://' || substr($license, 0, 7) === 'http://');

        $attributionProperty = $this->view->setting('iiifserver_manifest_attribution_property');
        if ($attributionProperty) {
            $attribution = strip_tags((string) $item->value($attributionProperty, ['default' => '']));
        }
        if (empty($attribution)) {
            $attribution = $isLicenseUrl || empty($license)
                ? $this->view->setting('iiifserver_manifest_attribution_default')
                : $license;
        }
        $manifest['attribution'] = $attribution;

        if ($license && $attribution !== $license) {
            $manifest['license'] = $license;
        }

        $logo = $this->view->setting('iiifserver_manifest_logo_default');
        if ($logo) {
            $manifest['logo'] = ['@id' => $logo];
        }

        /*
        // Omeka api is a service, but not referenced in https://iiif.io/api/annex/services.
        $manifest['service'] = [
            '@context' => $this->view->url('api-context', [], ['force_canonical' => true]),
            '@id' => $item->apiUrl(),
            'format' =>'application/ld+json',
            // TODO What is the profile of Omeka json-ld?
            // 'profile' => '',
        ];
        $manifest['service'] = [
            '@context' =>'http://example.org/ns/jsonld/context.json',
            '@id' => 'http://example.org/service/example',
            'profile' => 'http://example.org/docs/example-service.html',
        ];
        */

        $manifest['related'] = [
            '@id' => $this->view->publicResourceUrl($item, true),
            'format' => 'text/html',
        ];

        $manifest['seeAlso'] = [
            '@id' => $item->apiUrl(),
            'format' => 'application/ld+json',
            // TODO What is the profile of Omeka json-ld?
            // 'profile' => '',
        ];

        $withins = [];
        foreach ($item->itemSets() as $itemSet) {
            $withins[] = $this->view->iiifUrl($itemSet, 'iiifserver/collection', '2');
        }
        if (count($withins) === 1) {
            $metadata['within'] = reset($withins);
        } elseif (count($withins)) {
            $metadata['within'] = $withins;
        }

        $canvases = [];

        // Get all images and non-images and detect 3D models.
        $medias = $item->media();
        $images = [];
        $nonImages = [];
        $mediaMain3d = null;
        $mediaType3dModel = null;
        foreach ($medias as $media) {
            $mediaType = $media->mediaType();
            // Images files.
            // Internal: has_derivative is not only for images.
            if ($mediaType && substr($mediaType, 0, 6) === 'image/') {
                $images[] = $media;
            }
            // Handle external IIIF images.
            elseif ($media->ingester() === 'iiif') {
                $images[] = $media;
            }
            // Non-images files.
            else {
                // Three js manages many 3d formats with xml and json, but they
                // may not have a media-type or the media-type may not be
                // recognized during creation of the media. So only json type is
                // managed here.
                // TODO Make support of 3D models more generic.
                $nonImages[] = $media;
                if ($mediaType === 'application/json') {
                    // TODO Check if this is really a 3D model for three.js (see https://threejs.org) (store it as media type during import or a job).
                    // if ($this->isThreeJs($media)) {
                    $mediaType3dModel = 'model/vnd.threejs+json';
                    $mediaMain3d = $media;
                } elseif ($mediaType === 'model/gltf+json') {
                    $mediaType3dModel = 'model/gltf+json';
                    $mediaMain3d = $media;
                } elseif ($mediaType === 'model/gltf-binary') {
                    // A gltf-binary file is not a gltf bin file attached to a
                    // gltf+json.
                    $mediaType3dModel = 'model/gltf-binary';
                    $mediaMain3d = $media;
                }
                // Check if this is a json file for old Omeka or old imports.
                else {
                    $extension = strtolower(pathinfo((string) $media->source(), PATHINFO_EXTENSION));
                    // TODO Convert old "text/plain" into "application/json" or "model/gltf+json".
                    if ($mediaType === 'text/plain') {
                        if ($extension === 'json') {
                            $mediaType3dModel = 'model/vnd.threejs+json';
                            $mediaMain3d = $media;
                        } elseif ($extension === 'gltf') {
                            $mediaType3dModel = 'model/gltf+json';
                            $mediaMain3d = $media;
                        }
                    }
                    // elseif ($mediaType === 'application/octet-stream') {
                    //     if (pathinfo((string) $media->source(), PATHINFO_EXTENSION) == 'bin') {
                    //         $gltfFiles[] = $media;
                    //     }
                    // }
                }
            }
        }
        unset($medias);
        $totalImages = count($images);

        $is3dModel = isset($mediaMain3d);

        // Thumbnail of the whole work.
        $manifest['thumbnail'] = $this->_mainThumbnail($item, $is3dModel);

        // Process images, except if they belong to a 3D model.
        if (!$is3dModel) {
            $imageNumber = 0;
            foreach ($images as $media) {
                $canvas = $this->_iiifCanvasImage($media, ++$imageNumber);

                // TODO Add other content.
                /*
                $otherContent = [];
                $canvas['otherContent'] = $otherContent;
                */

                $canvases[] = $canvas;
            }
        }

        // Process non images.
        $rendering = [];
        $mediaSequences = [];
        $mediaSequencesElements = [];

        $translate = $this->view->plugin('translate');

        // TODO Manage the case where there is a video, a pdf etc, and the image
        // is only a quick view. So a main file should be set, that is not the
        // representative file.

        // When there are images or one json file, other files may be added to
        // download section, except if they belong to a 3D model, where nothing
        // is downloadable.
        if ($totalImages && !$is3dModel) {
            foreach ($nonImages as $media) {
                $mediaType = $media->mediaType();
                switch ($mediaType) {
                    case '':
                        break;

                    case 'application/pdf':
                        $render = [];
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $mediaType;
                        $render['label'] = $translate('Download as PDF'); // @translate
                        $rendering[] = $render;
                        break;

                    case 'text/xml':
                        $render = [];
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $mediaType;
                        $render['label'] = $translate('Download as XML'); // @translate
                        $rendering[] = $render;
                        break;
                }
                // TODO Add alto files and search.
                // TODO Add other content.
            }
        }

        // The media is a 3d model.
        elseif ($is3dModel) {
            // Prepare the media sequence for threejs.
            $mediaSequenceElement = $this->_iiifMediaSequenceModel(
                $mediaMain3d,
                [
                    'label' => $label,
                    'metadata' => $metadata,
                    // With Universal Viewer, the type should be "dctypes:PhysicalObject"
                    // or "PhysicalObject", not "Object" neither "Model" (used in v3).
                    'type' => 'PhysicalObject',
                    'format' => $mediaType3dModel,
                    'thumbnail' => $manifest['thumbnail'],
                ]
            );
            $mediaSequencesElements[] = $mediaSequenceElement;
        }

        // Else, check if non-images are managed (special content, as pdf).
        else {
            foreach ($nonImages as $media) {
                $mediaType = $media->mediaType();
                switch ($mediaType) {
                    case '':
                        break;

                    case 'application/pdf':
                        $mediaSequenceElement = $this->_iiifMediaSequencePdf(
                            $media,
                            ['label' => $label, 'metadata' => $metadata]
                        );
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // TODO Add the file for download (no rendering)? The
                        // file is already available for download in the pdf viewer.
                        break;

                    case substr($mediaType, 0, 6) === 'audio/':
                    // case 'audio/ogg':
                    // case 'audio/mp3':
                        $mediaSequenceElement = $this->_iiifMediaSequenceAudio(
                            $media,
                            ['label' => $label, 'metadata' => $metadata]
                        );
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // Rendering files are automatically added for download.
                        break;

                    // TODO Check/support the media type "application/octet-stream".
                    // case 'application/octet-stream':
                    case substr($mediaType, 0, 6) === 'video/':
                    // case 'video/webm':
                        $mediaSequenceElement = $this->_iiifMediaSequenceVideo(
                            $media,
                            ['label' => $label, 'metadata' => $metadata]
                        );
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // Rendering files are automatically added for download.
                        break;

                    // TODO Add other content.
                    default:
                }

                // TODO Add other files as resources of the current element.
            }
        }

        // Prepare sequences.
        $sequences = [];

        // Manage the exception: the media sequence with threejs 3D model.
        if ($is3dModel && $mediaSequencesElements) {
            $mediaSequence = [];
            $mediaSequence['@id'] = $this->_baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequences[] = $mediaSequence;
        }
        // When there are images.
        elseif ($totalImages) {
            $sequence = [];
            $sequence['@id'] = $this->_baseUrl . '/sequence/normal';
            $sequence['@type'] = 'sc:Sequence';
            $sequence['label'] = 'Current Page Order';

            $viewingDirectionProperty = $this->view->setting('iiifserver_manifest_viewing_direction_property');
            if ($viewingDirectionProperty) {
                $viewingDirection = strip_tags((string) $item->value($viewingDirectionProperty));
            }
            if (empty($viewingDirection)) {
                $viewingDirection = $this->view->setting('iiifserver_manifest_viewing_direction_default');
            }
            if (in_array($viewingDirection, ['left-to-right', 'right-to-left', 'top-to-bottom', 'bottom-to-top'])) {
                $sequence['viewingDirection'] = $viewingDirection;
            }

            $viewingHintProperty = $this->view->setting('iiifserver_manifest_behavior_property');
            if ($viewingHintProperty) {
                $viewingHint = strip_tags((string) $item->value($viewingHintProperty));
            }
            if (empty($viewingHint)) {
                $viewingHint = $this->view->setting('iiifserver_manifest_behavior_default', []);
                $viewingHint = in_array('none', $viewingHint) ? 'none' : reset($viewingHint);
            }
            if ($viewingHint !== 'none') {
                $sequence['viewingHint'] = $totalImages > 1 ? $viewingHint : 'non-paged';
            }

            if ($rendering) {
                $sequence['rendering'] = $rendering;
            }
            $sequence['canvases'] = $canvases;
            $sequences[] = $sequence;
        }

        // Sequences when there is no image (special content).
        elseif ($mediaSequencesElements) {
            $mediaSequence = [];
            $mediaSequence['@id'] = $this->_baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequences[] = $mediaSequence;

            // Add a sequence in case of the media cannot be read.
            $sequence = $this->_iiifSequenceUnsupported($rendering);
            $sequences[] = $sequence;
        }

        // No supported content.
        else {
            // Set a default render if needed.
            /*
            if (empty($rendering)) {
                $placeholder = 'img/placeholder-default.jpg';
                $render = [];
                $render['@id'] = $this->view->assetUrl($placeholder, 'IiifServer');
                $render['format'] = 'image/jpeg';
                $render['label'] = $translate('Unsupported content.'); // @translate
                $rendering[] = $render;
            }
            */

            $sequence = $this->_iiifSequenceUnsupported($rendering);
            $sequences[] = $sequence;
        }

        if ($mediaSequences) {
            $manifest['mediaSequences'] = $mediaSequences;
        }

        if ($sequences) {
            $manifest['sequences'] = $sequences;
        }

        $structureProperty = $this->view->setting('iiifserver_manifest_structures_property');
        if ($structureProperty) {
            $literalStructure = (string) $item->value($structureProperty, ['default' => '']);
            if ($literalStructure) {
                $structure = @json_decode($literalStructure, true);
                if ($structure && is_array($structure)) {
                    $firstRange = reset($structure);
                    if (is_array($firstRange)) {
                        $structure = isset($firstRange['type'])
                            ? $this->convertToStructure2($structure)
                            : $this->checkStructure($structure);
                    } else {
                        $structure = [];
                    }
                } else {
                    $structure = $this->extractStructure($literalStructure, $canvases);
                }
                if (count($structure) > 0) {
                    $manifest['structures'] = $structure;
                }
            }
        }

        if ($is3dModel) {
            $manifest['@context'] = [
                'http://iiif.io/api/presentation/2/context.json',
                'http://files.universalviewer.io/ld/ixif/0/context.json',
            ];
        }
        // For images, the normalized context.
        elseif ($totalImages) {
            $manifest['@context'] = 'http://iiif.io/api/presentation/2/context.json';
        }
        // For other non standard iiif files.
        else {
            $manifest['@context'] = [
                'http://iiif.io/api/presentation/2/context.json',
                // See MediaController::contextAction()
                'http://wellcomelibrary.org/ld/ixif/0/context.json',
                // WEB_ROOT . '/ld/ixif/0/context.json',
            ];
        }

        // Give possibility to customize the manifest.
        // TODO Manifest should be a true object, with many sub-objects.
        $resource = $item;
        $type = 'item';
        $params = compact('manifest', 'resource', 'type');
        $params = $this->view->plugin('trigger')->__invoke('iiifserver.manifest', $params, true);
        $manifest = $params['manifest'];

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        return $manifest;
    }

    /**
     * Prepare the metadata of a resource.
     *
     * @todo Factorize IiifCanvas2, IiifCollection2, TraitDescriptive and IiifManifest2.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function iiifMetadata(AbstractResourceEntityRepresentation $resource)
    {
        $jsonLdType = $resource->getResourceJsonLdType();
        $map = [
            'o:ItemSet' => [
                'whitelist' => 'iiifserver_manifest_properties_collection_whitelist',
                'blacklist' => 'iiifserver_manifest_properties_collection_blacklist',
            ],
            'o:Item' => [
                'whitelist' => 'iiifserver_manifest_properties_item_whitelist',
                'blacklist' => 'iiifserver_manifest_properties_item_blacklist',
            ],
            'o:Media' => [
                'whitelist' => 'iiifserver_manifest_properties_media_whitelist',
                'blacklist' => 'iiifserver_manifest_properties_media_blacklist',
            ],
        ];
        if (!isset($map[$jsonLdType])) {
            return [];
        }

        $settingHelper = $this->view->getHelperPluginManager()->get('setting');

        $whitelist = $settingHelper($map[$jsonLdType]['whitelist'], []);
        if ($whitelist === ['none']) {
            return [];
        }

        $values = $whitelist
            ? array_intersect_key($resource->values(), array_flip($whitelist))
            : $resource->values();

        $blacklist = $settingHelper($map[$jsonLdType]['blacklist'], []);

        if ($this->view->setting('iiifserver_manifest_structures_property')) {
            $blacklist[] = $this->view->setting('iiifserver_manifest_structures_property');
        }

        if ($blacklist) {
            $values = array_diff_key($values, array_flip($blacklist));
        }

        if (empty($values)) {
            return [];
        }

        // TODO Remove automatically special properties, and only for values that are used (check complex conditions…).

        return $this->view->setting('iiifserver_manifest_html_descriptive')
            ? $this->valuesAsHtml($values)
            : $this->valuesAsPlainText($values);
    }

    /**
     * List values as plain text descriptive metadata.
     *
     * @param \Omeka\Api\Representation\ValueRepresentation[] $values
     * @return array
     */
    protected function valuesAsPlainText(array $values)
    {
        $metadata = [];
        $publicResourceUrl = $this->view->plugin('publicResourceUrl');
        foreach ($values as $propertyData) {
            $valueMetadata = [];
            $valueMetadata['label'] = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $valueValues = array_filter(array_map(function ($v) use ($publicResourceUrl) {
                $vr = null;
                return strpos($v->type(), 'resource') === 0 && $vr = $v->valueResource()
                    ? $publicResourceUrl($vr, true)
                    : (string) $v;
            }, $propertyData['values']), 'strlen');
            $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
            $metadata[] = $valueMetadata;
        }
        return $metadata;
    }

    /**
     * List values as descriptive metadata, with links for resources and uris.
     *
     * @param \Omeka\Api\Representation\ValueRepresentation[] $values
     * @return array
     */
    protected function valuesAsHtml(array $values)
    {
        $metadata = [];
        $publicResourceUrl = $this->view->plugin('publicResourceUrl');
        foreach ($values as $propertyData) {
            $valueMetadata = [];
            $valueMetadata['label'] = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $valueValues = array_filter(array_map(function ($v) use ($publicResourceUrl) {
                if (strpos($v->type(), 'resource') === 0 && $vr = $v->valueResource()) {
                    return '<a class="resource-link" href="' . $publicResourceUrl($vr, true) . '">'
                        . '<span class="resource-name">' . $vr->displayTitle() . '</span>'
                        . '</a>';
                }
                return $v->asHtml();
            }, $propertyData['values']), 'strlen');
            $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
            $metadata[] = $valueMetadata;
        }
        return $metadata;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka file.
     *
     * @param MediaRepresentation $media
     * @return \stdClass|null
     */
    protected function _iiifThumbnail(MediaRepresentation $media)
    {
        /** @var \Omeka\Api\Representation\AssetRepresentation $thumbnailAsset */
        $thumbnailAsset = $media->thumbnail();
        if ($thumbnailAsset) {
            return $this->_iiifThumbnailAsset($thumbnailAsset);
        }

        if ($media->hasThumbnails()) {
            $imageUrl = $media->thumbnailUrl('medium');
            $size = $this->view->imageSize($media, 'medium');
            if ($size) {
                $thumbnail = [
                    '@id' => $imageUrl,
                    '@type' => 'dctypes:Image',
                    'format' => 'image/jpeg',
                    'width' => $size['width'],
                    'height' => $size['height'],
                ];
                return $thumbnail;
            }
        }

        // Manage external IIIF image.
        if ($media->ingester() === 'iiif') {
            // The method "mediaData" contains data from the info.json file.
            $mediaData = $media->mediaData();
            // In 3.0, the "@id" property becomes "id".
            $imageBaseUri = $mediaData['@id'] ?? $mediaData['id'];
            // In Image API 3.0, @context can be a list, https://iiif.io/api/image/3.0/#52-technical-properties.
            $imageApiContextUri = is_array($mediaData['@context']) ? array_pop($mediaData['@context']) : $mediaData['@context'];
            $imageComplianceLevelUri = is_array($mediaData['profile']) ? $mediaData['profile'][0] : $mediaData['profile'];
            $imageComplianceLevel = $this->_iiifComplianceLevel($mediaData['profile']);
            $imageUrl = $this->_iiifThumbnailUrl($imageBaseUri, $imageApiContextUri, $imageComplianceLevel);
            $service = $this->_iiifImageService($imageBaseUri, $imageApiContextUri, $imageComplianceLevelUri);
            $thumbnail = [
                'id' => $imageUrl,
                '@type' => 'dctypes:Image',
                'format' => 'image/jpeg',
                'width' => $mediaData['width'] * $mediaData['height'] / $this->defaultHeight,
                'height' => $this->defaultHeight,
                'service' => $service,
            ];
            return $thumbnail;
        }

        return null;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka asset file.
     *
     * @param AssetRepresentation $asset
     * @return \stdClass|null
     */
    protected function _iiifThumbnailAsset(AssetRepresentation $asset)
    {
        $size = $this->view->imageSize($asset);
        if ($size) {
            $thumbnail = [
                '@id' => $asset->assetUrl(),
                '@type' => 'dctypes:Image',
                'format' => $asset->mediaType(),
                'width' => $size['width'],
                'height' => $size['height'],
            ];
            return $thumbnail;
        }
    }

    /**
     * Create an IIIF image object from an Omeka file.
     *
     * @param MediaRepresentation $media
     * @param int $index Used to set the standard name of the image.
     * @param string $canvasUrl Used to set the value for "on".
     * @param int $width If not set, will be calculated.
     * @param int $height If not set, will be calculated.
     * @return \stdClass|null
     */
    protected function _iiifImage(MediaRepresentation $media, $index, $canvasUrl, $width = null, $height = null)
    {
        $view = $this->getView();
        if (empty($width) || empty($height)) {
            $imageSize = $view->imageSize($media, 'original');
            [$width, $height] = $imageSize ? array_values($imageSize) : [null, null];
        }

        $image = [];
        $image['@id'] = $this->_baseUrl . '/annotation/p' . sprintf('%04d', $index) . '-image';
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed currently).
        $imageResource = [];

        // If it is an external IIIF image.
        // Convert info.json saved in media into a presentation sequence part.
        if ($media->ingester() == 'iiif') {
            // The method "mediaData" contains data from the info.json file.
            $mediaData = $media->mediaData();
            $imageBaseUri = $mediaData['@id'] ?? $mediaData['id'];
            // In Image API 3.0, @context can be a list, https://iiif.io/api/image/3.0/#52-technical-properties.
            $imageApiContextUri = is_array($mediaData['@context']) ? array_pop($mediaData['@context']) : $mediaData['@context'];
            // In Image API 3.0, the "@id" property becomes "id".
            $imageComplianceLevelUri = is_array($mediaData['profile']) ? $mediaData['profile'][0] : $mediaData['profile'];

            $imageResource['@id'] = $this->_iiifImageFullUrl($imageBaseUri, $imageApiContextUri);
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = 'image/jpeg';
            $imageResource['width'] = $mediaData['width'];
            $imageResource['height'] = $mediaData['height'];

            $imageResourceService = $this->_iiifImageService($imageBaseUri, $imageApiContextUri, $imageComplianceLevelUri);
            $imageResource['service'] = $imageResourceService;

            $image['resource'] = $imageResource;
            $image['on'] = $canvasUrl;
            return $image;
        }

        // In api v2, only one service can be set.
        $supportedVersion = $this->view->setting('iiifserver_media_api_default_supported_version', ['service' => '0', 'level' => '0']);
        $service = $supportedVersion['service'];
        $level = $supportedVersion['level'];

        // According to https://iiif.io/api/presentation/2.1/#image-resources,
        // "the URL may be the complete URL to a particular size of the image
        // content", so the large one here, and it's always a jpeg.
        // It's not needed to use the full original size.
        $imageSize = $view->imageSize($media, 'large');
        [$widthLarge, $heightLarge] = $imageSize ? array_values($imageSize) : [null, null];
        $imageUrl = $this->view->iiifMediaUrl($media, 'imageserver/media', $service ?: '2', [
            'region' => 'full',
            'size' => $imageSize
                ? ($widthLarge . ',' . $heightLarge)
                : ($service === '3' ? 'max' : 'full'),
            'rotation' => 0,
            'quality' => 'default',
            'format' => 'jpg',
        ]);

        $imageResource['@id'] = $service ? $imageUrl : $media->originalUrl();
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['format'] = 'image/jpeg';
        $imageResource['width'] = $width;
        $imageResource['height'] = $height;

        if ($service) {
            $imageUrlService = $this->view->iiifMediaUrl($media, 'imageserver/id', $service);
            $contextUri = $this->_iiifImageServiceUri($service);
            $profileUri = $this->_iiifImageProfileUri($contextUri, $level);
            $imageResourceService = $this->_iiifImageService($imageUrlService, $contextUri, $profileUri);
            $iiifTileInfo = $view->iiifTileInfo($media);
            if ($iiifTileInfo) {
                $imageResourceService['tiles'] = [$iiifTileInfo];
                $imageResourceService['width'] = $width;
                $imageResourceService['height'] = $height;
            }
            $imageResource['service'] = $imageResourceService;
        }

        $image['resource'] = $imageResource;
        $image['on'] = $canvasUrl;
        return $image;
    }

    /**
     * Create an IIIF canvas object for an image.
     *
     * @param MediaRepresentation $media
     * @param int|string $index Used to set the standard name of the image.
     * @return \stdClass|null
     */
    protected function _iiifCanvasImage(MediaRepresentation $media, $index)
    {
        $canvas = [];

        $prefixIndex = (string) (int) $index === (string) $index ? 'p' : '';
        $canvasUrl = $this->_baseUrl . '/canvas/' . $prefixIndex . $index;

        $canvas['@id'] = $canvasUrl;
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $this->_iiifCanvasImageLabel($media, $index);

        // Thumbnail of the current file.
        $canvas['thumbnail'] = $this->_iiifThumbnail($media);

        // If it is an external IIIF image.
        if ($media->ingester() == 'iiif') {
            $mediaData = $media->mediaData();
            $width = $canvas['width'] = $mediaData['width'];
            $height = $canvas['height'] = $mediaData['height'];
        } else {
            // Size of canvas should be the double of small images (< 1200 px),
            // but only when more than one image is used by a canvas.
            $imageSize = $this->view->imageSize($media, 'original');
            [$width, $height] = $imageSize ? array_values($imageSize) : [null, null];
            $canvas['width'] = $width;
            $canvas['height'] = $height;
            $seeAlso = $this->seeAlso($media);
            if ($seeAlso) {
                $canvas['seeAlso'] = $seeAlso;
                $canvas['otherContent'] = $this->otherContent($media);
            }
        }

        $image = $this->_iiifImage($media, $index, $canvasUrl, $width, $height);

        $images = [];
        $images[] = $image;
        $canvas['images'] = $images;

        $metadata = $this->iiifMetadata($media);
        if ($metadata) {
            $canvas['metadata'] = $metadata;
        }

        return $canvas;
    }

    /**
     * Get the label of an image for canvas.
     *
     * @param MediaRepresentation $media
     * @param int $index
     * @return string
     */
    protected function _iiifCanvasImageLabel(MediaRepresentation $media, $index)
    {
        $labelOption = $this->view->setting('iiifserver_manifest_canvas_label');
        $fallback = (string) $index;
        switch ($labelOption) {
            case 'property':
                $labelProperty = $this->view->setting('iiifserver_manifest_canvas_label_property');
                return (string) $media->value($labelProperty, ['default' => $fallback]);

            case 'property_or_source':
                $labelProperty = $this->view->setting('iiifserver_manifest_canvas_label_property');
                $label = (string) $media->value($labelProperty, ['default' => '']);
                if (strlen($label)) {
                    return $label;
                }
                // no break;
            case 'source':
                return (string) $media->displayTitle($fallback);

            case 'template_or_source':
                $fallback = (string) $media->displayTitle($fallback);
                // no break;
            case 'template':
                $template = $media->resourceTemplate();
                $label = false;
                if ($template && $template->titleProperty()) {
                    $labelProperty = $template->titleProperty()->term();
                    $label = $media->value($labelProperty, ['default' => false]);
                }
                if (!$label) {
                    $label = $media->value('dcterms:title', ['default' => $fallback]);
                }
                return (string) $label;

            case 'position':
            default:
                return $fallback;
        }
    }

    /**
     * Create an IIIF canvas object for a place holder.
     *
     * @return \stdClass
     */
    protected function _iiifCanvasPlaceholder()
    {
        $translate = $this->view->plugin('translate');
        $serverUrl = $this->view->serverUrl('');

        $canvas = [];
        $canvas['@id'] = $serverUrl . $this->view->basePath('/iiif/ixif-message/canvas/c1');
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $translate('Placeholder image'); // @translate

        $placeholder = 'img/thumbnails/placeholder-image.png';
        $canvas['thumbnail'] = $serverUrl . $this->view->assetUrl($placeholder, 'ImageServer');

        $imageSize = $this->getWidthAndHeight(OMEKA_PATH . '/modules/ImageServer/asset/' . $placeholder) ?: ['width' => null, 'height' => null];
        $canvas['width'] = $imageSize['width'];
        $canvas['height'] = $imageSize['height'];

        $image = [];
        $image['@id'] = $serverUrl . $this->view->basePath('/iiif/ixif-message/imageanno/placeholder');
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed).
        $imageResource = [];
        $imageResource['@id'] = $serverUrl . $this->view->basePath('/iiif/ixif-message-0/res/placeholder');
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['width'] = $imageSize['width'];
        $imageResource['height'] = $imageSize['height'];
        $imageResource = $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = $serverUrl . $this->view->basePath('/iiif/ixif-message/canvas/c1');
        $images = [$image];

        $canvas['images'] = $images;

        return $canvas;
    }

    /**
     * Create an IIIF media sequence object for a pdf.
     *
     * @param MediaRepresentation $media
     * @param array $values
     * @return \stdClass|null
     */
    protected function _iiifMediaSequencePdf(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = [];
        $mediaSequenceElement['@id'] = $media->originalUrl();
        $mediaSequenceElement['@type'] = 'foaf:Document';
        $mediaSequenceElement['format'] = $media->mediaType();
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, a pdf is normally the only one
        // file.
        $mediaSequenceElement['label'] = $values['label'];
        $mediaSequenceElement['metadata'] = $values['metadata'];
        $mediaSequenceElement['thumbnail'] = $media->thumbnailUrl('medium');
        $mediaSequencesService = [];
        $mseUrl = $this->view->iiifMediaUrl($media, 'mediaserver/id', '2');
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequenceElement['service'] = $mediaSequencesService;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF media sequence object for an audio.
     *
     * @param MediaRepresentation $media
     * @param array $values
     * @return \stdClass|null
     */
    protected function _iiifMediaSequenceAudio(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = [];
        $mediaSequenceElement['@id'] = $media->originalUrl() . '/element/e0';
        $mediaSequenceElement['@type'] = 'dctypes:Sound';
        // The format is not be set here (see rendering).
        // $mediaSequenceElement['format'] = $media->mediaType();
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, such a file is normally the only
        // one file.
        $mediaSequenceElement['label'] = $values['label'];
        $mediaSequenceElement['metadata'] = $values['metadata'];
        $mediaSequenceElement['thumbnail'] = $media->thumbnailUrl('medium');

        // Specific to media files.
        $mseRenderings = [];
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm…
        $mseRendering = [];
        $mseRendering['@id'] = $media->originalUrl();
        $mseRendering['format'] = $media->mediaType();
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = [];
        $mseUrl = $this->view->iiifMediaUrl($media, 'mediaserver/id', '2');
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequenceElement['service'] = $mediaSequencesService;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF media sequence object for a video.
     *
     * @param MediaRepresentation $media
     * @param array $values
     * @return \stdClass|null
     */
    protected function _iiifMediaSequenceVideo(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = [];
        $mediaSequenceElement['@id'] = $media->originalUrl() . '/element/e0';
        $mediaSequenceElement['@type'] = 'dctypes:MovingImage';
        // The format is not be set here (see rendering).
        // $mediaSequenceElement['format'] = $media->mediaType();
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, such a file is normally the only
        // one file.
        $mediaSequenceElement['label'] = $values['label'];
        $mediaSequenceElement['metadata'] = $values['metadata'];
        $mediaSequenceElement['thumbnail'] = $media->thumbnailUrl('medium');

        // Specific to media files.
        $mseRenderings = [];
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm…
        $mseRendering = [];
        $mseRendering['@id'] = $media->originalUrl();
        $mseRendering['format'] = $media->mediaType();
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = [];
        $mseUrl = $this->view->iiifMediaUrl($media, 'mediaserver/id', '2');
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequenceElement['service'] = $mediaSequencesService;
        // TODO Get the true video width and height, even if it
        // is automatically managed.
        $mediaSequenceElement['width'] = 0;
        $mediaSequenceElement['height'] = 0;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF media sequence object for a 3D model managed by ThreeJs.
     *
     * @param MediaRepresentation $media
     * @param array $values Contains: label, metadata, format, thumbnail, type.
     * @return \stdClass|null
     */
    protected function _iiifMediaSequenceModel(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = [];
        $mediaSequenceElement['@id'] = $media->originalUrl();
        $mediaSequenceElement['@type'] = $values['type'];
        $mediaSequenceElement['format'] = $values['format'];
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used, because in Omeka, a 3D model is normally the only one file.
        $mediaSequenceElement['label'] = $values['label'];
        // Metadata are already set at record level.
        // $mediaSequenceElement['metadata'] = $values['metadata'];
        // Use the thumbnail of the item for the media too.
        $mediaSequenceElement['thumbnail'] = $values['thumbnail'];
        // No media sequence service and no sequences.
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF sequence object for an unsupported format.
     *
     * @param array $rendering
     * @return \stdClass
     */
    protected function _iiifSequenceUnsupported($rendering = [])
    {
        $sequence = [];
        $sequence['@id'] = $this->_baseUrl . '/sequence/normal';
        $sequence['@type'] = 'sc:Sequence';
        $sequence['label'] = $this->view->translate('Unsupported extension. This manifest is being used as a wrapper for non-IIIF v2 content (e.g., audio, video) and is unfortunately incompatible with IIIF v2 viewers.');
        $sequence['compatibilityHint'] = 'displayIfContentUnsupported';

        $canvas = $this->_iiifCanvasPlaceholder();

        $canvases = [];
        $canvases[] = $canvas;

        if ($rendering) {
            $sequence['rendering'] = $rendering;
        }
        $sequence['canvases'] = $canvases;

        return $sequence;
    }

    /**
     * Prepare to convert a json, literal or xml structure into a range.
     *
     * Iiif v2 supports only one structure, but managed differently. Ranges can
     * have either:
     *   - sub-ranges (with label)
     *   - canvases (without label)
     *   - members (mix of ranges and canvases, all with label).
     * To manage labels of canvases, the identifiers should be an integer (the
     * iiif position), or the label itself.
     *
     * @see https://iiif.io/api/presentation/2.1/#range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     *
     * @see \IiifServer\Iiif\Manifest::extractStructure()
     */
    protected function extractStructure(string $literalStructure, array $sequenceCanvases): array
    {
        if (mb_substr(trim($literalStructure), 0, 1) !== '<') {
            return $this->extractStructureProcess($literalStructure, $sequenceCanvases);
        }

        // Flat xml.
        // Nested xml.
        // TODO Manage nested xml toc with SimpleXml.
        // $isFlat = mb_strpos($literalStructure, '"/>') < mb_strpos($literalStructure, '<c ', 1);
        $matches = [];
        $lines = explode("\n", $literalStructure);
        foreach ($lines as &$line) {
            $line = trim($line);
            if ($line && $line !== '</c>') {
                preg_match('~\s*(<c\s*id="(?<id>[^"]*)")?\s*(label="(?<label>[^"]*)")?\s*(?:range="(?<range>[^"]*)"\s*/>)?~', $line, $matches);
                $line = $matches['id'] . ', ' . $matches['label'] . ', ' . $matches['range'];
            }
        }
        unset($line);
        return $this->extractStructureProcess(implode("\n", $lines), $sequenceCanvases);
    }

    /**
     * Convert a literal structure into a range.
     *
     * @see https://iiif.io/api/presentation/2.1/#range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     */
    protected function extractStructureProcess(string $literalStructure, array $sequenceCanvases): array
    {
        $structure = [];
        $ranges = [];
        $rangesChildren = [];
        $canvases = [];

        $rangeToArray = $this->view->plugin('rangeToArray');

        $isInteger = function ($value): bool {
            return (string) (int) $value === (string) $value;
        };

        // Convert the literal value and prepare all the ranges.

        // Split by newline code, but don't filter empty lines in order to
        // keep range indexes in complex cases.
        $lines = explode("\n", $literalStructure);
        $matches = [];
        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if (!$line || !preg_match('~^(?<name>[^,]*?)\s*,\s*(?<label>.*?)\s*,\s*(?<children>[^,]+?)$~u', $line, $matches)) {
                continue;
            }
            $name = strlen($matches['name']) === 0 ? 'r' . ($lineIndex + 1) : $matches['name'];
            $label = $matches['label'];
            $children = $rangeToArray($matches['children'], 1, null, false, false, ';');
            if (!count($children)) {
                continue;
            }

            $rangeId = $this->_baseUrl . '/range/' . ($isInteger($name) ? 'r' . $name : rawurldecode($name));

            // A line is always a range to display.
            $ranges[$name] = [
                '@id' => $rangeId,
                '@type' => 'sc:Range',
                'label' => $label,
            ];
            $rangesChildren[$name] = $children;
        }

        // If the values wasn't a formatted structure, there is no indexes.
        if (!count($ranges)) {
            return [];
        }

        // Prepare the list of canvases. This second step is needed because the
        // list of ranges should be complete to determine if an index is a
        // range or a canvas.
        foreach ($rangesChildren as $name => $itemNames) {
            foreach ($itemNames as $itemName) {
                $itemName = (string) $itemName;
                // Manage an exception: protected duplicate name for a canvas
                // and a range).
                $isProtected = mb_strlen($itemName) > 1 && mb_substr($itemName, 0, 1) === '"' && mb_substr($itemName, -1, 1) === '"';
                $cleanItemName = $isProtected
                    ? trim(mb_substr($itemName, 1, -1))
                    : $itemName;
                if ((isset($ranges[$cleanItemName]) && !$isProtected)
                    || isset($canvases[$cleanItemName])
                ) {
                    continue;
                }
                // Unlike iiif v3, the label is required when part of members,
                // so get it from the sequence.
                // It is computed one time, even if it is useless when children
                // are all canvases, but they may be used in multiple lines.
                $canvasId = null;
                $canvasLabel = null;
                $canvasIdIsInteger = $isInteger($cleanItemName);
                $canvasIdCheck = $canvasIdIsInteger ? 'p' . $cleanItemName : $cleanItemName;
                foreach ($sequenceCanvases as $sequenceCanvas) {
                    if ($canvasIdCheck === basename($sequenceCanvas['@id'])
                        || $canvasIdCheck === $sequenceCanvas['label']
                    ) {
                        $canvasId = $sequenceCanvas['@id'];
                        $canvasLabel = $sequenceCanvas['label'];
                        break;
                    }
                }
                if (!$canvasId) {
                    $canvasId = $this->_baseUrl . '/canvas/' . ($canvasIdIsInteger ? 'p' . $cleanItemName : rawurldecode($cleanItemName));
                    $canvasLabel = $canvasIdIsInteger ? '[' . $cleanItemName . ']' : $cleanItemName;
                }
                $canvases[$itemName] = [
                    '@id' => $canvasId,
                    '@type' => 'sc:Canvas',
                    'label' => $canvasLabel,
                ];
            }
        }

        // TODO Improve process to avoid recursive process (one loop and by-reference variables).

        $appendItem = function (&$range, $itemsType, $itemName, $ranges, $canvases): void {
            switch ($itemsType) {
                case 'canvases':
                    $range['canvases'][] = $canvases[$itemName]['@id'];
                    break;
                case 'ranges':
                    $range['ranges'][] = $ranges[$itemName];
                    break;
                case 'members':
                default:
                    $range['members'][] = $canvases[$itemName] ?? $ranges[$itemName];
                    break;
            }
        };

        $buildStructure = null;
        $buildStructure = function (array $itemNames, &$parentRange, array &$ascendants) use ($ranges, $canvases, $rangesChildren, $isInteger, $appendItem, &$buildStructure): void {
            // Determine the type of range items.
            $childrenAsKeys = array_flip($itemNames);
            if (!array_diff_key($childrenAsKeys, $canvases)) {
                $itemsType = 'canvases';
            } elseif (!array_diff_key($childrenAsKeys, $ranges)) {
                $itemsType = 'ranges';
            } else {
                $itemsType = 'members';
            }

            foreach ($itemNames as $itemName) {
                if (isset($canvases[$itemName])) {
                    $appendItem($parentRange, $itemsType, $itemName, $ranges, $canvases);
                    continue;
                }
                // TODO The type may be a canvas part (fragment of an image, etc.).
                // The index is a range.
                // Check if the item is in ascendants to avoid an infinite loop.
                // TODO In that case, the type of items may be wrong, and the items too…
                if (in_array($itemName, $ascendants)) {
                    $canvases[$itemName] = [
                        '@id' => $this->_baseUrl . '/canvas/' . ($isInteger($itemName) ? 'p' . $itemName : rawurlencode($itemName)),
                        '@type' => 'sc:Canvas',
                        'label' => $ranges[$itemName],
                    ];
                    $appendItem($parentRange, $itemsType, $itemName, $ranges, $canvases);
                    continue;
                }
                $ascendants[] = $itemName;
                $range = $ranges[$itemName];
                $buildStructure($rangesChildren[$itemName], $range, $ascendants);
                $ranges[$itemName] = $range;
                $appendItem($parentRange, $itemsType, $itemName, $ranges, $canvases);
                array_pop($ascendants);
            }
        };

        $allIndexes = array_fill_keys(array_merge(...array_values($rangesChildren)), true);
        $rangesToBuild = array_keys(array_diff_key($ranges, $allIndexes));
        $ascendants = [];
        $buildStructure($rangesToBuild, $structure, $ascendants);

        $structure = reset($structure) ?: [];

        return $structure;
    }

    /**
     * @todo Check a json structure for iiif v3.
     */
    protected function checkStructure(array $structure): array
    {
        return $structure;
    }

    /**
     * @todo Convert a v3 structure into a v2 structure.
     */
    protected function convertToStructure2(array $structure): array
    {
        return [];
    }

    /**
     * Get the representative thumbnail of the whole work.
     *
     * @todo Use the asset of the medias.
     * Note: for 3d models, it is not the normal way to store the image as asset
     * of a media, but as asset of the item, or as screenshot. The display of a
     * json or a binary is not an image.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param bool $is3dModel Manage an exception.
     * @return object The iiif thumbnail.
     */
    protected function _mainThumbnail(AbstractResourceEntityRepresentation $resource, $is3dModel)
    {
        $thumbnailAsset = $resource->thumbnail();
        if ($thumbnailAsset) {
            return $this->_iiifThumbnailAsset($thumbnailAsset);
        }

        // The primary media is not used, because it may not be an image.
        // The connection is used because the api does not allow to search
        // on field "has_thumbnails".
        /** @var \Doctrine\DBAL\Connection $conn */
        $conn = $resource->getServiceLocator()->get('Omeka\Connection');
        $qb = $conn->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('id')
            ->from('media', 'media')
            ->where($expr->eq('item_id', ':item_id'))
            ->andWhere('has_thumbnails = 1')
            ->orderBy('id', 'ASC')
            ->setMaxResults(1);

        $bind = [
            'item_id' => (int) $resource->id(),
        ];
        $types = [
            'item_id' => \Doctrine\DBAL\ParameterType::INTEGER,
        ];

        if ($is3dModel) {
            // The thumbnail is always a real image, so it is possible to
            // filter the request.
            $qb
                ->andWhere($expr->in('media_type', ':media_types'))
                ->andWhere($expr->in('source', ':thumbnails'));
            // IIIF format doesn't not support other images.
            // Don't set pdf and jp2, because default Omeka thumbnaillers don't
            // support them by default, and tiff is not managed by browsers.
            // @link https://iiif.io/api/image/3.0/#45-format
            $bind['media_types'] = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $types['media_types'] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            // This is the list of translations of "thumbnail" in all the omeka
            // languages, except non-ascii ones, with the browser extensions for
            // images.
            $bind['thumbnails'] = [
                'thumb.jpg', 'thumb.jpeg', 'thumb.png', 'thumb.gif', 'thumb.webp',
                'thumbnail.jpg', 'thumbnail.jpeg', 'thumbnail.png', 'thumbnail.gif', 'thumbnail.webp',
                'screnshot.jpg', 'screnshot.jpeg', 'screnshot.png', 'screnshot.gif', 'screnshot.webp',
                'esikatselukuva.jpg', 'esikatselukuva.jpeg', 'esikatselukuva.png', 'esikatselukuva.gif', 'esikatselukuva.webp',
                'imagine.jpg', 'imagine.jpeg', 'imagine.png', 'imagine.gif', 'imagine.webp',
                'miniatura.jpg', 'miniatura.jpeg', 'miniatura.png', 'miniatura.gif', 'miniatura.webp',
                'miniatuur.jpg', 'miniatuur.jpeg', 'miniatuur.png', 'miniatuur.gif', 'miniatuur.webp',
                'pisipilt.jpg', 'pisipilt.jpeg', 'pisipilt.png', 'pisipilt.gif', 'pisipilt.webp',
                'vignette.jpg', 'vignette.jpeg', 'vignette.png', 'vignette.gif', 'vignette.webp',
                'vorschaubild.jpg', 'vorschaubild.jpeg', 'vorschaubild.png', 'vorschaubild.gif', 'vorschaubild.webp',
            ];
            $types['thumbnails'] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        $id = $conn->executeQuery($qb, $bind, $types)->fetchOne();
        if ($id) {
            // Media may be private for the user, so use searchOne, not read.
            $media = $this->view->api()->searchOne('media', ['id' => $id], ['initialize' => false])->getContent();
            if ($media) {
                return $this->_iiifThumbnail($media);
            }
        }
    }

    /**
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array|null Associative array of width and height of the image
     * file, else null.
     */
    protected function getWidthAndHeight($filepath)
    {
        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempFile = $this->tempFileFactory->build();
            $tempPath = $tempFile->getTempPath();
            $tempFile->delete();
            $result = file_put_contents($tempPath, $filepath);
            if ($result !== false) {
                $result = getimagesize($tempPath);
                if ($result) {
                    [$width, $height] = $result;
                }
            }
            unlink($tempPath);
        }
        // A normal path.
        elseif (file_exists($filepath)) {
            $result = getimagesize($filepath);
            if ($result) {
                [$width, $height] = $result;
            }
        }

        if (empty($width) || empty($height)) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Helper to create a IIIF URL for the thumbnail.
     *
     * @param string $baseUri IIIF base URI of the image (URI up to the
     * identifier, w/o trailing slash)
     * @param string $contextUri Version of the API Image supported by the
     * server, as stated by the JSON-LD context URI
     * @param string $complianceLevel Compliance level to the API Image
     * supported by the server
     * @return string IIIF thumbnail URL
     */
    protected function _iiifThumbnailUrl($baseUri, $contextUri, $complianceLevel)
    {
        // NOTE: this function does not support level0 implementations (need to use `sizes` from the info.json)
        // TODO handle square thumbnails, depending on server capabilities (see 'regionSquare' feature https://iiif.io/api/image/2.1/#profile-description): e.g. $baseUri . '/square/200,200/0/default.jpg';

        if ($complianceLevel != 'level0') {
            switch ($contextUri) {
                case '1.1':
                case 'http://library.stanford.edu/iiif/image-api/1.1/context.json':
                    return $baseUri . '/full/,' . $this->defaultHeight . '/0/native.jpg';
                case '2':
                case '3':
                case 'http://iiif.io/api/image/2/context.json':
                case 'http://iiif.io/api/image/3/context.json':
                default:
                    return $baseUri . '/full/,' . $this->defaultHeight . '/0/default.jpg';
            }
        }
    }

    /**
     * Helper to set the IIIF full size url of an image, depending on the
     * version of the IIIF Image API supported by the server.
     *
     * @param string $baseUri IIIF base URI of the image (including the
     * identifier slot)
     * @param string $contextUri Version of the API Image supported by the
     * server, as stated by the JSON-LD context URI
     * @return string IIIF full size URL of the image
     */
    protected function _iiifImageFullUrl($baseUri, $contextUri)
    {
        switch ($contextUri) {
            case '1.1':
            case 'http://library.stanford.edu/iiif/image-api/1.1/context.json':
                return $baseUri . '/full/full/0/native.jpg';
            case '2':
            case 'http://iiif.io/api/image/2/context.json':
                return $baseUri . '/full/full/0/default.jpg';
            case '3':
            case 'http://iiif.io/api/image/3/context.json':
            // Max is managed by "2" too.
            default:
                return $baseUri . '/full/max/0/default.jpg';
        }
    }

    /**
     * Get the image service context uri, according to a service.
     *
     * @param string $service
     * @return string Context uri of  the service.
     */
    protected function _iiifImageServiceUri($service): string
    {
        $serviceToUris = [
            // TODO Check what is the real 0.
            // '0' => 'http://library.stanford.edu/iiif/image-api/1.0/context.json',
            '1' => 'http://library.stanford.edu/iiif/image-api/1.1/context.json',
            '2' => 'http://iiif.io/api/image/2/context.json',
            '3' => 'http://iiif.io/api/image/3/context.json',
        ];
        $first = substr($service, 0, 1);
        return is_numeric($first) && isset($serviceToUris[$first])
            ? $serviceToUris[$first]
            : $service;
    }

    /**
     * Helper to set the compliance level to the IIIF Image API, based on the
     * compliance level URI.
     *
     * @param array|string $profile Contents of the `profile` property from the
     * info.json
     * @return string Image API compliance level (returned value: level0 | level1 | level2)
     */
    protected function _iiifComplianceLevel($profile)
    {
        // In Image API 2.1, the profile property is a list, and the first entry
        // is the compliance level URI.
        // In Image API 1.1 and 3.0, the profile property is a string.
        if (is_array($profile)) {
            $profile = $profile[0];
        }

        $profileToLevels = [
            // Image API 1.0 profile.
            'http://library.stanford.edu/iiif/image-api/compliance.html' => 'level0',
            // Image API 1.1 profiles.
            'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level0' => 'level0',
            'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level1' => 'level1',
            'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level2' => 'level2',
            // Api 2.0.
            'http://iiif.io/api/image/2/level0.json' => 'level0',
            'http://iiif.io/api/image/2/level1.json' => 'level1',
            'http://iiif.io/api/image/2/level2.json' => 'level2',
            // in Image API 3.0, the profile property is a string with one of
            // these values: level0, level1, or level2 so just return the value…
            'level0' => 'level0',
            'level1' => 'level1',
            'level2' => 'level2',
        ];

        return $profileToLevels[$profile] ?? 'level0';
    }

    /**
     * Get the profile uri from the service and the compliance level.
     *
     * @param string $contextUri
     * @param string $level
     * @return string Image API profile uri.
     */
    protected function _iiifImageProfileUri($contextUri, $level)
    {
        $contextUriToLevels = [
            'http://library.stanford.edu/iiif/image-api/1.0/context.json' => [
                // No level for 1.0.
                '0' => 'http://library.stanford.edu/iiif/image-api/compliance.html',
                '1' => 'http://library.stanford.edu/iiif/image-api/compliance.html',
                '2' => 'http://library.stanford.edu/iiif/image-api/compliance.html',
            ],
            'http://library.stanford.edu/iiif/image-api/1.1/context.json' => [
                '0' => 'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level0',
                '1' => 'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level1',
                '2' => 'http://library.stanford.edu/iiif/image-api/1.1/compliance.html#level2',
            ],
            'http://iiif.io/api/image/2/context.json' => [
                '0' => 'http://iiif.io/api/image/2/level0.json',
                '1' => 'http://iiif.io/api/image/2/level1.json',
                '2' => 'http://iiif.io/api/image/2/level2.json',
            ],
            'http://iiif.io/api/image/3/context.json' => [
                '0' => 'level0',
                '1' => 'level1',
                '2' => 'level3',
            ],
        ];
        if (!is_numeric($level)) {
            $levels = ['level0' => '0', 'level1' => '1', 'level2' => '2'];
            if (!isset($levels[$level])) {
                return $level;
            }
            $level = $levels[$level];
        }
        return $contextUriToLevels[$contextUri][$level] ?? $level;
    }

    /**
     * Helper to create the IIIF Image API service block.
     *
     * @param string $baseUri IIIF base URI of the image (including the
     * identifier slot)
     * @param string $contextUri Version of the API Image supported by the
     * server, as stated by the JSON-LD context URI
     * @param string $complianceLevel Compliance level to the API Image
     * supported by the server
     * @return object $service IIIF Image API service block to be appended to
     * the Manifest
     */
    protected function _iiifImageService($baseUri, $contextUri, $complianceLevelUri)
    {
        $service = [];
        $service['@context'] = $contextUri;
        $service['@id'] = $baseUri;
        $service['profile'] = $complianceLevelUri;
        return $service;
    }

    /**
     * Added in order to use trait TraitRights.
     */
    protected function context(): ?string
    {
        return 'http://iiif.io/api/presentation/2/context.json';
    }

    /**
     * Check if a json file is a threejs one.
     */
    protected function isThreeJs(MediaRepresentation $media): bool
    {
        // Contain keys "animations", "images", "materials", "metadata", "geometries", "object", "textures".
        $json = file_get_contents($media->originalUrl());
        if ($json && $json = json_decode($json, true)) {
            return isset($json['metadata']['generator'])
            && $json['metadata']['generator'] === 'io_three';
        }
        return false;
    }

    /**
     * @todo Factorize.
     */
    protected function seeAlso(MediaRepresentation $media): ?array
    {
        $relatedMedia = $this->relatedMediaOcr($media);
        if (!$relatedMedia) {
            return null;
        }
        return [
            '@id' => $relatedMedia->originalUrl(),
            'profile' => 'http://www.loc.gov/standards/alto/v3/alto.xsd',
            'format' => 'application/alto+xml',
            'label' => 'ALTO XML',
        ];
    }

    /**
     * @todo Factorize.
     */
    protected function otherContent(MediaRepresentation $media): ?array
    {
        $relatedMedia = $this->relatedMediaOcr($media);
        if (!$relatedMedia) {
            return null;
        }
        $id = $this->view->iiifUrl($relatedMedia->item(), 'iiifserver/uri', '2', [
            'type' => 'annotation-page',
            'name' => $media->id(),
            'subtype' => 'line',
        ]);
        return [[
            '@id' => $id,
            '@type' => 'sc:AnnotationList',
            'label' => 'Text of current page',
        ]];
    }

    protected function relatedMediaOcr(MediaRepresentation $media): ?MediaRepresentation
    {
        static $relatedMedias = [];

        $mediaId = $media->id();
        if (isset($relatedMedias[$mediaId])) {
            return $relatedMedias[$mediaId];
        }

        $callingResource = $media;
        $callingResourceId = $callingResource->id();
        $callingResourceBasename = pathinfo((string) $callingResource->source(), PATHINFO_FILENAME);
        if (!$callingResourceBasename) {
            return null;
        }

        // Get the ocr.
        $media = null;
        foreach ($callingResource->item()->media() as $media) {
            if ($media->id() === $callingResourceId) {
                continue;
            }
            $resourceBasename = pathinfo((string) $media->source(), PATHINFO_FILENAME);
            if ($resourceBasename !== $callingResourceBasename) {
                continue;
            }
            $mediaType = $media->mediaType();
            if (!in_array($mediaType, [
                'application/vnd.alto+xml',
                'application/alto+xml',
            ])) {
                continue;
            }
            break;
        }
        if (!$media) {
            return null;
        }

        $relatedMedias[$mediaId] = $media;
        return $relatedMedias[$mediaId];
    }
}
