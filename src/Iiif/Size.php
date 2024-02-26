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
 *@link https://iiif.io/api/image/3.0/#53-sizes
 */
class Size extends AbstractType
{
    use TraitImage;

    protected $type = 'Size';

    protected $propertyRequirements = [
        'type' => self::OPTIONAL,
        'width' => self::REQUIRED,
        'height' => self::REQUIRED,
    ];

    /**
     * @var \Omeka\Api\Representation\MediaRepresentation
     */
    protected $resource;

    /**
     * @var array
     */
    protected $options;

    public function __construct(MediaRepresentation $resource, array $options = null)
    {
        $this->resource = $resource;
        $this->options = $options ?: [];
        if (empty($this->options['image_type'])) {
            $this->options['image_type'] = 'original';
        }
        $this->initImage();
    }

    public function isImage(): bool
    {
        return true;
    }

    public function hasSize(): bool
    {
        $size = $this->imageSize($this->imageType());
        return !empty(array_filter($size));
    }

    public function height(): ?int
    {
        $size = $this->imageSize($this->imageType());
        return $size ? (int) $size['height'] : null;
    }

    public function width(): ?int
    {
        $size = $this->imageSize($this->imageType());
        return $size ? (int) $size['width'] : null;
    }

    protected function imageType(): ?string
    {
        return $this->options['image_type'];
    }
}
