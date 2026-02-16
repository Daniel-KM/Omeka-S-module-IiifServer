<?php declare(strict_types=1);

/*
 * Copyright 2015-2024 Daniel Berthereau
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

use IiifServer\Iiif\TraitDescriptiveRights;
use IiifServer\Iiif\TraitStructuralStructures;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\AssetRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFileFactory;
use Omeka\Settings\Settings;

/**
 * The casting to (object) that was used because the json encoding converts array into object or array, was removed.
 */
class IiifManifest2 extends AbstractHelper
{
    use TraitDescriptiveRights;
    use TraitStructuralStructures;

    /**
     * @var int
     */
    protected $defaultHeight = 400;

    /**
     * @var \IiifServer\View\Helper\RangeToArray
     */
    protected $rangeToArray;

    /**
     * @var \Omeka\View\Helper\Setting
     */
    protected $setting;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \Omeka\File\TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * Full path to the files.
     *
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUrlFiles;

    /**
     * @var string
     */
    protected $baseUrlIiif;

    /**
     * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation
     */
    protected $resource;

    public function __construct(
        Settings $settings,
        TempFileFactory $tempFileFactory,
        ?string $basePath
    ) {
        $this->settings = $settings;
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
        $this->resource = $resource;

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
     *
     * @see https://iiif.io/api/presentation/2.1/
     */
    protected function buildManifestItem(ItemRepresentation $item)
    {
        // Prepare values needed for the manifest. Empty values will be removed.
        // Some are required.
        $manifest = [
            // Metadata about this manifest file.
            '@context' => '',
            '@id' => '',
            '@type' => 'sc:Manifest',

            // Descriptive metadata about the object/work.
            'label' => '',
            'metadata' => [],
            'description' => '',
            'thumbnail' => '',

            // Presentation information.
            'viewingDirection' => '',
            'viewingHint' => '',

            // Rights Information.
            'license' => '',
            'attribution' => '',
            // A logo to add at the end of the information panel.
            'logo' => '',

            // Links.
            // For example the web page of the item.
            'related' => [],
            'service' => [],
            // Other formats of the same data.
            'seeAlso' => [],
            'rendering' => [],
            'within' => '',

            // List of sequences.
            'sequences' => [],
            'mediaSequences' => [],
        ];

        $manifest['@id'] = $this->view->iiifUrl($item, 'iiifserver/manifest', '2');

        // Required for TraitDescriptiveRights and to avoid to load setting each time.
        $helpers = $this->view->getHelperPluginManager();
        $this->setting = $helpers->get('setting');

        // The base url of files is used for derivative files.
        $this->baseUrlFiles = $this->view->serverUrl($this->view->basePath('/files'));

        // The base url for some other ids to quick process.
        $this->baseUrlIiif = $this->view->iiifUrl($item, 'iiifserver/uri', '2', [
            'type' => 'annotation-page',
            'name' => '',
        ]);
        $this->baseUrlIiif = mb_substr($this->baseUrlIiif, 0, (int) mb_strpos($this->baseUrlIiif, '/annotation-page'));

        $metadata = $this->iiifMetadata($item);
        $manifest['metadata'] = $metadata;

        // Don't use html in a title!
        $label = $this->view->escapeHtml($item->displayTitle('') ?: $manifest['@id']);
        $label = html_entity_decode($label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $manifest['label'] = $label;

        $descriptionProperty = $this->setting->__invoke('iiifserver_manifest_summary_property');
        if ($descriptionProperty) {
            $description = $descriptionProperty === 'template'
                ? strip_tags((string) $item->displayDescription())
                : strip_tags((string) $item->value($descriptionProperty, ['default' => '']));
        } else {
            $description = '';
        }
        $manifest['description'] = $description;

        // A license url is not a requirement in v2.1, but a recommandation.
        $license = $this->rightsResource($item);
        $isLicenseUrl = $license
            && (substr($license, 0, 8) === 'https://' || substr($license, 0, 7) === 'http://');

        $attributionProperty = $this->setting->__invoke('iiifserver_manifest_attribution_property');
        if ($attributionProperty) {
            $attribution = strip_tags((string) $item->value($attributionProperty, ['default' => '']));
        }
        if (empty($attribution)) {
            $attribution = $isLicenseUrl || empty($license)
                ? $this->setting->__invoke('iiifserver_manifest_attribution_default')
                : $license;
        }
        $manifest['attribution'] = $attribution;

        if ($license && $attribution !== $license) {
            $manifest['license'] = $license;
        }

        $logo = $this->setting->__invoke('iiifserver_manifest_logo_default');
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

        // The viewing direction and hint can be set in manifest and sequence.
        $viewingDirectionProperty = $this->setting->__invoke('iiifserver_manifest_viewing_direction_property');
        if ($viewingDirectionProperty) {
            $viewingDirection = strip_tags((string) $item->value($viewingDirectionProperty));
        }
        if (empty($viewingDirection)) {
            $viewingDirection = $this->setting->__invoke('iiifserver_manifest_viewing_direction_default');
        }
        if (in_array($viewingDirection, ['left-to-right', 'right-to-left', 'top-to-bottom', 'bottom-to-top'])) {
            $manifest['viewingDirection'] = $viewingDirection;
        }

        $allowedViewingHintManifest = ['individuals', 'paged', 'continuous'];
        $viewingHintProperty = $this->setting->__invoke('iiifserver_manifest_behavior_property');
        if ($viewingHintProperty) {
            $viewingHint = strip_tags((string) $item->value($viewingHintProperty));
        }
        if (empty($viewingHint)) {
            $viewingHint = $this->setting->__invoke('iiifserver_manifest_behavior_default', []);
            if (in_array('none', $viewingHint)) {
                $viewingHint = 'none';
            } else {
                $viewingHint = array_intersect($viewingHint, $allowedViewingHintManifest);
                $viewingHint = reset($viewingHint) ?: 'none';
            }
        }
        if (in_array($viewingHint, $allowedViewingHintManifest)) {
            $manifest['viewingHint'] = $viewingHint;
        }

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

                    case 'application/xml':
                    case 'text/xml':
                        $render = [];
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $mediaType;
                        $render['label'] = $translate('Download as XML'); // @translate
                        $rendering[] = $render;
                        break;

                    case 'application/alto+xml':
                        $render = [];
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $mediaType;
                        $render['label'] = $translate('Download as ALTO XML'); // @translate
                        $rendering[] = $render;
                        break;

                    case 'text/plain':
                        $render = [];
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $mediaType;
                        $render['label'] = $translate('Download as text'); // @translate
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
            $mediaSequence['@id'] = $this->baseUrlIiif . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequences[] = $mediaSequence;
        }
        // When there are images.
        elseif ($totalImages) {
            $sequence = [];
            $sequence['@id'] = $this->baseUrlIiif . '/sequence/normal';
            $sequence['@type'] = 'sc:Sequence';
            $sequence['label'] = 'Current Page Order';

            // The viewing direction and hint can be set in manifest and sequence.
            // Manifest and sequence follow the same rules, so just copy it,
            // even if it is useless when they are the same.
            if (!empty($manifest['viewingDirection'])) {
                $sequence['viewingDirection'] = $viewingDirection;
            }
            if (!empty($manifest['viewingHint'])) {
                $sequence['viewingHint'] = $viewingHint;
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
            $mediaSequence['@id'] = $this->baseUrlIiif . '/sequence/s0';
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

        // TODO Flat structure is currently not skipped in iiif v2.
        // $appendFlatStructure = !$this->setting->__invoke('iiifserver_manifest_structures_skip_flat');
        $structureProperty = $this->setting->__invoke('iiifserver_manifest_structures_property');

        if ($structureProperty) {
            // Only the first sequence is used in iiif v2.
            $literalStructure = (string) $item->value($structureProperty);
            if ($literalStructure) {
                // Check if a structure is stored as xml or toc code.
                // Json is currently unsupported.
                $toc = $this->extractStructure($literalStructure);
                if (!empty($toc)) {
                    $this->rangeToArray = $this->view->plugin('rangeToArray');
                    $indexMedias = [];
                    foreach ($canvases as $index => $sequenceCanvas) {
                        $id = $sequenceCanvas['images'][0]['resource']['service']['@id']
                            ?? $sequenceCanvas['images'][0]['resource']['service']['id']
                            ?? null;
                        if ($id) {
                            // The basename of the service id is the media id.
                            $indexView = $index + 1;
                            $indexMedias[$indexView] = (int) basename($id);
                        }
                    }
                    $ranges = $this->finalizeToc($toc, $indexMedias);
                    $structure = $this->convertStructure($ranges, $indexMedias, $canvases);
                    if (count($structure) > 0) {
                        $manifest['structures'] = $structure;
                    }
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

        if ($settingHelper('iiifserver_manifest_structures_property')) {
            $blacklist[] = $settingHelper('iiifserver_manifest_structures_property');
        }

        if ($blacklist) {
            $values = array_diff_key($values, array_flip($blacklist));
        }

        if (empty($values)) {
            return [];
        }

        // TODO Remove automatically special properties, and only for values that are used (check complex conditions…).

        return $settingHelper('iiifserver_manifest_html_descriptive')
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
                $vr = $v->valueResource();
                return $vr
                    ? sprintf('%1$s (%2$s)', $vr->displayTitle, $publicResourceUrl($vr, true))
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
                if ($vr = $v->valueResource()) {
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
     * @return array|null
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
            $size = $this->getView()->imageSize($media, 'medium');
            if ($size && $size['width']) {
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
            if (empty($mediaData)
                || !isset($mediaData['@context'])
                || !isset($mediaData['profile'])
                || (empty($mediaData['@id']) && empty($mediaData['id']))
            ) {
                return null;
            }
            // In 3.0, the "@id" property becomes "id".
            $imageBaseUri = $mediaData['@id'] ?? $mediaData['id'];
            // In Image API 3.0, @context can be a list, https://iiif.io/api/image/3.0/#52-technical-properties.
            $imageApiContextUri = is_array($mediaData['@context']) ? array_pop($mediaData['@context']) : $mediaData['@context'];
            $imageComplianceLevelUri = is_array($mediaData['profile']) ? $mediaData['profile'][0] : $mediaData['profile'];
            $imageComplianceLevel = $this->iiifComplianceLevel($mediaData['profile']);
            $imageUrl = $this->iiifThumbnailUrl($imageBaseUri, $imageApiContextUri, $imageComplianceLevel);
            $service = $this->iiifImageService($imageBaseUri, $imageApiContextUri, $imageComplianceLevelUri);
            $thumbnail = [
                'id' => $imageUrl,
                '@type' => 'dctypes:Image',
                'format' => 'image/jpeg',
            ];
            if (!empty($mediaData['width']) && !empty($mediaData['height'])) {
                $thumbnail['width'] = $mediaData['width'] * $mediaData['height'] / $this->defaultHeight;
                $thumbnail['height'] = $this->defaultHeight;
            }
            $thumbnail['service'] = $service;
            return $thumbnail;
        }

        return null;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka asset file.
     *
     * @param AssetRepresentation $asset
     * @return array|null
     */
    protected function _iiifThumbnailAsset(AssetRepresentation $asset)
    {
        $size = $this->view->imageSize($asset);
        if ($size && $size['width']) {
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
     * @return array|null
     */
    protected function _iiifImage(MediaRepresentation $media, $index, $canvasUrl, $width = null, $height = null)
    {
        $view = $this->getView();
        if (empty($width) || empty($height)) {
            [$width, $height] = array_values($view->imageSize($media, 'original'));
        }

        $image = [];
        $image['@id'] = $this->baseUrlIiif . '/annotation/p' . sprintf('%04d', $index) . '-image';
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

            $imageResource['@id'] = $this->iiifImageFullUrl($imageBaseUri, $imageApiContextUri);
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = 'image/jpeg';
            $imageResource['width'] = $mediaData['width'];
            $imageResource['height'] = $mediaData['height'];

            $imageResourceService = $this->iiifImageService($imageBaseUri, $imageApiContextUri, $imageComplianceLevelUri);
            $imageResource['service'] = $imageResourceService;

            $image['resource'] = $imageResource;
            $image['on'] = $canvasUrl;
            return $image;
        }

        // In api v2, only one service can be set.
        $supportedVersion = $this->setting->__invoke('iiifserver_media_api_default_supported_version', ['service' => '0', 'level' => '0']);
        $service = $supportedVersion['service'];
        $level = $supportedVersion['level'];

        // According to https://iiif.io/api/presentation/2.1/#image-resources,
        // "the URL may be the complete URL to a particular size of the image
        // content", so the large one here, and it's always a jpeg.
        // It's not needed to use the full original size.
        [$widthLarge, $heightLarge] = array_values($view->imageSize($media, 'large'));
        $imageUrl = $this->view->iiifMediaUrl($media, 'imageserver/media', $service ?: '2', [
            'region' => 'full',
            'size' => $widthLarge && $heightLarge
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
            $contextUri = $this->iiifImageServiceUri($service);
            $profileUri = $this->iiifImageProfileUri($contextUri, $level);
            $imageResourceService = $this->iiifImageService($imageUrlService, $contextUri, $profileUri);
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
     * @return array|null
     */
    protected function _iiifCanvasImage(MediaRepresentation $media, $index)
    {
        $canvas = [];

        $prefixIndex = (string) (int) $index === (string) $index ? 'p' : '';
        $canvasUrl = $this->baseUrlIiif . '/canvas/' . $prefixIndex . $index;

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
            [$width, $height] = array_values($this->getView()->imageSize($media, 'original'));
            $canvas['width'] = $width;
            $canvas['height'] = $height;
        }

        // Ocr content can be added when the image and the xml are not stored at
        // the same place.
        $seeAlso = $this->seeAlso($media, $index);
        if ($seeAlso) {
            $canvas['seeAlso'] = $seeAlso;
            $otherContent = $this->otherContents($media, $index);
            if ($otherContent) {
                $canvas['otherContent'] = $otherContent;
            }
        }

        // Append annotations from module Annotate.
        $annotations = $this->otherContentAnnotationList($media, $index);
        if ($annotations) {
            empty($canvas['otherContent'])
                ? $canvas['otherContent'] = $annotations
                : $canvas['otherContent'] = array_merge($annotations, $canvas['otherContent']);
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
        $labelOption = $this->setting->__invoke('iiifserver_manifest_canvas_label');
        $fallback = (string) $index;
        switch ($labelOption) {
            case 'property':
                $labelProperty = $this->setting->__invoke('iiifserver_manifest_canvas_label_property');
                return (string) $media->value($labelProperty, ['default' => $fallback]);

            case 'property_or_source':
                $labelProperty = $this->setting->__invoke('iiifserver_manifest_canvas_label_property');
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
                if ($template) {
                    $titleProperty = $template->titleProperty();
                    if ($titleProperty) {
                        $titlePropertyTerm = $template->titleProperty()->term();
                        if ($titlePropertyTerm !== 'dcterms:title') {
                            $label = $media->value($titlePropertyTerm, ['default' => false]);
                        }
                    }
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
     * @return array
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
     * @return array|null
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
     */
    protected function _iiifMediaSequenceAudio(MediaRepresentation $media, $values): array
    {
        [$derivativeUrl, $derivativeMediaType] = $this->derivativeFile($media, 'audio');

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
        $mseRendering['@id'] = $derivativeUrl ?? $media->originalUrl();
        $mseRendering['format'] = $derivativeMediaType ?? $media->mediaType();
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
     */
    protected function _iiifMediaSequenceVideo(MediaRepresentation $media, $values): array
    {
        [$derivativeUrl, $derivativeMediaType] = $this->derivativeFile($media, 'video');

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
        $mseRendering = [];
        $mseRendering['@id'] = $derivativeUrl ?? $media->originalUrl();
        $mseRendering['format'] = $derivativeMediaType ?? $media->mediaType();
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
     * @return array|null
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
     * @return array
     */
    protected function _iiifSequenceUnsupported($rendering = [])
    {
        $sequence = [];
        $sequence['@id'] = $this->baseUrlIiif . '/sequence/normal';
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
     * Convert a literal structure into a range.
     *
     * Iiif v2 supports only one structure, but managed differently. Ranges can
     * have either:
     *   - sub-ranges (with label)
     *   - canvases (without label)
     *   - members (mix of ranges and canvases, all with label).
     * To manage labels of canvases, the identifiers should be an integer (the
     * iiif position of the media), or the label itself.
     *
     * @see https://iiif.io/api/presentation/2.1/#range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     * @see https://glenrobson.github.io/iiif_stuff/toc/
     *
     * @see \IiifServer\Iiif\TraitStructuralStructures::convertStructure()
     */
    protected function convertStructure(array $ranges, array $indexMedias, array $sequenceCanvases): ?array
    {
        // If the values wasn't a formatted structure, there is no indexes.
        // A structure of one page is useless for now.
        if (count($ranges) < 1) {
            return null;
        }

        $referencedCanvases = [];
        foreach (array_keys($indexMedias) as $index) {
            // Sequence canvas is 0-based, but list of views is 1-based.
            $sequenceCanvasIndex = $index - 1;
            $sequenceCanvas = $sequenceCanvases[$sequenceCanvasIndex];
            $referencedCanvases[$index] = [
                '@id' => $sequenceCanvas['@id'] ?? $sequenceCanvas['id'],
                '@type' => 'sc:Canvas',
                // This is the label set in the sequence, that may be
                // different from the label from the table of contents.
                'label' => $sequenceCanvas['label'] ?? null,
            ];
        }

        // Iiif v2 structure does not need to be fully recursive.
        // There may be multiple "top".
        $structure = [];

        // In the new process, there is no more members, since views and
        // ranges are separated.
        // Nevertheless, it is used for flat toc, because it can display labels.

        // Conform to specs, but not working in standard viewers.
        // TODO Better: members should list ranges included canvas of each range. See iiif v3. But not compliant with Mirador v2.

        // When there is no subranges, it means a short table of contents
        // (labels and view number), in which case the table is flat and a
        // specific top is needed.
        $isFlatToc = !array_filter(array_column($ranges, 'ranges', 'name'));

        /*
        if ($isFlatToc) {
            reset($ranges);
            $topKey = key($ranges);
            $rangeData = $ranges[$topKey];
            $range = [
                '@id' => $rangeData['@id'],
                '@type' => 'sc:Range',
                'label' => $rangeData['label'] ?? $this->view->translate('[Content]'), // @translate
                'viewingHint' => 'top',
                'members' => [],
            ];
            // Use the labels from the toc, not from the referenced canvases.
            $isFirst = true;
            foreach ($ranges as $subRangeData) {
                if ($isFirst) {
                    $isFirst = false;
                    continue;
                }
                $firstView = reset($subRangeData['views']);
                if ($firstView && isset($referencedCanvases[$firstView])) {
                    $member = $referencedCanvases[$firstView];
                    $label = $subRangeData['label'] ?? $member['label'] ?? null;
                    if ($label !== null && $label !== '') {
                        $member['label'] = $label;
                        $range['members'][] = $member;
                    }
                }
            }
            if (!count($range['members'])) {
                return [];
            }
            $structure[] = $range;
            return $structure;
        }
        */

        // Prepare all range ids one time.
        $isInteger = fn ($value): bool => (string) (int) $value === (string) $value;
        foreach ($ranges as $name => &$rangeData) {
            $rangeData['@id'] = $this->baseUrlIiif . '/range/' . ($isInteger($name) ? 'r' . $name : rawurldecode($name));
        }
        unset($rangeData);

        $isFirst = true;
        foreach ($ranges as $rangeData) {
            if (!count($rangeData['ranges']) && !count($rangeData['views'])) {
                continue;
            }

            $range = [
                '@id' => $rangeData['@id'],
                '@type' => 'sc:Range',
                'label' => $rangeData['label'],
            ];

            if ($isFirst && !$isFlatToc) {
                $range['viewingHint'] = 'top';
            }

            // When there is no range on top, it means that it is a list of
            // views, but it is generally an error.

            if (count($rangeData['ranges'])) {
                foreach ($rangeData['ranges'] as $subRangeName) {
                    $range['ranges'][] = $ranges[$subRangeName]['@id'] ?? null;
                }
                $range['ranges'] = array_filter($range['ranges']);
            }

            if (count($rangeData['views'])
                // According to spec, no views on top, except if there is no
                // ranges.
                && !($isFirst && count($rangeData['ranges']))
            ) {
                foreach ($rangeData['views'] as $view) {
                    $range['canvases'][] = $referencedCanvases[$view]['@id'] ?? null;
                }
                $range['canvases'] = array_filter($range['canvases']);
            }

            $isFirst = false;

            $structure[] = $range;
        }

        return $structure;
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
            // TODO Check privacy of media for user. Use read if possible.
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
    protected function iiifThumbnailUrl($baseUri, $contextUri, $complianceLevel): string
    {
        // NOTE: this function does not support level0 implementations (need to use `sizes` from the info.json)
        // TODO handle square thumbnails, depending on server capabilities (see 'regionSquare' feature https://iiif.io/api/image/2.1/#profile-description): e.g. $baseUri . '/square/200,200/0/default.jpg';

        if ($complianceLevel != 'level0') {
            switch ($contextUri) {
                case '1.1':
                case 'http://library.stanford.edu/iiif/image-api/context.json':
                case 'http://library.stanford.edu/iiif/image-api/1.0/context.json':
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
    protected function iiifImageFullUrl($baseUri, $contextUri): string
    {
        switch ($contextUri) {
            case '1.1':
            case 'http://library.stanford.edu/iiif/image-api/context.json':
            case 'http://library.stanford.edu/iiif/image-api/1.0/context.json':
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
    protected function iiifImageServiceUri($service): string
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
     *@see https://iiif.io/api/image/1.1/compliance/
     *
     * Copy:
     * @see \IiifServer\Iiif\Annotation\Body::iiifComplianceLevel()
     * @see \IiifServer\Iiif\TraitDescriptiveThumbnail::iiifComplianceLevel()
     * @see \IiifServer\View\Helper\IiifManifest2::iiifComplianceLevel()
     *
     * @param array|string $profile Contents of the `profile` property from the
     * info.json
     * @return string Image API compliance level (returned value: level0 | level1 | level2)
     */
    protected function iiifComplianceLevel($profile): string
    {
        // In Image API 2.1, the profile property is a list, and the first entry
        // is the compliance level URI.
        // In Image API 1.1 and 3.0, the profile property is a string.
        if (is_array($profile)) {
            $profile = reset($profile);
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
    protected function iiifImageProfileUri($contextUri, $level): string
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
     * Copy:
     * @see \IiifServer\Iiif\TraitDescriptiveThumbnail::iiifImageService()
     * @see \IiifServer\View\Helper\IiifManifest2::iiifImageService()
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
    protected function iiifImageService($baseUri, $contextUri, $complianceLevelUri): array
    {
        $service = [];
        $service['@context'] = $contextUri;
        $service['@id'] = $baseUri;
        $service['profile'] = $complianceLevelUri;
        return $service;
    }

    /**
     * Added in order to use trait TraitDescriptiveRights.
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
    protected function seeAlso(MediaRepresentation $media, $indexOne): ?array
    {
        $relatedMedia = $this->view->iiifMediaRelatedOcr($media, (int) $indexOne ?: null);
        if (!$relatedMedia) {
            return null;
        }
        return [
            '@id' => $relatedMedia->originalUrl(),
            'profile' => 'http://www.loc.gov/standards/alto/v4/alto.xsd',
            'format' => 'application/alto+xml',
            'label' => 'ALTO XML',
        ];
    }

    /**
     * @todo Factorize.
     *
     * Note: multiple other content may be returned, so it's an array of arrays,
     * even if there is only one currently.
     */
    protected function otherContents(MediaRepresentation $media, $indexOne): ?array
    {
        $relatedMedia = $this->view->iiifMediaRelatedOcr($media, (int) $indexOne ?: null);
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
            'label' => $this->view->translate('Text of current page'), // @ŧranslate
        ]];
    }

    /**
     * The annotation list is a reference to the list of the annotations of the
     * module Annotate. There is only one list, so don't return an array of
     * arrays.
     */
    protected function otherContentAnnotationList(MediaRepresentation $media, $indexOne): ?array
    {
        static $api;
        static $oaHasSelectorId;

        if ($api === null) {
            $plugins = $this->view->getHelperPluginManager();
            $api = $plugins->has('annotations') ? $plugins->get('api') : false;
            if (!$api) {
                return null;
            }
            $easyMeta = $plugins->get('easyMeta');
            $oaHasSelectorId = $easyMeta->propertyId('oa:hasSelector');
            if (!$oaHasSelectorId) {
                $api = false;
                return null;
            }
        } elseif (!$api) {
            return null;
        }

        // Check if media has at least one annotation via oa:hasSelector to set
        // the reference to the list.
        $has = $api->search('annotations', [
            'property' => [[
                'property' => $oaHasSelectorId,
                'type' => 'res',
                'text' => (string) $media->id(),
            ]],
            'limit' => 0,
        ], ['initialize' => false, 'finalize' => false])->getTotalResults();

        if (!$has) {
            return null;
        }

        $id = $this->view->iiifUrl($media->item(), 'iiifserver/uri', '2', [
            'type' => 'annotation-list',
            'name' => $media->id(),
        ]);
        return [
            '@id' => $id,
            '@type' => 'sc:AnnotationList',
            'label' => $this->view->translate('Annotations'), // @ŧranslate
        ];
    }

    /**
     * Use derivative files for non-standard files (wmv, asf,  apple, etc.).
     *
     *  Requires the files available in media data, that are done through module
     *  Derivative Media.
     *
     * @return array Array with derivative url and derivative media type.
     */
    protected function derivativeFile(MediaRepresentation $media, string $type): array
    {
        // TODO Use view helper DerivativeList.

        $derivatives = [
            'audio' => [
                'mp3' => 'audio/mpeg',
                'ogg' => 'audio/ogg',
            ],
            'video' => [
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
            ],
        ];

        $data = $media->mediaData();
        $hasDerivative = isset($data['derivative']) && count($data['derivative']);
        if (!$hasDerivative || !isset($derivatives[$type])) {
            return [null, null];
        }

        $enabled = $this->setting->__invoke('derivativemedia_enable', []);
        if (!in_array($type, $enabled)) {
            return [null, null];
        }

        foreach ($derivatives[$type] as $folder => $mediaType) {
            if (isset($data['derivative'][$folder])) {
                $derivative = $data['derivative'][$folder];
                $derivativeUrl = $this->baseUrlFiles . '/' . $folder . '/' . $derivative['filename'];
                $derivativeMediaType = empty($derivative['type']) ? $mediaType : $derivative['type'];
                return [$derivativeUrl, $derivativeMediaType];
            }
        }

        return [null, null];
    }
}
