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

trait TraitThumbnail
{
    /**
     * @var int
     */
    protected $defaultHeight = 400;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSizeHelper;

    /**
     * @var \IiifServer\View\Helper\IiifImageUrl
     */
    protected $iiifImageUrl;

    protected function initThumbnail()
    {
        $services = $this->resource->getServiceLocator();
        $this->iiifImageUrl = $services->get('ViewHelperManager')->get('iiifImageUrl');
        $this->imageSizeHelper = $services->get('ControllerPluginManager')->get('imageSize');
    }

    public function getThumbnail()
    {
        // TODO Factorize as a standard image.
        $imageSize = $this->imageSizeHelper;

        /** @var \Omeka\Api\Representation\AssetRepresentation $thumbnailAsset */
        $thumbnailAsset = $this->resource->thumbnail();
        if ($thumbnailAsset) {
            $imageUrl = $thumbnailAsset->assetUrl();
            $size = $imageSize($thumbnailAsset);
            if ($size) {
                $thumbnail = [
                    'id' => $imageUrl,
                    'type' => 'Image',
                    'format' => $thumbnailAsset->mediaType(),
                    'width' => $size['width'],
                    'height' => $size['height'],
                ];
                return [(object) $thumbnail];
            }
        }

        $primaryMedia = $this->resource->primaryMedia();
        if (!$primaryMedia) {
            return null;
        }

        if ($primaryMedia->hasThumbnails()) {
            $imageUrl = $primaryMedia->thumbnailUrl('medium');
            $size = $imageSize($primaryMedia, 'medium');
            if ($size) {
                $thumbnail = [
                    'id' => $imageUrl,
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                    'width' => $size['width'],
                    'height' => $size['height'],
                ];
                return [(object) $thumbnail];
            }
        }

        // Manage external IIIF image.
        if ($primaryMedia->ingester() === 'iiif') {
            // The method "mediaData" contains data from the info.json file.
            $mediaData = $primaryMedia->mediaData();
            // Before 3.0, the "id" property was "@id".
            $imageBaseUri = isset($mediaData['id']) ? $mediaData['id'] : $mediaData['@id'];
            // In Image API 3.0, @context can be a list, https://iiif.io/api/image/3.0/#52-technical-properties.
            $imageApiContextUri = is_array($mediaData['@context']) ? array_pop($mediaData['@context']) : $mediaData['@context'];
            $imageComplianceLevelUri = is_array($mediaData['profile']) ? $mediaData['profile'][0] : $mediaData['profile'];
            $imageComplianceLevel = $this->_iiifComplianceLevel($mediaData['profile']);
            $imageUrl = $this->_iiifThumbnailUrl($imageBaseUri, $imageApiContextUri, $imageComplianceLevel);
            $thumbnailService = $this->_iiifImageService($imageBaseUri, $imageApiContextUri, $imageComplianceLevelUri);
            $thumbnail = [
                'id' => $imageUrl,
                'type' => 'Image',
                'format' => 'image/jpeg',
                'width' => $mediaData['width'] * $mediaData['height'] / $this->defaultHeight,
                'height' => $this->defaultHeight,
                'service' => $thumbnailService,
            ];
            return [(object) $thumbnail];
        }

        return null;
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
        $service['id'] = $baseUri;
        $service['profile'] = $complianceLevelUri;
        return (object) $service;
    }
}
