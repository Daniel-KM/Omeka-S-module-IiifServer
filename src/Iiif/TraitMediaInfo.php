<?php declare(strict_types=1);

/*
 * Copyright 2020-2024 Daniel Berthereau
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

use Omeka\Api\Representation\MediaRepresentation;

trait TraitMediaInfo
{
    /**
     * Get the iiif type according to the type of the media.
     *
     * It is not recommended to loop directly on media info: it can store media
     * that are not in the structure, for example a placeholderCanvas.
     * Media can be prepared for the resource or for a specific media.
     *
     * @param MediaRepresentation $media
     * @return array|null An array containing media infos and the category, that
     * can be a canvas motivation painting or supplementing, or a canvas
     * rendering, or a manifest rendering.
     */
    protected function mediaInfo(?MediaRepresentation $media): ?array
    {
        if ($media === null) {
            return null;
        }
        $mediaId = $media->id();
        if (!array_key_exists($mediaId, $this->_storage['media_info'] ?? [])) {
            $this->prepareMediaInfoSingle($media);
            return $this->_storage['media_info_single'][$mediaId];
        }
        return $this->_storage['media_info'][$mediaId];
    }

    /**
     * Get the iiif type according to the type of the media.
     *
     * This method is used for media outside manifest, for example a placeholderCanvas.
     */
    protected function mediaInfoSingle(?MediaRepresentation $media): ?array
    {
        if ($media === null) {
            return null;
        }
        $mediaId = $media->id();
        if (!array_key_exists($mediaId, $this->_storage['media_info_single'] ?? [])) {
            $this->prepareMediaInfoSingle($media);
        }
        return $this->_storage['media_info_single'][$mediaId];
    }

    /**
     * Categorize media to prepare and include them only once in manifest.
     *
     * For example if there is only one media and if it is a pdf, it will be set
     * as Canvas Supplementing, else if there is an image too, it will be set as
     * Rendering. Images are nearly always Canvas Painting.
     * - Canvas annotation painting: main media to display: image, video, audio.
     * - Canvas annotation supplementing: related to main media, like a
     *   transcription or a tei. Any other motivation can be used, except
     *   painting.
     * - Canvas renderings: non-iiif alternative designed to be rendered in the
     *   viewer, like pdf, ebook, slide deck, 3D model (Universal Viewer).
     * - Manifest rendering: non-iiif alternative designed to be downloaded,
     *   like pdf, ebook, slide deck (Mirador).
     *
     * @todo Better manage mixed painting in canvas, for example an image that is part a video. In such a case, the manifest is generally build manually, so it's not the purpose of this module currently.
     * @todo Manage media related to other (xml alto to its image).
     * @todo Better management of this list of medias, that should be available anywhere.
     *
     * @todo Merge with TraitMediaRelated.
     */
    private function prepareMediaInfoList(): self
    {
        // TODO Use ContentResources.
        // Note: hasThumbnails() is not only for images.

        if ($this->type !== 'Manifest') {
            return $this;
        }

        // TODO Store media-type too.

        // Since this method is called only by manifest, it should be empty.
        $this->_storage['media_info'] ??= [];

        $canvasPaintings = [];
        $canvasSupplementings = [];
        $canvasRenderings = [];
        $canvasSeeAlso = [];
        $manifestRenderings = [];

        // First loop to get the full list of types.
        $iiifTypes = [
            // Painting.
            'Image' => [],
            'Video' => [],
            'Sound' => [],
            // Supplementing or Rendering or SeeAlso.
            'Text' => [],
            'Dataset' => [],
            'Model' => [],
            'other' => [],
            'invalid' => [],
        ];

        $result = [];
        $mediaIds = [];
        $medias = $this->resource->media();
        foreach ($medias as $media) {
            $mediaId = $media->id();
            $mediaIds[] = $mediaId;
            $result[$mediaId] = null;
            $contentResource = new ContentResource($media);
            if ($contentResource->hasIdAndType()) {
                $iiifType = $contentResource->type();
                if (in_array($iiifType, ['Image', 'Video', 'Sound', 'Text', 'Model'])) {
                    $iiifTypes[$iiifType][$mediaId] = [
                        'content' => $contentResource,
                    ];
                } else {
                    $iiifTypes['other'][$mediaId] = [
                        'content' => $contentResource,
                    ];
                }
            } else {
                $iiifTypes['invalid'][$mediaId] = [
                    'content' => $contentResource,
                ];
            }
        }
        unset($medias);

        // TODO Manage distinction between supplementing and rendering, mainly for text (transcription and/or pdf? Via linked properties?
        // TODO Manage 3D that may uses multiple files.
        // TODO Manage pdf, that are a Text, but not displayable as iiif.

        // Canvas manages only image, audio and video: it requires size and/or
        // duration.
        // Priorities are Model, Image, then Video, Sound, and Text.
        // Model has prioritary because when an item is a model, there are
        // multiple files, including texture images, not to be displayed.
        if ($iiifTypes['Model']) {
            // TODO Same issue for Model than for Text?
            // $canvasRenderings = $iiifTypes['Model'];
            $canvasPaintings = $iiifTypes['Model'];
            $iiifTypes['Model'] = [];
            // When an item is a model, images are skipped.
            $iiifTypes['Image'] = [];
        } elseif ($iiifTypes['Image']) {
            $canvasPaintings = $iiifTypes['Image'];
            $iiifTypes['Image'] = [];
        } elseif ($iiifTypes['Video']) {
            $canvasPaintings = $iiifTypes['Video'];
            $iiifTypes['Video'] = [];
        } elseif ($iiifTypes['Sound']) {
            $canvasPaintings = $iiifTypes['Sound'];
            $iiifTypes['Sound'] = [];
        } elseif ($iiifTypes['Text']) {
            // For pdf and other texts, Iiif says no painting, but manifest
            // rendering, but UV doesn't display it. Mirador doesn't manage them
            // anyway.
            // TODO The solution is to manage pdf as a list of images via the image server! And to make it type Image? And to add textual content.
            $canvasPaintings = $iiifTypes['Text'];
            // $canvasRenderings = $iiifTypes['Text'];
            // $manifestRendering = $iiifTypes['Text'];
            $iiifTypes['Text'] = [];
        }

        // All other files are downloadable.
        $manifestRenderings += array_replace($iiifTypes['Image'], $iiifTypes['Video'], $iiifTypes['Sound'],
            $iiifTypes['Text'], $iiifTypes['Dataset'], $iiifTypes['Model'], $iiifTypes['other']);

        // TODO Manage dataset cleanerly.
        foreach ($iiifTypes['other'] as $mediaId => $iiifType) {
            $contentResource = $iiifType['content'];
            if ($contentResource->type() === 'Dataset'
                && $contentResource->format() === 'application/alto+xml'
            ) {
                unset($iiifTypes['other'][$mediaId]);
                unset($manifestRenderings[$mediaId]);
                $canvasSeeAlso[$mediaId]['content'] = $contentResource;
            }
        }

        // Second loop to store the category.
        foreach (array_keys($result) as $mediaId) {
            if (isset($canvasPaintings[$mediaId])) {
                $result[$mediaId] = $canvasPaintings[$mediaId];
                $result[$mediaId]['on'] = 'Canvas';
                $result[$mediaId]['key'] = 'annotation';
                $result[$mediaId]['motivation'] = 'painting';
            } elseif (isset($canvasSupplementings[$mediaId])) {
                $result[$mediaId] = $canvasSupplementings[$mediaId];
                $result[$mediaId]['on'] = 'Canvas';
                $result[$mediaId]['key'] = 'annotation';
                $result[$mediaId]['motivation'] = 'supplementing';
            } elseif (isset($canvasRenderings[$mediaId])) {
                $result[$mediaId] = $canvasRenderings[$mediaId];
                $result[$mediaId]['on'] = 'Canvas';
                $result[$mediaId]['key'] = 'rendering';
                $result[$mediaId]['motivation'] = null;
            } elseif (isset($canvasSeeAlso[$mediaId])) {
                $result[$mediaId] = $canvasSeeAlso[$mediaId];
                $result[$mediaId]['on'] = 'Canvas';
                $result[$mediaId]['key'] = 'seeAlso';
                $result[$mediaId]['motivation'] = null;
            } elseif (isset($manifestRenderings[$mediaId])) {
                $result[$mediaId] = $manifestRenderings[$mediaId];
                $result[$mediaId]['on'] = 'Manifest';
                $result[$mediaId]['key'] = 'rendering';
                $result[$mediaId]['motivation'] = null;
            }
        }

        // Prepare mapping between media and canvas index one time and store it.
        $index = 0;
        foreach ($mediaIds as $mediaId) {
            $mediaInfo = $result[$mediaId];
            if ($mediaInfo
                && $mediaInfo['on'] === 'Canvas'
                && ($mediaInfo['key'] ?? null) === 'annotation'
            ) {
                $result[$mediaId]['index'] = ++$index;
            }
        }

        $this->_storage['media_info'] += $result;

        return $this;
    }

    /**
     * Prepare a single media info.
     */
    private function prepareMediaInfoSingle(MediaRepresentation $media): self
    {
        $mediaId = $media->id();

        $this->_storage['media_info_single'] ??= [];

        $contentResource = new ContentResource($media);
        if ($contentResource->hasIdAndType()) {
            $iiifType = $contentResource->type();
            $this->_storage['media_info_single'][$mediaId]['content'] = $contentResource;
            $this->_storage['media_info_single'][$mediaId]['on'] = 'Canvas';
            if (in_array($iiifType, ['Image', 'Video', 'Sound', 'Text', 'Model'])) {
                $this->_storage['media_info_single'][$mediaId]['key'] = 'annotation';
                $this->_storage['media_info_single'][$mediaId]['motivation'] = 'painting';
            } else {
                $this->_storage['media_info_single'][$mediaId]['key'] = null;
                $this->_storage['media_info_single'][$mediaId]['motivation'] = null;
            }
        } else {
            // Cannot be a canvas.
            $this->_storage['media_info_single'][$mediaId] = null;
        }

        return $this;
    }
}
