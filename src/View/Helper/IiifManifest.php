<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
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

use IiifServer\Mvc\Controller\Plugin\TileInfo;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFileFactory;
use Zend\View\Helper\AbstractHelper;

class IiifManifest extends AbstractHelper
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
     * Get the IIIF manifest for the specified resource.
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
            return $this->view->iiifCollection($resource);
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
            'service' => '',
            // For example the web page of the item.
            'related' => '',
            // Other formats of the same data.
            'seeAlso' => '',
            'within' => '',
            'metadata' => [],
            'mediaSequences' => [],
            'sequences' => [],
        ];

        $url = $this->view->url(
            'iiifserver_presentation_item',
            ['id' => $item->id()],
            ['force_canonical' => true]
        );
        $url = $this->view->iiifForceBaseUrlIfRequired($url);
        $manifest['@id'] = $url;

        // The base url for some other ids.
        $this->_baseUrl = dirname($url);

        // Prepare the metadata of the record.
        // TODO Manage filter and escape?
        $metadata = [];
        foreach ($item->values() as $propertyData) {
            $valueMetadata = [];
            $valueMetadata['label'] = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $valueValues = array_filter(array_map(function ($v) {
                return $v->type() === 'resource'
                    ? $this->view->iiifUrl($v->valueResource())
                    : (string) $v;
            }, $propertyData['values']), 'strlen');
            $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
            $metadata[] = (object) $valueMetadata;
        }
        $manifest['metadata'] = $metadata;

        $label = $item->displayTitle('') ?: $this->view->iiifUrl($item);
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

        // TODO To parameter or to extract from metadata.
        /*
        $metadata['service'] = array(
            '@context' =>'http://example.org/ns/jsonld/context.json',
            '@id' => 'http://example.org/service/example',
            'profile' => 'http://example.org/docs/example-service.html',
        );
        */

        // TODO To parameter or to extract from metadata (Dublin Core Relation).
        /*
        $metadata['seeAlso'] = array(
            '@id' => 'http://www.example.org/library/catalog/book1.marc',
            'format' =>'application/marc',
        );
        */

        $withins = [];
        foreach ($item->itemSets() as $itemSet) {
            $within = $this->view->url(
                'iiifserver_presentation_collection',
                ['id' => $itemSet->id()],
                ['force_canonical' => true]
            );
            $within = $this->view->iiifForceBaseUrlIfRequired($within);
            $withins[] = $within;
        }
        if (!empty($withins)) {
            $within = count($withins) > 1
                ? $withins
                : reset($withins);
            $metadata['within'] = $within;
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
                $otherContent = array();
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
                $render = array();
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

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        $manifest = (object) $manifest;
        return $manifest;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka file.
     *
     * @param MediaRepresentation $media
     * @return \stdClass|null
     */
    protected function _iiifThumbnail(MediaRepresentation $media)
    {
        $imageSize = $this->getView()->imageSize($media, 'square');
        if (empty($imageSize)) {
            return;
        }
        list($width, $height) = array_values($imageSize);

        $thumbnail = [];

        $imageUrl = $this->view->url(
            'iiifserver_image_url',
            [
                'id' => $media->id(),
                'region' => 'full',
                'size' => $width . ',' . $height,
                'rotation' => 0,
                'quality' => 'default',
                'format' => 'jpg',
            ],
            ['force_canonical' => true]
        );
        $imageUrl = $this->view->iiifForceBaseUrlIfRequired($imageUrl);
        $thumbnail['@id'] = $imageUrl;

        $thumbnailService = [];
        $thumbnailService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $thumbnailServiceUrl = $this->view->url(
            'iiifserver_image',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );
        $thumbnailServiceUrl = $this->view->iiifForceBaseUrlIfRequired($thumbnailServiceUrl);
        $thumbnailService['@id'] = $thumbnailServiceUrl;
        $thumbnailService['profile'] = 'http://iiif.io/api/image/2/level2.json';
        $thumbnailService = (object) $thumbnailService;

        $thumbnail['service'] = $thumbnailService;
        $thumbnail = (object) $thumbnail;

        return $thumbnail;
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

        $tiles = [];
        $tileInfo = new TileInfo();
        $tilingData = $tileInfo($media);
        $iiifTileInfo = $tilingData ? $this->iiifTileInfo($tilingData) : null;

        // This is a tiled image.
        if ($iiifTileInfo) {
            $imageSize = $view->imageSize($media, 'large');
            list($widthLarge, $heightLarge) = $imageSize ? array_values($imageSize) : [null, null];
            $imageUrl = $this->view->url(
                'iiifserver_image',
                [
                    'id' => $media->id(),
                    'region' => 'full',
                    'size' => $widthLarge . ',' . $heightLarge,
                    'rotation' => 0,
                    'quality' => 'default',
                    'format' => 'jpg',
                ],
                ['force_canonical' => true]
            );
            $imageUrl = $this->view->iiifForceBaseUrlIfRequired($imageUrl);

            $imageResource['@id'] = $imageUrl;
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = $media->mediaType();
            $imageResource['width'] = $width;
            $imageResource['height'] = $height;

            $imageUrl = $this->view->url(
                'iiifserver_image',
                ['id' => $media->id()],
                ['force_canonical' => true]
            );
            $imageUrl = $this->view->iiifForceBaseUrlIfRequired($imageUrl);

            $imageResourceService = [];
            $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';
            $imageResourceService['@id'] = $imageUrl;
            $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';
            $imageResourceService['width'] = $width;
            $imageResourceService['height'] = $height;

            $tiles = [];
            $tiles[] = $iiifTileInfo;
            $imageResourceService['tiles'] = $tiles;

            $imageResourceService = (object) $imageResourceService;

            $imageResource['service'] = $imageResourceService;
            $imageResource = (object) $imageResource;
        }

        // Simple light image.
        else {
            $imageResource['@id'] = $media->originalUrl();
            $imageResource['@type'] = 'dctypes:Image';
            $imageResource['format'] = $media->mediaType();
            $imageResource['width'] = $width;
            $imageResource['height'] = $height;

            $imageResourceService = [];
            $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';

            $imageUrl = $this->view->url(
                'iiifserver_image',
                ['id' => $media->id()],
                ['force_canonical' => true]
            );
            $imageUrl = $this->view->iiifForceBaseUrlIfRequired($imageUrl);
            $imageResourceService['@id'] = $imageUrl;
            $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';
            $imageResourceService = (object) $imageResourceService;

            $imageResource['service'] = $imageResourceService;
            $imageResource = (object) $imageResource;
        }

        $image['resource'] = $imageResource;
        $image['on'] = $canvasUrl;
        $image = (object) $image;

        return $image;
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

        $titleFile = $media->value('dcterms:title', ['type' => 'literal']);
        $canvasUrl = $this->_baseUrl . '/canvas/p' . $index;

        $canvas['@id'] = $canvasUrl;
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $titleFile ?: '[' . $index .']';

        // Thumbnail of the current file.
        $canvas['thumbnail'] = $this->_iiifThumbnail($media);

        // Size of canvas should be the double of small images (< 1200 px), but
        // only when more than image is used by a canvas.
        $imageSize = $this->getView()->imageSize($media, 'original');
        list($width, $height) = $imageSize ? array_values($imageSize) : [null, null];
        $canvas['width'] = $width;
        $canvas['height'] = $height;

        $image = $this->_iiifImage($media, $index, $canvasUrl, $width, $height);

        $images = [];
        $images[] = $image;
        $canvas['images'] = $images;

        $canvas = (object) $canvas;

        return $canvas;
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
        $mseUrl = $this->view->url(
            'iiifserver_media',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );
        $mseUrl = $this->view->iiifForceBaseUrlIfRequired($mseUrl);
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
        $mseUrl = $this->view->url(
            'iiifserver_media',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );
        $mseUrl = $this->view->iiifForceBaseUrlIfRequired($mseUrl);
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
        $mseUrl = $this->view->url(
            'iiifserver_media',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );
        $mseUrl = $this->view->iiifForceBaseUrlIfRequired($mseUrl);
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
            return;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;
        return $tile;
    }

    /**
     * Get a storage path.
     *
     * @param string $prefix The storage prefix
     * @param string $name The file name, or basename if extension is passed
     * @param null|string $extension The file extension
     * @return string
     * @todo Refactorize.
     */
    protected function getStoragePath($prefix, $name, $extension = null)
    {
        return sprintf('%s/%s%s', $prefix, $name, $extension ? ".$extension" : null);
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
}
