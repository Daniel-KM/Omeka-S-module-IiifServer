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
 *@link https://iiif.io/api/presentation/3.0/#rendering
 */
class Rendering extends AbstractResourceType
{
    use TraitIiifType;

    protected $type = null;

    protected $propertyRequirements = [
        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'label' => self::OPTIONAL,
        'format' => self::OPTIONAL,
    ];

    public function setResource(AbstractResourceEntityRepresentation $resource): self
    {
        // The options should be set first.

        parent::setResource($resource);

        if (!$resource instanceof MediaRepresentation) {
            $message = new PsrMessage(
                'Resource #{resource_id}: A media is required to build a Rendering.', // @translate
                ['resource_id' => $resource->id()]
            );
            $this->logger->err($message->getMessage(), $message->getContext());
            throw new RuntimeException((string) $message);
        }

        $this->type = $this->IiifTypeOfMedia->__invoke($resource);

        return $this;
    }

    public function id(): ?string
    {
        if (array_key_exists('id', $this->cache)) {
            return $this->cache['id'];
        }

        // FIXME Manage all media Omeka types (Iiif, youtube, etc.)..
        $url = $this->resource->originalUrl();
        if ($url) {
            $id = $url;
        } else {
            $siteSlug = $this->options['siteSlug'] ?? null;
            if ($siteSlug) {
                // TODO Return media page or item page? Add an option.
                // To get the site url from resource is slow.
                // $id = $this->resource->siteUrl($siteSlug, true);
                $id = $this->urlHelper->__invoke('site/resource-id', ['site-slug' => $siteSlug, 'controller' => $this->resource->getControllerName(), 'action' => 'show', 'id' => $this->resource->id()], ['force_canonical' => true]);
            } else {
                $id = null;
            }
        }
        $this->cache['id'] = $id;

        return $this->cache['id'];
    }

    /**
     * The label is not a title, but an info about the type, since the main
     * label is already known.
     *
     * {@inheritDoc}
     * @see \IiifServer\Iiif\AbstractResourceType::getLabel()
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

    /**
     * Get the media type of the resource.
     *
     * @todo Manage the format of non-file resources (iiif, oembed, etc.).
     */
    public function format(): ?string
    {
        $mediaType = $this->resource->mediaType();
        if ($mediaType) {
            return $mediaType;
        }
        return null;
    }
}
