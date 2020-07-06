<?php

/*
 * Copyright 2015-2020 Daniel Berthereau
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

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFileFactory;
use Zend\View\Helper\AbstractHelper;

class IiifManifest2 extends AbstractHelper
{
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

        // The base url for some other ids to quick process.
        $this->_baseUrl = $this->view->iiifUrl($item, 'iiifserver/uri', '2', [
            'type' => 'annotation-page',
            'name' => '',
        ]);
        $this->_baseUrl = mb_substr($this->_baseUrl, 0, mb_strpos($this->_baseUrl, '/annotation-page'));

        $metadata = $this->iiifMetadata($item);
        $manifest['metadata'] = $metadata;

        $label = $item->displayTitle('') ?: $manifest['@id'];
        $manifest['label'] = $label;

        $descriptionProperty = $this->view->setting('iiifserver_manifest_description_property');
        if ($descriptionProperty) {
            $description = strip_tags($item->value($descriptionProperty, ['type' => 'literal']));
        } else {
            $description = '';
        }
        $manifest['description'] = $description;

        $licenseProperty = $this->view->setting('iiifserver_license_property');
        if ($licenseProperty) {
            $license = $item->value($licenseProperty);
        }
        if (empty($license)) {
            $license = $this->view->setting('iiifserver_manifest_license_default');
        }
        $manifest['license'] = $license;

        $attributionProperty = $this->view->setting('iiifserver_manifest_attribution_property');
        if ($attributionProperty) {
            $attribution = strip_tags($item->value($attributionProperty, ['type' => 'literal']));
        }
        if (empty($attribution)) {
            $attribution = $this->view->setting('iiifserver_manifest_attribution_default');
        }
        $manifest['attribution'] = $attribution;

        $manifest['logo'] = $this->view->setting('iiifserver_manifest_logo_default');

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

        // Get all images and non-images and detect json files (for 3D model).
        $medias = $item->media();
        $images = [];
        $nonImages = [];
        $jsonFiles = [];
        foreach ($medias as $media) {
            $mediaType = $media->mediaType();
            // Images files.
            // Internal: has_derivative is not only for images.
            if ($mediaType && strpos($mediaType, 'image/') === 0) {
                $images[] = $media;
            }
            // Handle external IIIF images.
            elseif ($media->ingester() == 'iiif') {
                $images[] = $media;
            }
            // Non-images files.
            else {
                $nonImages[] = $media;
                if ($mediaType == 'application/json') {
                    $jsonFiles[] = $media;
                }
                // Check if this is a json file for old Omeka or old imports.
                elseif ($mediaType == 'text/plain') {
                    // Currently, the extension is "txt", even for json files.
                    // switch (strtolower($media->extension())) {
                    //   case 'json':
                    //       $jsonFiles[] = $media;
                    //       break;
                    // }
                    if (pathinfo($media->source(), PATHINFO_EXTENSION) == 'json') {
                        $jsonFiles[] = $media;
                    }
                }
            }
        }
        unset($medias);
        $totalImages = count($images);
        $totalJsonFiles = count($jsonFiles);

        // Prepare an exception.
        // TODO Check if this is really a 3D model for three.js (see https://threejs.org).
        $isThreejs = $totalJsonFiles == 1;

        // Process images, except if they belong to a 3D model.
        if (!$isThreejs) {
            $imageNumber = 0;
            foreach ($images as $media) {
                $canvas = $this->_iiifCanvasImage($media, ++$imageNumber);

                // TODO Add other content.
                /*
                $otherContent = [];
                $otherContent = (object) $otherContent;

                $canvas->otherContent = $otherContent;
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
        // download section.
        if ($totalImages || $isThreejs) {
            foreach ($nonImages as $media) {
                $mediaType = $media->mediaType();
                switch ($mediaType) {
                    case '':
                        break;

                    case 'application/pdf':
                        $render = [];
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $mediaType;
                        $render['label'] = $translate('Download as PDF');
                        $render = (object) $render;
                        $rendering[] = $render;
                        break;

                    case 'text/xml':
                        $render = [];
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $mediaType;
                        $render['label'] = $translate('Download as XML');
                        $render = (object) $render;
                        $rendering[] = $render;
                        break;
                }
                // TODO Add alto files and search.
                // TODO Add other content.
            }

            // Prepare the media sequence for threejs.
            if ($isThreejs) {
                $mediaSequenceElement = $this->_iiifMediaSequenceThreejs(
                    $media,
                    ['label' => $label, 'metadata' => $metadata, 'files' => $images]
                );
                $mediaSequencesElements[] = $mediaSequenceElement;
            }
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

                    case strpos($mediaType, 'audio/') === 0:
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
                    case strpos($mediaType, 'video/') === 0:
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

        // Thumbnail of the whole work.
        // TODO Use resource thumbnail (> Omeka 1.3).
        $manifest['thumbnail'] = $this->_mainThumbnail($item, $isThreejs);

        // Prepare sequences.
        $sequences = [];

        // Manage the exception: the media sequence with threejs 3D model.
        if ($isThreejs && $mediaSequencesElements) {
            $mediaSequence = [];
            $mediaSequence['@id'] = $this->_baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequence = (object) $mediaSequence;
            $mediaSequences[] = $mediaSequence;
        }
        // When there are images.
        elseif ($totalImages) {
            $sequence = [];
            $sequence['@id'] = $this->_baseUrl . '/sequence/normal';
            $sequence['@type'] = 'sc:Sequence';
            $sequence['label'] = 'Current Page Order';
            $sequence['viewingDirection'] = 'left-to-right';
            $sequence['viewingHint'] = $totalImages > 1 ? 'paged' : 'non-paged';
            if ($rendering) {
                $sequence['rendering'] = $rendering;
            }
            $sequence['canvases'] = $canvases;
            $sequence = (object) $sequence;

            $sequences[] = $sequence;
        }

        // Sequences when there is no image (special content).
        elseif ($mediaSequencesElements) {
            $mediaSequence = [];
            $mediaSequence['@id'] = $this->_baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequence = (object) $mediaSequence;
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
                $render['label'] = $translate('Unsupported content.');
                $render = (object) $render;
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

        if ($isThreejs) {
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
        $triggerHelper = $this->view->plugin('trigger');
        $params = compact('manifest', 'resource', 'type');
        $params = $triggerHelper('iiifserver.manifest', $params, true);
        $manifest = $params['manifest'];

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        $manifest = (object) $manifest;
        return $manifest;
    }

    /**
     * Prepare the metadata of a resource.
     *
     * @todo Factorize with IiifCollection.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function iiifMetadata(AbstractResourceEntityRepresentation $resource)
    {
        $jsonLdType = $resource->getResourceJsonLdType();
        $map = [
            'o:ItemSet' => 'iiifserver_manifest_properties_collection',
            'o:Item' => 'iiifserver_manifest_properties_item',
            'o:Media' => 'iiifserver_manifest_properties_media',
        ];
        if (!isset($map[$jsonLdType])) {
            return [];
        }

        $properties = $this->view->setting($map[$jsonLdType]);
        if ($properties === ['none']) {
            return [];
        }

        $values = $properties ? array_intersect_key($resource->values(), array_flip($properties)) : $resource->values();

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
                return strpos($v->type(), 'resource') === 0
                    ? $publicResourceUrl($v->valueResource(), true)
                    : (string) $v;
            }, $propertyData['values']), 'strlen');
            $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
            $metadata[] = (object) $valueMetadata;
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
                if (strpos($v->type(), 'resource') === 0) {
                    $r = $v->valueResource();
                    return '<a class="resource-link" href="' . $publicResourceUrl($r, true) . '">'
                        . '<span class="resource-name">' . $r->displayTitle() . '</span>'
                        . '</a>';
                }
                return $v->asHtml();
            }, $propertyData['values']), 'strlen');
            $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
            $metadata[] = (object) $valueMetadata;
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
        $thumbnail = [];

        // Manage external IIIF image.
        if ($media->ingester() === 'iiif') {
            // The method "mediaData" contains data from the info.json file.
            $mediaData = $media->mediaData();
            // In Image API 3.0, @context can be a list, https://iiif.io/api/image/3.0/#52-technical-properties.
            $imageApiContextUri = is_array($mediaData['@context']) ? array_pop($mediaData['@context']) : $mediaData['@context'];
            // In 3.0, the "@id" property becomes "id".
            $imageBaseUri = $mediaData['@id'] ?: $mediaData['id'];
            $imageComplianceLevelUri = is_array($mediaData['profile']) ? $mediaData['profile'][0] : $mediaData['profile'];

            if ($media->hasThumbnails()) {
                $thumbnail['@id'] = $media->thumbnailUrl('medium');
            }
            // Else use a IIIF URL (e.g. "/full/,200/0/default.jpg" in 2.1).
            else {
                $imageComplianceLevel = $this->_iiifComplianceLevel($mediaData['profile']);
                $thumbnail['@id'] = $this->_iiifThumbnailUrl($imageBaseUri, $imageApiContextUri, $imageComplianceLevel);
            }

            $thumbnailService = $this->_iiifImageService($imageBaseUri, $imageApiContextUri, $imageComplianceLevelUri);

            $thumbnail['service'] = $thumbnailService;
            return (object) $thumbnail;
        }

        $imageSize = $this->getView()->imageSize($media, 'square');
        if (empty($imageSize)) {
            return;
        }
        list($width, $height) = array_values($imageSize);

        $imageUrl = $this->view->iiifImageUrl($media, 'imageserver/media', '2', [
            'region' => 'full',
            'size' => $width . ',' . $height,
            'rotation' => 0,
            'quality' => 'default',
            'format' => 'jpg',
        ]);
        $thumbnail['@id'] = $imageUrl;

        $thumbnailService = [];
        $thumbnailService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $thumbnailServiceUrl = $this->view->iiifImageUrl($media, 'imageserver/id', '2');
        $thumbnailService['@id'] = $thumbnailServiceUrl;
        $thumbnailService['profile'] = 'http://iiif.io/api/image/2/level2.json';
        $thumbnailService = (object) $thumbnailService;

        $thumbnail['service'] = $thumbnailService;
        return (object) $thumbnail;
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
            list($width, $height) = $imageSize ? array_values($imageSize) : [null, null];
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
            // In Image API 3.0, @context can be a list, https://iiif.io/api/image/3.0/#52-technical-properties.
            $imageApiContextUri = is_array($mediaData['@context']) ? array_pop($mediaData['@context']) : $mediaData['@context'];
            // In Image API 3.0, the "@id" property becomes "id".
            $imageBaseUri = $mediaData['@id'] ?: $mediaData['id'];
            $imageComplianceLevelUri = is_array($mediaData['profile']) ? $mediaData['profile'][0] : $mediaData['profile'];

            $imageResource['@id'] = $this->_iiifImageFullUrl($imageBaseUri, $imageApiContextUri);
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = 'image/jpeg';
            $imageResource['width'] = $mediaData['width'];
            $imageResource['height'] = $mediaData['height'];

            $imageResourceService = $this->_iiifImageService($imageBaseUri, $imageApiContextUri, $imageComplianceLevelUri);
            $imageResource['service'] = $imageResourceService;
            $imageResource = (object) $imageResource;

            $image['resource'] = $imageResource;
            $image['on'] = $canvasUrl;
            return (object) $image;
        }

        // According to https://iiif.io/api/presentation/2.1/#image-resources,
        // "the URL may be the complete URL to a particular size of the image
        // content", so the large one here, and it's always a jpeg.
        // It's not needed to use the full original size.
        $imageSize = $view->imageSize($media, 'large');
        list($widthLarge, $heightLarge) = $imageSize ? array_values($imageSize) : [null, null];
        $imageUrl = $this->view->iiifImageUrl($media, 'imageserver/media', '2', [
            'region' => 'full',
            'size' => $widthLarge . ',' . $heightLarge,
            'rotation' => 0,
            'quality' => 'default',
            'format' => 'jpg',
        ]);

        $imageResource['@id'] = $imageUrl;
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['format'] = 'image/jpeg';
        $imageResource['width'] = $width;
        $imageResource['height'] = $height;

        $imageUrlService = $this->view->iiifImageUrl($media, 'imageserver/id', '2');
        $imageResourceService = [];
        $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $imageResourceService['@id'] = $imageUrlService;
        $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';

        // TODO Use the trait TileInfo of module ImageServer.
        $viewHelpers = $view->getHelperPluginManager();
        if ($viewHelpers->has('tileInfo')) {
            $tilingData = $view->tileInfo($media);
            $iiifTileInfo = $tilingData ? $this->iiifTileInfo($tilingData) : null;
            if ($iiifTileInfo) {
                $tiles = [];
                $tiles[] = $iiifTileInfo;
                $imageResourceService['tiles'] = $tiles;
                $imageResourceService['width'] = $width;
                $imageResourceService['height'] = $height;
            }
        }

        $imageResourceService = (object) $imageResourceService;
        $imageResource['service'] = $imageResourceService;
        $imageResource = (object) $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = $canvasUrl;
        return (object) $image;
    }

    /**
     * Create an IIIF canvas object for an image.
     *
     * @param MediaRepresentation $media
     * @param int $index Used to set the standard name of the image.
     * @return \stdClass|null
     */
    protected function _iiifCanvasImage(MediaRepresentation $media, $index)
    {
        $canvas = [];

        $canvasUrl = $this->_baseUrl . '/canvas/p' . $index;

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
            $imageSize = $this->getView()->imageSize($media, 'original');
            list($width, $height) = $imageSize ? array_values($imageSize) : [null, null];
            $canvas['width'] = $width;
            $canvas['height'] = $height;
        }

        $image = $this->_iiifImage($media, $index, $canvasUrl, $width, $height);

        $images = [];
        $images[] = $image;
        $canvas['images'] = $images;

        $metadata = $this->iiifMetadata($media);
        if ($metadata) {
            $canvas['metadata'] = $metadata;
        }

        return (object) $canvas;
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
                return $media->value($labelProperty, ['default' => $fallback]);

            case 'property_or_source':
                $labelProperty = $this->view->setting('iiifserver_manifest_canvas_label_property');
                $label = $media->value($labelProperty, ['default' => '']);
                if (strlen($label)) {
                    return $label;
                }
                // no break;
            case 'source':
                return $media->displayTitle($fallback);

            case 'template_or_source':
                $fallback = $media->displayTitle($fallback);
                // no break;
            case 'template':
                $template = $media->resourceTemplate();
                $label = false;
                if ($template && $template->titleProperty()) {
                    $label = $media->value($labelProperty, ['default' => false]);
                }
                if (!$label) {
                    $label = $media->value('dcterms:title', ['default' => $fallback]);
                }
                return $label;

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
        $translate = $this->getView()->plugin('translate');

        $canvas = [];
        $canvas['@id'] = $this->view->basePath('/iiif/ixif-message/canvas/c1');
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $translate('Placeholder image');

        $placeholder = 'img/thumbnails/placeholder-image.png';
        $canvas['thumbnail'] = $this->view->assetUrl($placeholder, 'IiifServer');

        $imageSize = $this->getWidthAndHeight(OMEKA_PATH . '/modules/IiifServer/asset/' . $placeholder) ?: ['width' => null, 'height' => null];
        $canvas['width'] = $imageSize['width'];
        $canvas['height'] = $imageSize['height'];

        $image = [];
        $image['@id'] = $this->view->basePath('/iiif/ixif-message/imageanno/placeholder');
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed).
        $imageResource = [];
        $imageResource['@id'] = $this->view->basePath('/iiif/ixif-message-0/res/placeholder');
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['width'] = $imageSize['width'];
        $imageResource['height'] = $imageSize['height'];
        $imageResource = (object) $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = $this->view->basePath('/iiif/ixif-message/canvas/c1');
        $image = (object) $image;
        $images = [$image];

        $canvas['images'] = $images;

        $canvas = (object) $canvas;

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
        if ($media->hasThumbnails()) {
            $thumbnailUrl = $media->thumbnailUrl('medium');
            if ($thumbnailUrl) {
                $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
            }
        }
        $mediaSequencesService = [];
        $mseUrl = $this->view->iiifImageUrl($media, 'mediaserver/id', '2');
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequencesService = (object) $mediaSequencesService;
        $mediaSequenceElement['service'] = $mediaSequencesService;
        $mediaSequenceElement = (object) $mediaSequenceElement;
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
        if ($media->hasThumbnails()) {
            $thumbnailUrl = $media->thumbnailUrl('medium');
            if ($thumbnailUrl) {
                $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
            }
        }
        // A place holder is recommended for media.
        if (empty($mediaSequenceElement['thumbnail'])) {
            // $placeholder = 'img/placeholder-audio.jpg';
            // $mediaSequenceElement['thumbnail'] = $this->view->assetUrl($placeholder, 'IiifServer');
            $mediaSequenceElement['thumbnail'] = '';
        }

        // Specific to media files.
        $mseRenderings = [];
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm…
        $mseRendering = [];
        $mseRendering['@id'] = $media->originalUrl();
        $mseRendering['format'] = $media->mediaType();
        $mseRendering = (object) $mseRendering;
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = [];
        $mseUrl = $this->view->iiifImageUrl($media, 'mediaserver/id', '2');
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequencesService = (object) $mediaSequencesService;
        $mediaSequenceElement['service'] = $mediaSequencesService;
        $mediaSequenceElement = (object) $mediaSequenceElement;
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
        if ($media->hasThumbnails()) {
            $thumbnailUrl = $media->thumbnailUrl('medium');
            if ($thumbnailUrl) {
                $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
            }
        }
        // A place holder is recommended for medias.
        if (empty($mediaSequenceElement['thumbnail'])) {
            // $placeholder = 'img/placeholder-video.jpg';
            // $mediaSequenceElement['thumbnail'] = $this->view->assetUrl($placeholder, 'IiifServer');
            $mediaSequenceElement['thumbnail'] = '';
        }

        // Specific to media files.
        $mseRenderings = [];
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm…
        $mseRendering = [];
        $mseRendering['@id'] = $media->originalUrl();
        $mseRendering['format'] = $media->mediaType();
        $mseRendering = (object) $mseRendering;
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = [];
        $mseUrl = $this->view->iiifImageUrl($media, 'mediaserver/id', '2');
        $mediaSequencesService['@id'] = $mseUrl;
        // See MediaController::contextAction()
        $mediaSequencesService['profile'] = 'http://wellcomelibrary.org/ld/ixif/0/alpha.json';
        $mediaSequencesService = (object) $mediaSequencesService;
        $mediaSequenceElement['service'] = $mediaSequencesService;
        // TODO Get the true video width and height, even if it
        // is automatically managed.
        $mediaSequenceElement['width'] = 0;
        $mediaSequenceElement['height'] = 0;
        $mediaSequenceElement = (object) $mediaSequenceElement;
        return $mediaSequenceElement;
    }

    /**
     * Create an IIIF media sequence object for a threejs 3D model.
     *
     * @param MediaRepresentation $media
     * @param array $values
     * @return \stdClass|null
     */
    protected function _iiifMediaSequenceThreejs(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = [];
        $mediaSequenceElement['@id'] = $media->originalUrl();
        $mediaSequenceElement['@type'] = 'dctypes:PhysicalObject';
        $mediaSequenceElement['format'] = 'application/vnd.threejs+json';
        // TODO If no file metadata, then item ones.
        // TODO Currently, the main title and metadata are used,
        // because in Omeka, a 3D model is normally the only one
        // file.
        $mediaSequenceElement['label'] = $values['label'];
        // Metadata are already set at record level.
        // $mediaSequenceElement['metadata'] = $values['metadata'];
        // Check if there is a "thumb.jpg" that can be managed as a thumbnail.
        foreach ($values['files'] as $imageFile) {
            if (basename($imageFile->filename()) == 'thumb.jpg') {
                // The original is used, because this is already a thumbnail.
                $thumbnailUrl = $imageFile->originalUrl();
                if ($thumbnailUrl) {
                    $mediaSequenceElement['thumbnail'] = $thumbnailUrl;
                }
                break;
            }
        }
        // No media sequence service and no sequences.
        $mediaSequenceElement = (object) $mediaSequenceElement;
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
        $sequence['label'] = $this->view->translate('Unsupported extension. This manifest is being used as a wrapper for non-IIIF content (e.g., audio, video) and is unfortunately incompatible with IIIF viewers.');
        $sequence['compatibilityHint'] = 'displayIfContentUnsupported';

        $canvas = $this->_iiifCanvasPlaceholder();

        $canvases = [];
        $canvases[] = $canvas;

        if ($rendering) {
            $sequence['rendering'] = $rendering;
        }
        $sequence['canvases'] = $canvases;
        $sequence = (object) $sequence;

        return $sequence;
    }

    /**
     * Get the representative thumbnail of the whole work.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param bool $isThreejs Manage an exception.
     * @return object The iiif thumbnail.
     */
    protected function _mainThumbnail(AbstractResourceEntityRepresentation $resource, $isThreejs)
    {
        $media = null;
        // Threejs is an exception, because the thumbnail may be a true file
        // named "thumb.js".
        if ($isThreejs) {
            // The connection is used because the api does not allow to search
            // on source name.
            $conn = $resource->getServiceLocator()
                ->get('Omeka\Connection');
            $qb = $conn->createQueryBuilder()
                ->select('id')
                ->from('media', 'media')
                ->where('item_id = :item_id')
                ->setParameter(':item_id', $resource->id())
                ->andWhere('has_thumbnails = 1')
                ->andWhere('source LIKE "%thumb.jpg"')
                ->orderBy('id', 'ASC')
                ->setMaxResults(1);
            $stmt = $conn->executeQuery($qb, $qb->getParameters());
            $id = $stmt->fetch(\PDO::FETCH_COLUMN);
            if ($id) {
                $response = $this->view->api()->read('media', $id);
                $media = $response->getContent();
            }
        }

        // Standard record.
        if (empty($media)) {
            // TODO Use index of the true Omeka representative file (primaryMedia()).
            // The connection is used because the api does not allow to search
            // on field "has_thumbnails".
            // $response = $this->view->api()->search(
            //     'media',
            //     [
            //         'item_id' => $resource->id(),
            //         'has_thumbnails' => 1,
            //         'limit' => 1,
            //     ]
            // );
            // $medias = $response->getContent();
            // $media = reset($medias);

            // TODO Use resource thumbnail (> Omeka 1.3).
            $conn = @$this->getView()->getHelperPluginManager()->getServiceLocator()
                ->get('Omeka\Connection');
            $qb = $conn->createQueryBuilder()
                ->select('id')
                ->from('media', 'media')
                ->where('item_id = :item_id')
                ->setParameter(':item_id', $resource->id())
                ->andWhere('has_thumbnails = 1')
                ->orderBy('id', 'ASC')
                ->setMaxResults(1);
            $stmt = $conn->executeQuery($qb, $qb->getParameters());
            $id = $stmt->fetch(\PDO::FETCH_COLUMN);
            if ($id) {
                $response = $this->view->api()->read('media', $id);
                $media = $response->getContent();
            }
        }

        if ($media) {
            return $this->_iiifThumbnail($media);
        }
    }

    /**
     * Create the data for a IIIF tile object.
     *
     * @param array $tileInfo
     * @return array|null
     */
    protected function iiifTileInfo($tileInfo)
    {
        $tile = [];

        $squaleFactors = [];
        $maxSize = max($tileInfo['source']['width'], $tileInfo['source']['height']);
        $tileSize = $tileInfo['size'];
        $total = (int) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $squaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($squaleFactors) <= 1) {
            return null;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;
        return $tile;
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
                    list($width, $height) = $result;
                }
            }
            unlink($tempPath);
        }
        // A normal path.
        elseif (file_exists($filepath)) {
            $result = getimagesize($filepath);
            if ($result) {
                list($width, $height) = $result;
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
     * Helper to create a IIIF URL for the thumbnail
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
                    return $baseUri . '/full/,200/0/native.jpg';
                case '2':
                case '3':
                case 'http://iiif.io/api/image/2/context.json':
                case 'http://iiif.io/api/image/3/context.json':
                default:
                    return $baseUri . '/full/,200/0/default.jpg';
            }
        }
    }

    /**
     * Helper to set the IIIF full size url of an image, depending on the
     * version of the IIIF Image API supported by the server
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
     * Helper to set the compliance level to the IIIF Image API, based on the
     * compliance level URI
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

        $profileToLlevels = [
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

        return isset($profileToLlevels[$profile])
            ? $profileToLlevels[$profile]
            : 'level0';
    }

    /**
     * Helper to create the IIIF Image API service block
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
        return (object) $service;
    }
}
