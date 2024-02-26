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

use Common\Stdlib\PsrMessage;
use IiifServer\Iiif\Exception\RuntimeException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
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
     * @var string
     */
    protected $id = null;

    /**
     * This is not the real type and must be set more precisely.
     *
     * @var string
     */
    protected $type = null;

    protected $propertyRequirements = [
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
        'logo' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::NOT_ALLOWED,
        'supplementary' => self::NOT_ALLOWED,
        'services' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::NOT_ALLOWED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::OPTIONAL,
    ];

    protected $behaviors = [
        'hidden' => self::OPTIONAL,
    ];

    /**
     * @var \Omeka\Api\Representation\MediaRepresentation
     */
    protected $resource;

    public function setResource(AbstractResourceEntityRepresentation $resource): self
    {
        parent::setResource($resource);

        if (!$resource instanceof MediaRepresentation) {
            $message = new PsrMessage(
                'Resource #{resource_id}: A media is required to build a ContentResource.', // @translate
                ['resource_id' => $resource->id()]
            );
            $this->logger->err($message->getMessage(), $message->getContext());
            throw new RuntimeException((string) $message);
        }

        $this
            ->initIiifType()
            ->prepareMediaId();
        return $this;
    }

    public function hasIdAndType(): bool
    {
        return $this->id && $this->type;
    }

    public function id(): ?string
    {
        if ($this->id) {
            return $this->id;
        }

        // Here, the resource is a media.
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
     * @see \IiifServer\Iiif\AbstractResourceType::label()
     */
    public function label(): ?array
    {
        if (!$this->type) {
            return null;
        }

        $format = $this->format();
        if (isset($this->mediaLabels[$format])) {
            $format = $this->mediaLabels[$format];
        }
        $label = $format
            ? sprintf('%1$s [%2$s]', $this->type, $format)
            : $this->type;
        return ValueLanguage::output(['none' => [$label]]);
    }

    public function language(): array
    {
        $languages = [];

        $lang = $this->resource->lang();
        // FIXME Lang may be a code with encoding.
        if ($lang && strlen($lang) === 2) {
            $languages[] = $lang;
        }

        // TODO Use module Table for languages.
        foreach ($this->resource->value('dcterms:language', ['all' => true]) as $language) {
            $languageCode = (string) $language;
            if (strlen($languageCode) === 2) {
                $languages[] = $languageCode;
            }
        }

        return $languages;
    }

    protected function prepareMediaId(): self
    {
        // FIXME Manage all media Omeka types (Iiif, youtube, etc.)..
        $ingester = $this->resource->ingester();
        if ($ingester === 'iiif') {
            $mediaData = $this->resource->mediaData();
            if (isset($mediaData['id'])) {
                $this->id = $mediaData['id'];
                return $this;
            } elseif (isset($mediaData['@id'])) {
                $this->id = $mediaData['@id'];
                return $this;
            }
        }

        $this->id = $this->resource->originalUrl();
        if (!$this->id) {
            $siteSlug = $this->options['siteSlug'] ?? null;
            if ($siteSlug) {
                // TODO Return media page or item page? Add an option or use content-resource url.
                $this->id = $this->resource->siteUrl($siteSlug, true);
            }
        }

        return $this;
    }
}
