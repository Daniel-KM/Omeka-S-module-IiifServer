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

trait TraitMedia
{
    /**
     * @var \IiifServer\View\Helper\MediaDimension
     */
    protected $mediaDimensionHelper;

    /**
     * @var \IiifServer\View\Helper\ImageSize
     */
    protected $imageSizeHelper;

    /**
     * @var \IiifServer\View\Helper\IiifImageUrl
     */
    protected $iiifImageUrl;

    protected function initMedia()
    {
        $viewHelpers = $this->resource->getServiceLocator()->get('ViewHelperManager');
        $this->mediaDimensionHelper = $viewHelpers->get('mediaDimension');
        // It's quicker to use image size helper for images.
        $this->imageSizeHelper = $viewHelpers->get('imageSize');
    }

    public function isImage()
    {
        return $this->type === 'Image';
    }

    public function isAudioVideo()
    {
        return $this->type === 'Video' || $this->type === 'Audio';
    }

    public function isAudio()
    {
        return $this->type === 'Audio';
    }

    public function isVideo()
    {
        return $this->type === 'Video';
    }

    public function getHeight()
    {
        return $this->mediaSize()['height'];
    }

    public function getWidth()
    {
        return $this->mediaSize()['width'];
    }

    public function getDuration()
    {
        return $this->mediaDimension()['duration'];
    }

    protected function mediaSize()
    {
        $data = $this->mediaDimension();
        return [
            'width' => $data['width'],
            'height' => $data['height'],
        ];
    }

    protected function mediaDimension()
    {
        if (!array_key_exists('media_dimension', $this->_storage)) {
            if ($this->isAudioVideo()) {
                $helper = $this->mediaDimensionHelper;
                $this->_storage['media_dimension'] = $helper($this->resource->primaryMedia());
            } elseif ($this->isImage()) {
                $helper = $this->imageSizeHelper;
                $this->_storage['media_dimension'] = $helper($this->resource->primaryMedia());
                if ($this->_storage['media_dimension']) {
                    $this->_storage['media_dimension']['duration'] = null;
                } else {
                    $this->_storage['media_dimension'] = ['width' => null, 'height' => null, 'duration' => null];
                }
            } else {
                $this->_storage['media_dimension'] = ['width' => null, 'height' => null, 'duration' => null];
            }
        }
        return $this->_storage['media_dimension'];
    }
}
