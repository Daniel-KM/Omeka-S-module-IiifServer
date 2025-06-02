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

namespace IiifServer\View\Helper;

use Access\Mvc\Controller\Plugin\IsAllowedMediaContent;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\MediaRepresentation;

class IiifMediaRelatedOcr extends AbstractHelper
{
    /**
     * @var \Access\Mvc\Controller\Plugin\IsAllowedMediaContent
     */
    protected $isAllowedMediaContent;

    /**
     * @var string
     */
    protected $matchXmlImage;

    /**
     * @var bool
     */
    protected $skipReservedXml;

    public function __construct(
        ?IsAllowedMediaContent $isAllowedMediaContent,
        string $matchXmlImage,
        bool $skipReservedXml
    ) {
        $this->isAllowedMediaContent = $isAllowedMediaContent;
        $this->matchXmlImage = $matchXmlImage;
        $this->skipReservedXml = $skipReservedXml;
    }

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
    public function __invoke(MediaRepresentation $media, ?int $indexOne = null): ?MediaRepresentation
    {
        static $relatedMedias = [];

        $mediaId = $media->id();
        if (array_key_exists($mediaId, $relatedMedias)) {
            return $relatedMedias[$mediaId];
        }

        if ($this->skipReservedXml
            && $this->isAllowedMediaContent
            && !$this->isAllowedMediaContent->__invoke($media)
        ) {
            $relatedMedias[$mediaId] = null;
            return null;
        }

        $relatedMedias[$mediaId] = $this->matchXmlImage === 'basename'
            ? $this->relatedMediaBasename($media)
            : $this->relatedMediaOrder($media, $indexOne);

        return $relatedMedias[$mediaId];
    }

    protected function relatedMediaOrder(MediaRepresentation $callingResource, ?int $indexOne): ?MediaRepresentation
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
            // TODO Check if an api query is quicker than a loop on all item media.
            foreach ($callingResource->item()->media() as $media) {
                $mediaType = $media->mediaType();
                if ($mediaType === 'application/alto+xml') {
                    $xmlAltoMedias[++$xmlIndex] = $media;
                } elseif (substr((string) $mediaType, 0, 6) === 'image/' || $media->ingester() === 'iiif') {
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
        // TODO First filter on media of the item by media type if it is quicker than a loop.
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
