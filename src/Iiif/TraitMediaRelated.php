<?php declare(strict_types=1);

/*
 * Copyright 2020-2023 Daniel Berthereau
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

/**
 * @todo Merge with TraitMediaInfo.
 */
trait TraitMediaRelated
{
    /**
     * Get the related media according to the setting (order or name).
     *
     * @todo Factorize IiifAnnotationPageLine2, IiifManifest2 and AnnotationPage.
     *
     * @see \IiifServer\Iiif\AnnotationPage::initAnnotationPage()
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine2
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine3
     * @see \IiifServer\View\Helper\IiifManifest2::otherContent()
     * @see \IiifServer\View\Helper\IiifManifest2::relatedMediaOcr()
     */
    protected function relatedMediaOcr(MediaRepresentation $media, $indexOne): ?MediaRepresentation
    {
        static $relatedMedias = [];
        static $matchXmlImage;
        /** @var \Access\Mvc\Controller\Plugin\IsAllowedMediaContent $isAllowedMediaContent */
        static $isAllowedMediaContent;

        $mediaId = $media->id();
        if (array_key_exists($mediaId, $relatedMedias)) {
            return $relatedMedias[$mediaId];
        }

        if ($matchXmlImage === null) {
            $services = $media->getServiceLocator();
            $settings = $this->settings ?? $services->get('Omeka\Settings');
            $matchXmlImage = $settings->get('iiifserver_xml_image_match', 'order');
            $skipReservedXml = (bool) $settings->get('iiifserver_access_ocr_skip');
            if ($skipReservedXml) {
                $plugins = $services->get('ControllerPluginManager');
                $isAllowedMediaContent = $plugins->has('isAllowedMediaContent') ? $plugins->get('isAllowedMediaContent') : null;
            }
        }

        if ($isAllowedMediaContent && !$isAllowedMediaContent($media)) {
            $relatedMedias[$mediaId] = null;
            return null;
        }

        if ($matchXmlImage === 'basename') {
            $relatedMedias[$mediaId] = $this->relatedMediaBasename($media);
        } else {
            $relatedMedias[$mediaId] = $this->relatedMediaOrder($media, $indexOne);
        }

        return $relatedMedias[$mediaId];
    }

    protected function relatedMediaOrder(MediaRepresentation $callingResource, $indexOne): ?MediaRepresentation
    {
        static $xmlAltoMedias;
        static $imageMediasIndexes;

        // The index is required to get the media by order.
        if (!is_array($xmlAltoMedias)) {
            // Should be one based.
            $imageMediasIndexes = [];
            $xmlAltoMedias = [];
            $imageIndex = 0;
            $xmlIndex = 0;
            foreach ($callingResource->item()->media() as $media) {
                $mediaType = $media->mediaType();
                if ($mediaType === 'application/alto+xml') {
                    $xmlAltoMedias[++$xmlIndex] = $media;
                } elseif (substr((string) $mediaType, 0, 6) === 'image/') {
                    $imageMediasIndexes[$media->id()] = ++$imageIndex;
                }
            }
        }

        $callingResourceId = $callingResource->id();

        if (!$indexOne) {
            if (empty($imageMediasIndexes[$callingResourceId])) {
                return null;
            }
            $indexOne = $imageMediasIndexes[$callingResourceId];
        }

        return $xmlAltoMedias[$indexOne] ?? null;
    }

    protected function relatedMediaBasename(MediaRepresentation $callingResource): ?MediaRepresentation
    {
        $callingResourceId = $callingResource->id();
        $callingResourceBasename = pathinfo((string) $callingResource->source(), PATHINFO_FILENAME);
        if (!strlen((string) $callingResourceBasename)) {
            return null;
        }

        // Get the ocr for each image.
        $relatedMedia = null;
        foreach ($callingResource->item()->media() as $rMedia) {
            if ($rMedia->id() === $callingResourceId) {
                continue;
            }
            $resourceBasename = pathinfo((string) $rMedia->source(), PATHINFO_FILENAME);
            if ($resourceBasename !== $callingResourceBasename) {
                continue;
            }
            $mediaType = $rMedia->mediaType();
            if ($mediaType !== 'application/alto+xml') {
                continue;
            }
            $relatedMedia = $rMedia;
            break;
        }

        return $relatedMedia;
    }
}
