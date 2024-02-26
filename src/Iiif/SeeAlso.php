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

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

/**
 *@link https://iiif.io/api/presentation/3.0/#seealso
 */
class SeeAlso extends AbstractResourceType
{
    use TraitIiifType;

    protected $type = null;

    protected $propertyRequirements = [
        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'label' => self::OPTIONAL,
        'format' => self::OPTIONAL,
        'profile' => self::OPTIONAL,
    ];

    protected $callingResource;

    protected $callingMotivation;

    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        parent::__construct($resource, $options);
        $this->callingResource = $options['callingResource'] ?? null;
        $this->callingMotivation = $options['callingMotivation'] ?? null;
        // For now, manage only media seeAlso.
        if ($resource instanceof MediaRepresentation) {
            $this->initIiifType();
            $this->initSeeAlso();
        }
    }

    public function id(): ?string
    {
        return $this->_storage['id'] ?? null;
    }

    /**
     * The label is not a title, but an info about the type, since the main
     * label is already known.
     *
     * {@inheritDoc}
     * @see \IiifServer\Iiif\AbstractResourceType::getLabel()
     */
    public function label(): ?ValueLanguage
    {
        return empty($this->_storage['label'])
            ? null
            : new ValueLanguage(['none' => [$this->_storage['label']]]);
    }

    /**
     * Get the media type of the resource.
     *
     * @todo Manage the format of non-file resources (iiif, oembed, etc.).
     */
    public function format(): ?string
    {
        return $this->_storage['format'] ?? null;
    }

    /**
     * Get the profile to use for linked resource.
     */
    public function profile(): ?string
    {
        return $this->_storage['profile'] ?? null;
    }

    /**
     * Prepare seeAlso.
     *
     * Only alto is managed for now.
     *
     * Here, the canvas contains a supported image/audio/video to be displayed,
     * no xml, pdf, etc. These other files can be attached to the displayable
     * media.
     *
     * There are two ways to make a relation between two media: use a property
     * with a linked media or use the same basename from the original source.
     *
     * @todo Merge with TraitLinking SeeAlso.
     */
    protected function initSeeAlso(): self
    {
        if (!$this->type || empty($this->callingResource)) {
            return $this;
        }

        $callingResourceId = $this->callingResource->id();
        if ($this->resource->id() === $callingResourceId) {
            return $this;
        }

        $callingResourceBasename = pathinfo((string) $this->callingResource->source(), PATHINFO_FILENAME);
        if (!$callingResourceBasename) {
            return $this;
        }

        $resourceBasename = pathinfo((string) $this->resource->source(), PATHINFO_FILENAME);
        if ($resourceBasename !== $callingResourceBasename) {
            return $this;
        }

        $mediaType = $this->resource->mediaType();
        if ($mediaType !== 'application/alto+xml') {
            return $this;
        }

        $this->type = 'Dataset';

        // TODO Manage other alto versions than v3 (should be stored in media for quick check).
        $this->_storage['id'] = $this->resource->originalUrl();
        $this->_storage['type'] = $this->type;
        $this->_storage['label'] = $this->mediaLabels[$mediaType] ?? $this->type;
        $this->_storage['format'] = $mediaType;
        $this->_storage['profile'] = 'http://www.loc.gov/standards/alto/v3/alto.xsd';

        return $this;
    }
}
