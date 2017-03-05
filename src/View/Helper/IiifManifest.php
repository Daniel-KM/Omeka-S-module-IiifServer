<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
 * Copyright 2015-2017 BibLibre
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
use Omeka\File\Manager as FileManager;
use Zend\View\Helper\AbstractHelper;
use \Exception;

class IiifManifest extends AbstractHelper
{
    protected $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Get the IIIF manifest for the specified record.
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
        $manifest = array(
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
            'metadata' => array(),
            'mediaSequences' => array(),
            'sequences' => array(),
        );

        $url = $this->view->url('iiifserver_presentation_item', array(
            'id' => $item->id(),
        ));
        $url = $this->view->iiifForceHttpsIfRequired($url);
        $manifest['@id'] = $url;

        // The base url for some other ids.
        $this->_baseUrl = dirname($url);

        // Prepare the metadata of the record.
        // TODO Manage filter and escape?
        $metadata = [];
        foreach ($item->values() as $term => $value) {
            $metadata[] = (object) [
                'label' => $value['alternate_label'] ?: $value['property']->label(),
                'value' => count($value['values']) > 1
                    ? array_map('strval', $value['values'])
                    : (string) reset($value['values']),
            ];
        }
        $manifest['metadata'] = $metadata;

        $label = $item->displayTitle();
        $manifest['label'] = $label;

