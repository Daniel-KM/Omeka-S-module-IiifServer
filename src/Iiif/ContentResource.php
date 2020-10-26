<?php declare(strict_types=1);

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

use Omeka\Api\Representation\MediaRepresentation;

/**
 *@link https://iiif.io/api/presentation/3.0/#57-content-resources
 *
 * There are "specific resources" too.
 */
class ContentResource extends AbstractResourceType
{
    use TraitIiifType;
    use TraitMedia;
    use TraitThumbnail;

    /**
     * This is not the real type and must be set more precisely.
     *
     * @var string
     */
    protected $type = null;

    protected $keys = [
        '@context' => self::NOT_ALLOWED,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,

        // Descriptive and rights properties.
        'label' => self::OPTIONAL,
        'metadata' => self::OPTIONAL,
        'summary' => self::OPTIONAL,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::NOT_ALLOWED,
        'language' => self::RECOMMENDED,
        'provider' => self::OPTIONAL,
        'thumbnail' => self::OPTIONAL,
        'placeholderCanvas' => self::NOT_ALLOWED,
        'accompanyingCanvas' => self::NOT_ALLOWED,

        // Technical properties.
        // 'id' => self::REQUIRED,
        // 'type' => self::REQUIRED,
        'format' => self::OPTIONAL,
        'profile' => self::OPTIONAL,
        'height' => self::OPTIONAL,
        'width' => self::OPTIONAL,
        'duration' => self::OPTIONAL,
        'viewingDirection' => self::NOT_ALLOWED,
        'behavior' => self::OPTIONAL,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
        'homepage' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::NOT_ALLOWED,
        'supplementary' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::NOT_ALLOWED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::OPTIONAL,
    ];

    protected $behaviors = [
        'hidden' => self::OPTIONAL,
    ];

    public function __construct(MediaRepresentation $resource, array $options = null)
    {
        parent::__construct($resource, $options);
        $this->initIiifType();
        $this->initMedia();
        $this->initThumbnail();
        $this->prepareMediaId();
    }

    public function hasIdAndType()
    {
        return $this->id && $this->type;
    }

    public function getId()
    {
        if ($this->id) {
            return $this->id;
        }

        return $this->iiifUrl->__invoke($this->resource->item(), 'iiifserver/uri', '3', [
            'type' => 'content-resource',
            'name' => $this->resource->id(),
        ]);
    }

    /**
     * The label is not a title, but an info about the type, since the main
     * label is already known.
     *
     * {@inheritDoc}
     * @see \IiifServer\Iiif\AbstractResourceType::getLabel()
     */
    public function getLabel()
    {
        if (!$this->type) {
            return null;
        }

        $format = $this->getFormat();
        if (isset($this->mediaLabels[$format])) {
            $format = $this->mediaLabels[$format];
        }
        $label = $format
            ? sprintf('%1$s [%2$s]', $this->type, $format)
            : $this->type;
        return new ValueLanguage(['none' => $label]);
    }

    protected function prepareMediaId(): void
    {
        // FIXME Manage all media Omeka types (Iiif, youtube, etc.)..
        $ingester = $this->resource->ingester();
        if ($ingester === 'iiif') {
            $mediaData = $this->resource->mediaData();
            if (isset($mediaData['id'])) {
                $this->id = $mediaData['id'];
                return;
            } elseif (isset($mediaData['@id'])) {
                $this->id = $mediaData['@id'];
                return;
            }
        }

        $this->id = $this->resource->originalUrl();
        if (!$this->id) {
            $siteSlug = @$this->options['siteSlug'];
            if ($siteSlug) {
                // TODO Return media page or item page? Add an option or use content-resource url.
                $this->id = $this->resource->siteUrl($siteSlug, true);
            }
        }
    }
}
