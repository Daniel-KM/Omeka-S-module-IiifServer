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

trait TraitImage
{
    /**
     * @var \IiifServer\View\Helper\ImageSize
     */
    protected $imageSizeHelper;

    /**
     * @var \IiifServer\View\Helper\IiifImageUrl
     */
    protected $iiifImageUrl;

    protected function initImage()
    {
        $viewHelpers = $this->resource->getServiceLocator()->get('ViewHelperManager');
        $this->imageSizeHelper = $viewHelpers->get('imageSize');
        $this->iiifImageUrl = $viewHelpers->get('iiifImageUrl');
    }

    public function isImage()
    {
        return $this->type === 'Image';
    }

    /**
     * @return int|null
     */
    public function getHeight()
    {
        $size = $this->imageSize();
        return $size ? $size['height'] : null;
    }

    /**
     * @return int|null
     */
    public function getWidth()
    {
        $size = $this->imageSize();
        return $size ? $size['width'] : null;
    }

    public function getThumbnail()
    {
        /** @var \Omeka\Api\Representation\AssetRepresentation $thumbnailAsset */
        $thumbnailAsset = $this->resource->thumbnail();
        $primaryMedia = $this->resource->primaryMedia();
        if (!$thumbnailAsset && !$primaryMedia) {
            return null;
        }

        $helper = $this->imageSizeHelper;
        if ($thumbnailAsset) {
            $image = $thumbnailAsset;
            $size = $helper($thumbnailAsset);
            // FIXME The image server doesn't know the resource id when it's an item. Neither the extension.
            $id = $primaryMedia ? $primaryMedia->id() : $this->resource->id();
        } else {
            $image = $primaryMedia;
            $size = $helper($primaryMedia, 'medium');
            $id = $primaryMedia->id();
        }

        if (empty($size)) {
            return null;
        }

        $helper = $this->iiifImageUrl;
        $imageUrl = $helper(
            'imageserver/media',
            [
                'id' => $id,
                'region' => 'full',
                'size' => $size['width'] . ',' . $size['height'],
                'rotation' => 0,
                'quality' => 'default',
                'format' => 'jpg',
            ]
        );

        $thumbnail = [
            'id' => $imageUrl,
            'type' => 'Image',
            'format' => $image->mediaType(),
            'width' => $size['width'],
            'height' => $size['height'],
        ];

        return [
            (object) $thumbnail,
        ];
    }

    protected function imageSize($type = 'original')
    {
        static $sizes = [];

        if (!$this->isImage()) {
            return null;
        }

        if (!array_key_exists($type, $sizes)) {
            $helper = $this->imageSizeHelper;
            $sizes[$type] = $helper($this->resource->primaryMedia(), $type) ?: null;
        }

        return $sizes[$type];
    }
}
