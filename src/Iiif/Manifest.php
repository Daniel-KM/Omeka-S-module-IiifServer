<?php

/*
 * Copyright 2020 Daniel Berthereau
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

namespace IiifServer\Iiif;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

/**
 * @link https://iiif.io/api/presentation/3.0/#52-manifest
 */
class Manifest extends AbstractResourceType
{
    use TraitDescriptive;
    use TraitLinking;
    use TraitThumbnail;

    protected $type = 'Manifest';

    /**
     * @link https://iiif.io/api/presentation/3.0/#b-example-manifest-response
     *
     * @var array
     */
    protected $keys = [
        '@context' => self::REQUIRED,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,

        // Descriptive and rights properties.
        'label' => self::REQUIRED,
        'metadata' => self::RECOMMENDED,
        'summary' => self::RECOMMENDED,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::OPTIONAL,
        'language' => self::NOT_ALLOWED,
        'provider' => self::RECOMMENDED,
        'thumbnail' => self::RECOMMENDED,
        'placeholderCanvas' => self::OPTIONAL,
        'accompanyingCanvas' => self::OPTIONAL,

        // Technical properties.
        // 'id' => self::REQUIRED,
        // 'type' => self::REQUIRED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        'height' => self::NOT_ALLOWED,
        'width' => self::NOT_ALLOWED,
        'duration' => self::NOT_ALLOWED,
        'viewingDirection' => self::OPTIONAL,
        'behavior' => self::OPTIONAL,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
        'homepage' => self::OPTIONAL,
        'logo' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::OPTIONAL,
        'supplementary' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::REQUIRED,
        'structures' => self::OPTIONAL,
        'annotations' => self::OPTIONAL,
    ];

