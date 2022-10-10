<?php declare(strict_types=1);

/*
 * Copyright 2020-2022 Daniel Berthereau
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
    use TraitThumbnail;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSize;

    /**
     * @var \IiifServer\View\Helper\IiifMediaUrl
     */
    protected $iiifMediaUrl;

    protected function initImage(): void
    {
        $services = $this->resource->getServiceLocator();
        $this->imageSize = $services->get('ControllerPluginManager')->get('imageSize');
        $this->iiifMediaUrl = $services->get('ViewHelperManager')->get('iiifMediaUrl');
    }

    public function isImage(): bool
    {
        return $this->type === 'Image';
    }

    public function width(): ?int
    {
        $size = $this->imageSize();
        return $size ? (int) $size['width'] : null;
    }

    public function height(): ?int
    {
        $size = $this->imageSize();
        return $size ? (int) $size['height'] : null;
    }

    protected function imageSize($type = 'original'): ?array
    {
        if (!$this->isImage()) {
            return null;
        }

        if (!array_key_exists('image_sizes', $this->_storage)) {
            $this->_storage['image_sizes'] = [];
        }

        if (!array_key_exists($type, $this->_storage['image_sizes'])) {
            $this->_storage['image_sizes'][$type] = $this->imageSize->__invoke($this->resource->primaryMedia(), $type);
        }

        return $this->_storage['image_sizes'][$type];
    }
}