        $descriptionProperty = $this->view->setting('iiifserver_manifest_description_property');
        if ($descriptionProperty) {
            $description = strip_tags($item->value($descriptionProperty, array('type' => 'literal')));
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

        $attributionProperty = $this->view->setting('iiifserver_attribution_property');
        if ($attributionProperty) {
            $attribution = strip_tags($item->value($attributionProperty, array('type' => 'literal')));
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

        $withins = array();
        foreach ($item->itemSets() as $itemSet) {
            $within = $this->view->url('iiifserver_presentation_collection', array(
                'id' => $itemSet->id(),
            ));
            $within = $this->view->iiifForceHttpsIfRequired($within);
            $withins[] = $within;
        }
        if (!empty($withins)) {
            $within = count($withins) > 1
                ? $withins
                : reset($withins);
            $metadata['within'] = $within;
        }

        $canvases = array();

        // Get all images and non-images and detect json files (for 3D model).
        $medias = $item->media();
        $images = array();
        $nonImages = array();
        $jsonFiles = array();
        foreach ($medias as $media) {
            $mediaType = $media->mediaType();
            // Images files.
            // Internal: has_derivative is not only for images.
            if (strpos($mediaType, 'image/') === 0) {
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
        unset ($medias);
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
        $rendering = array();
        $mediaSequences = array();
        $mediaSequencesElements = array();

        $translate = $this->getView()->plugin('translate');

        // TODO Manage the case where there is a video, a pdf etc, and the image
        // is only a quick view. So a main file should be set, that is not the
        // representative file.

        // When there are images or one json file, other files may be added to
        // download section.
        if ($totalImages || $isThreejs) {
            foreach ($nonImages as $media) {
                switch ($media->mediaType()) {
                    case 'application/pdf':
                        $render = array();
                        $render['@id'] = $media->originalUrl();
                        $render['format'] = $media->mediaType();
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
                    array('label' => $label, 'metadata' => $metadata, 'files' => $images)
                    );
                $mediaSequencesElements[] = $mediaSequenceElement;
            }
        }

        // Else, check if non-images are managed (special content, as pdf).
        else {
            foreach ($nonImages as $media) {
                switch ($media->mediaType()) {
                    case 'application/pdf':
                        $mediaSequenceElement = $this->_iiifMediaSequencePdf(
                            $media,
                            array('label' => $label, 'metadata' => $metadata)
                        );
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // TODO Add the file for download (no rendering)? The
                        // file is already available for download in the pdf viewer.
                        break;

                    case strpos($media->mediaType(), 'audio/') === 0:
                    // case 'audio/ogg':
                    // case 'audio/mp3':
                        $mediaSequenceElement = $this->_iiifMediaSequenceAudio(
                            $media,
                            array('label' => $label, 'metadata' => $metadata)
                        );
                        $mediaSequencesElements[] = $mediaSequenceElement;
                        // Rendering files are automatically added for download.
                        break;

                    // TODO Check/support the media type "application//octet-stream".
                    // case 'application//octet-stream':
                    case strpos($media->mediaType(), 'video/') === 0:
                    // case 'video/webm':
                        $mediaSequenceElement = $this->_iiifMediaSequenceVideo(
                            $media,
                            array('label' => $label, 'metadata' => $metadata)
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
        $sequences = array();

        // Manage the exception: the media sequence with threejs 3D model.
        if ($isThreejs && $mediaSequencesElements) {
            $mediaSequence = array();
            $mediaSequence['@id'] = $this->_baseUrl . '/sequence/s0';
            $mediaSequence['@type'] = 'ixif:MediaSequence';
            $mediaSequence['label'] = 'XSequence 0';
            $mediaSequence['elements'] = $mediaSequencesElements;
            $mediaSequence = (object) $mediaSequence;
            $mediaSequences[] = $mediaSequence;
        }
        // When there are images.
        elseif ($totalImages) {
            $sequence = array();
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
            $mediaSequence = array();
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
            $manifest['@context'] = array(
                'http://iiif.io/api/presentation/2/context.json',
                'http://files.universalviewer.io/ld/ixif/0/context.json',
            );
        }
        // For images, the normalized context.
        elseif($totalImages) {
            $manifest['@context'] = 'http://iiif.io/api/presentation/2/context.json';
        }
        // For other non standard iiif files.
        else {
            $manifest['@context'] = array(
                'http://iiif.io/api/presentation/2/context.json',
                // See MediaController::contextAction()
                'http://wellcomelibrary.org/ld/ixif/0/context.json',
                // WEB_ROOT . '/ld/ixif/0/context.json',
            );
        }

        // Remove all empty values (there is no "0" or "null" at first level).
        $manifest = array_filter($manifest);

        $manifest = (object) $manifest;
        return $manifest;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka file.
     *
     * @param Omeka\Api\Representation\MediaRepresentation $media
     * @return Standard object|null
     */
    protected function _iiifThumbnail(MediaRepresentation $media)
    {
        $imageSize = $this->_getImageSize($media, 'square');
        list($width, $height) = array_values($imageSize);
        if (empty($width) || empty($height)) {
            return;
        }

        $thumbnail = array();

        $imageUrl = $this->view->url('iiifserver_image_url', array(
            'id' => $media->id(),
            'region' => 'full',
            'size' => $width . ',' . $height,
            'rotation' => 0,
            'quality' => 'default',
            'format' => 'jpg',
        ));
        $imageUrl = $this->view->iiifForceHttpsIfRequired($imageUrl);
        $thumbnail['@id'] = $imageUrl;

        $thumbnailService = array();
        $thumbnailService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $thumbnailServiceUrl = $this->view->url('iiifserver_image', array(
            'id' => $media->id(),
        ));
        $thumbnailServiceUrl = $this->view->iiifForceHttpsIfRequired($thumbnailServiceUrl);
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
     * @param integer $index Used to set the standard name of the image.
     * @param string $canvasUrl Used to set the value for "on".
     * @param integer $width If not set, will be calculated.
     * @param integer $height If not set, will be calculated.
     * @return Standard object|null
     */
    protected function _iiifImage(MediaRepresentation $media, $index, $canvasUrl, $width = null, $height = null)
    {
        if (empty($media)) {
            return;
        }

        if (empty($width) || empty($height)) {
            $sizeFile = $this->_getImageSize($media, 'original');
            list($width, $height) = array_values($sizeFile);
        }

        $image = array();
        $image['@id'] = $this->_baseUrl . '/annotation/p' . sprintf('%04d', $index) . '-image';
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed currently).
        $imageResource = array();

        // Simple light image.
        $imageResource['@id'] = $media->originalUrl();
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['format'] = $media->mediaType();
        $imageResource['width'] = $width;
        $imageResource['height'] = $height;

        $imageResourceService = array();
        $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';

        $imageUrl = $this->view->url('iiifserver_image', array(
            'id' => $media->id(),
        ));
        $imageUrl = $this->view->iiifForceHttpsIfRequired($imageUrl);
        $imageResourceService['@id'] = $imageUrl;
        $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';
        $imageResourceService = (object) $imageResourceService;

        $imageResource['service'] = $imageResourceService;
        $imageResource = (object) $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = $canvasUrl;
        $image = (object) $image;

        return $image;
    }

    /**
     * Create an IIIF canvas object for an image.
     *
     * @param MediaRepresentation $media
     * @param integer $index Used to set the standard name of the image.
     * @return Standard object|null
     */
    protected function _iiifCanvasImage(MediaRepresentation $media, $index)
    {
        $canvas = array();

        $titleFile = $media->value('dcterms:title', array('type' => 'literal'));
        $canvasUrl = $this->_baseUrl . '/canvas/p' . $index;

        $canvas['@id'] = $canvasUrl;
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $titleFile ?: '[' . $index .']';

        // Thumbnail of the current file.
        $canvas['thumbnail'] = $this->_iiifThumbnail($media);

        // Size of canvas should be the double of small images (< 1200 px), but
        // only when more than image is used by a canvas.
        list($width, $height) = array_values($this->_getImageSize($media, 'original'));
        $canvas['width'] = $width;
        $canvas['height'] = $height;

        $image = $this->_iiifImage($media, $index, $canvasUrl, $width, $height);

        $images = array();
        $images[] = $image;
        $canvas['images'] = $images;

        $canvas = (object) $canvas;

        return $canvas;
    }

    /**
     * Create an IIIF canvas object for a place holder.
     *
     * @return Standard object
     */
    protected function _iiifCanvasPlaceholder()
    {
        $translate = $this->getView()->plugin('translate');

        $canvas = array();
        $canvas['@id'] = $this->view->basePath('/iiif/ixif-message/canvas/c1');
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $translate('Placeholder image');

        $placeholder = 'img/thumbnails/placeholder-image.png';
        $canvas['thumbnail'] = $this->view->assetUrl($placeholder, 'IiifServer');

        $imageSize = $this->_getWidthAndHeight(OMEKA_PATH . '/modules/IiifServer/asset/' . $placeholder);
        $canvas['width'] = $imageSize['width'];
        $canvas['height'] = $imageSize['height'];

        $image = array();
        $image['@id'] = $this->view->basePath('/iiif/ixif-message/imageanno/placeholder');
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed).
        $imageResource = array();
        $imageResource['@id'] = $this->view->basePath('/iiif/ixif-message-0/res/placeholder');
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['width'] = $imageSize['width'];
        $imageResource['height'] = $imageSize['height'];
        $imageResource = (object) $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = $this->view->basePath('/iiif/ixif-message/canvas/c1');
        $image = (object) $image;
        $images = array($image);

        $canvas['images'] = $images;

        $canvas = (object) $canvas;

        return $canvas;
    }

    /**
     * Create an IIIF media sequence object for a pdf.
     *
     * @param MediaRepresentation $media
     * @param array $values
     * @return Standard object|null
     */
    protected function _iiifMediaSequencePdf(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = array();
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
        $mediaSequencesService = array();
        $mseUrl = $this->view->url('iiifserver_media', array(
            'id' => $media->id(),
        ));
        $mseUrl = $this->view->iiifForceHttpsIfRequired($mseUrl);
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
     * @return Standard object|null
     */
    protected function _iiifMediaSequenceAudio(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = array();
        $mediaSequenceElement['@id'] = $media->originalUrl() . '/element/e0';
        $mediaSequenceElement['@type'] = 'dctypes:Sound';
        // The format is not be set here (see rendering).
        // $mediaSequenceElement['format'] = $media->mime_type;
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
        $mseRenderings = array();
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm...
        $mseRendering = array();
        $mseRendering['@id'] = $media->originalUrl();
        $mseRendering['format'] = $media->mediaType();
        $mseRendering = (object) $mseRendering;
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = array();
        $mseUrl = $this->view->url('iiifserver_media', array(
            'id' => $media->id(),
        ));
        $mseUrl = $this->view->iiifForceHttpsIfRequired($mseUrl);
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
     * @return Standard object|null
     */
    protected function _iiifMediaSequenceVideo(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = array();
        $mediaSequenceElement['@id'] = $media->originalUrl() . '/element/e0';
        $mediaSequenceElement['@type'] = 'dctypes:MovingImage';
        // The format is not be set here (see rendering).
        // $mediaSequenceElement['format'] = $media->mime_type;
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
        $mseRenderings = array();
        // Only one rendering currently: the file itself, but it
        // may be converted to multiple format: high and low
        // resolution, webm...
        $mseRendering = array();
        $mseRendering['@id'] = $media->originalUrl();
        $mseRendering['format'] = $media->mediaType();
        $mseRendering = (object) $mseRendering;
        $mseRenderings[] = $mseRendering;
        $mediaSequenceElement['rendering'] = $mseRenderings;

        $mediaSequencesService = array();
        $mseUrl = $this->view->url('iiifserver_media', array(
            'id' => $media->id(),
        ));
        $mseUrl = $this->view->iiifForceHttpsIfRequired($mseUrl);
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
     * @return Standard object|null
     */
    protected function _iiifMediaSequenceThreejs(MediaRepresentation $media, $values)
    {
        $mediaSequenceElement = array();
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
     * @return Standard object
     */
    protected function _iiifSequenceUnsupported($rendering = array())
    {
        $sequence = array();
        $sequence['@id'] = $this->_baseUrl . '/sequence/normal';
        $sequence['@type'] = 'sc:Sequence';
        $sequence['label'] = $this->view->translate('Unsupported extension. This manifest is being used as a wrapper for non-IIIF content (e.g., audio, video) and is unfortunately incompatible with IIIF viewers.');
        $sequence['compatibilityHint'] = 'displayIfContentUnsupported';

        $canvas = $this->_iiifCanvasPlaceholder();

        $canvases = array();
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
     * @param Resource $resource
     * @param boolean $isThreejs Manage an exception.
     * @return object The iiif thumbnail.
     */
    protected function _mainThumbnail($resource, $isThreejs)
    {
        $media = null;
        // Threejs is an exception, because the thumbnail may be a true file
        // named "thumb.js".
        if ($isThreejs) {
            // The connection is used because the api does not allow to search
            // on source name.
            $conn = @$this->getView()->getHelperPluginManager()->getServiceLocator()
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
            // TODO Use index of the true Omeka representative file.
            $response = $this->view->api()->search(
                'media',
                [
                    'item_id' => $resource->id(),
                    'has_thumbnails' => 1,
                    'limit' => 1,
                ]
                );
            $medias = $response->getContent();
            $media = reset($medias);
        }

        if ($media) {
            return $this->_iiifThumbnail($media);
        }
    }

    /**
     * Create an IIIF tile object for a place holder.
     *
     * @internal The method uses the Zoomify format of OpenLayersZoom.
     *
     * @param MediaRepresentation $media
     * @return Standard object or null if no tile.
     * @see UniversalViewer_View_Helper_IiifInfo::_iiifTile()
     */
    protected function _iiifTile(MediaRepresentation $media)
    {
        $tile = array();

        $tileProperties = $this->_getTileProperties($media);
        if (empty($tileProperties)) {
            return;
        }

        $squaleFactors = array();
        $maxSize = max($tileProperties['source']['width'], $tileProperties['source']['height']);
        $tileSize = $tileProperties['size'];
        $total = (integer) ceil($maxSize / $tileSize);
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
        $tile = (object) $tile;
        return $tile;
    }

    /**
     * Return the properties of a tiled file.
     *
     * @param MediaRepresentation $media
     * @return array|null
     * @see UniversalViewer_ImageController::_getTileProperties()
     */
    protected function _getTileProperties(MediaRepresentation $media)
    {
        $olz = new OpenLayersZoom_Creator();
        $dirpath = $olz->useIIPImageServer()
            ? $olz->getZDataWeb($media)
            : $olz->getZDataDir($media);
        $properties = simplexml_load_file($dirpath . '/ImageProperties.xml', 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_PARSEHUGE);
        if ($properties === false) {
            return;
        }
        $properties = $properties->attributes();
        $properties = reset($properties);

        // Standardize the properties.
        $result = array();
        $result['size'] = (integer) $properties['TILESIZE'];
        $result['total'] = (integer) $properties['NUMTILES'];
        $result['source']['width'] = (integer) $properties['WIDTH'];
        $result['source']['height'] = (integer) $properties['HEIGHT'];
        return $result;
    }

    /**
     * Get an array of the width and height of the image file.
     *
     * @param MediaRepresentation $media
     * @param string $imageType
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     *
     * @see UniversalViewer_View_Helper_IiifManifest::_getImageSize()
     * @see UniversalViewer_View_Helper_IiifInfo::_getImageSize()
     * @see UniversalViewer_ImageController::_getImageSize()
     * @todo Refactorize.
     */
    protected function _getImageSize(MediaRepresentation $media, $imageType = 'original')
    {
        // Check if this is an image.
        if (empty($media) || strpos($media->mediaType(), 'image/') !== 0) {
            return array(
                'width' => null,
                'height' => null,
            );
        }

        // The storage adapter should be checked for external storage.
        if ($imageType == 'original') {
            $storagePath = $this->fileManager->getStoragePath($imageType, $media->filename());
        } else {
            $basename = $this->fileManager->getBasename($media->filename());
            $storagePath = $this->fileManager->getStoragePath($imageType, $basename, FileManager::THUMBNAIL_EXTENSION);
        }
        $filepath = OMEKA_PATH . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->_getWidthAndHeight($filepath);

        if (empty($result['width']) || empty($result['height'])) {
            throw new Exception("Failed to get image resolution: $filepath");
        }

        return $result;
    }

    /**
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     * @see UniversalViewer_ImageController::_getWidthAndHeight()
     */
    protected function _getWidthAndHeight($filepath)
    {
        if (file_exists($filepath)) {
            list($width, $height, $type, $attr) = getimagesize($filepath);
            return array(
                'width' => $width,
                'height' => $height,
            );
        }

        return array(
            'width' => null,
            'height' => null,
        );
    }
}