    protected $behaviors = [
        'auto-advance' => self::OPTIONAL,
        'continuous' => self::OPTIONAL,
        'facing-pages' => self::NOT_ALLOWED,
        'individuals' => self::OPTIONAL,
        'multi-part' => self::NOT_ALLOWED,
        'no-auto-advance' => self::OPTIONAL,
        'no-nav' => self::NOT_ALLOWED,
        'no-repeat' => self::OPTIONAL,
        'non-paged' => self::NOT_ALLOWED,
        'hidden' => self::NOT_ALLOWED,
        'paged' => self::OPTIONAL,
        'repeat' => self::OPTIONAL,
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'together' => self::OPTIONAL,
        'unordered' => self::OPTIONAL,
    ];

    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        parent::__construct($resource, $options);
        $this->initLinking();
        $this->initThumbnail();
    }

    public function getId()
    {
        $helper = $this->urlHelper;
        $url = $helper(
            'iiifserver/manifest',
            ['id' => $this->resource->id()],
            ['force_canonical' => true]
        );
        $helper = $this->iiifForceBaseUrlIfRequired;
        return $helper($url);
    }

    /**
     * As the process converts Omeka resource, there is only one file by canvas
     * currently.
     *
     * Canvas Painting are always Image, Video, or Audio. Other files are Canvas
     * Annotation or Manifest Rendering, for example associated pdf to download.
     *
     * Currently, Canvas are determined by their content (image, video, audio).
     * Currently, there is only one file by canvas, so no supplementing, or
     * canvas rendering.
     *
     * @todo Manage multiple files by canvas for supplementing and rendering.
     *
     * @return array
     */
    public function getItems()
    {
        $items = [];
        foreach ($this->resource->media() as $media) {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo['object'] === 'canvas') {
                $items[] = new Canvas($media, [
                    'index' => $media->id(),
                    'content' => $mediaInfo['content'],
                    'key' => isset($mediaInfo['key']) ? $mediaInfo['key'] : null,
                    'motivation' => isset($mediaInfo['motivation']) ? $mediaInfo['motivation'] : null,
                ]);
            }
        }
        return $items;
    }

    /**
     * In manifest, the rendering is used for media to be downloaded.
     *
     * @return array
     */
    public function getRendering()
    {
        $renderings = [];
        $site = $this->defaultSite();
        $siteSlug = $site ? $site->slug() : null;
        foreach ($this->resource->media() as $media) {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo['object'] === 'manifest') {
                $rendering = new Rendering($media, [
                    'index' => $media->id(),
                    'siteSlug' => $siteSlug,
                    'content' => $mediaInfo['content'],
                ]);
                if ($rendering->getId() && $rendering->getType()) {
                    $renderings[] = $rendering;
                }
            }
        }
        return $renderings;
    }

    /**
     * Get the iiif type according to the type of the media.
     *
     * @param MediaRepresentation $media
     * @return array|null An array containing media infos and the category, that
     * can be a canvas motivation painting or supplementing, or a canvas
     * rendering, or a manifest rendering.
     */
    protected function mediaInfo(MediaRepresentation $media)
    {
        if (!array_key_exists('media_info', $this->_storage)) {
            $this->_storage['media_info'] = $this->prepareMediaLists();
        }

        $mediaId = $media->id();
        return isset($this->_storage['media_info'][$mediaId])
            ? $this->_storage['media_info'][$mediaId]
            : null;
    }

    /**
     * Categorize media, so they will be include only once in manifest.
     *
     * For example if there is only one media and if it is a pdf, it will be set
     * as Canvas Supplementing, else if there is an image too, it will be set as
     * Rendering. Images are nearly always Canvas Painting.
     * - Canvas annotation painting: main media to display: image, video, audio.
     * - Canvas annotation supplementing: related to main media, like a
     *   transcription or a tei. Any other motivation can be used, except
     *   painting.
     * - Canvas renderings: non-iiif alternative designed to be rendered in the
     *   viewer, like pdf, ebook, slide deck, 3D model, to be rendered.
     * - Manifest rendering: non-iiif alternative, like pdf, ebook, slide deck,
     *   3D model, to be downloaded.
     *
     * @todo Better manage mixed painting in canvas, for example an image that is part a video. In such a case, the manifest is generally build manually, so it's not the purpose of this module currently.
     *
     * @return array
     */
    protected function prepareMediaLists()
    {
        // TODO Use ContentResources.
        // Note: hasThumbnails() is not only for images.

        $result = [];

        $canvasPaintings = [];
        $canvasSupplementings = [];
        $canvasRenderings = [];
        $manifestRenderings = [];

        // First loop to get the full list of types.
        $types = [
            // Painting.
            'Image' => [],
            'Video' => [],
            'Audio' => [],
            // Supplementing or Rendering.
            'Text' => [],
            'other' => [],
            'invalid' => [],
        ];

        $medias = $this->resource->media();
        foreach ($medias as $media) {
            $mediaId = $media->id();
            $result[$mediaId] = null;
            $contentResource = new ContentResource($media);
            if ($contentResource->hasIdAndType()) {
                $type = $contentResource->getType();
                if (in_array($type, ['Image', 'Video', 'Audio', 'Text'])) {
                    $types[$type][$mediaId] = [
                        'content' => $contentResource,
                    ];
                } else {
                    $types['other'][$mediaId] = [
                        'content' => $contentResource,
                    ];
                }
            } else {
                $types['invalid'][$mediaId] = [
                    'content' => $contentResource,
                ];
            }
        }
        unset($medias);

        // TODO Manage distinction between supplementing and rendering, mainly for text (transcription and/or pdf? Via linked properties?
        // TODO Manage 3D that may uses multiple files.

        // Canvas manages only image, audio and video: it requires size and/or
        // duration.
        // Priorities are Image, then Video, Audio, and Text.
        if ($types['Image']) {
            $canvasPaintings = $types['Image'];
            $manifestRenderings = $types['Video'] + $types['Audio'] + $types['Text'];
         } elseif ($types['Video']) {
            $canvasPaintings = $types['Video'];
            $manifestRenderings = $types['Audio'] + $types['Text'];
        } elseif ($types['Audio']) {
            $canvasPaintings = $types['Audio'];
            $manifestRenderings = $types['Text'];
        } elseif ($types['Text']) {
            // No painting.
            $canvasRenderings = $types['Text'];
        }

        // All other files are downloadable.
        $manifestRenderings += $types['other'];

        // Second loop to store the category.
        foreach (array_keys($result) as $mediaId) {
            if (isset($canvasPaintings[$mediaId])) {
                $result[$mediaId] = $canvasPaintings[$mediaId];
                $result[$mediaId]['object'] = 'canvas';
                $result[$mediaId]['key'] = 'annotation';
                $result[$mediaId]['motivation'] = 'painting';
            } elseif (isset($canvasSupplementings[$mediaId])) {
                $result[$mediaId] = $canvasSupplementings[$mediaId];
                $result[$mediaId]['object'] = 'canvas';
                $result[$mediaId]['key'] = 'annotation';
                $result[$mediaId]['motivation'] = 'supplementing';
            } elseif (isset($canvasRenderings[$mediaId])) {
                $result[$mediaId] = $canvasRenderings[$mediaId];
                $result[$mediaId]['object'] = 'canvas';
                $result[$mediaId]['key'] = 'rendering';
                $result[$mediaId]['motivation'] = null;
            } elseif (isset($manifestRenderings[$mediaId])) {
                $result[$mediaId] = $manifestRenderings[$mediaId];
                $result[$mediaId]['object'] = 'manifest';
                $result[$mediaId]['key'] = 'rendering';
                $result[$mediaId]['motivation'] = null;
            }
        }

        return $result;
    }
}
