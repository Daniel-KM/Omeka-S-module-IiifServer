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

/**
 * @link https://iiif.io/api/presentation/3.0/#52-manifest
 */
class Manifest extends AbstractResourceType
{
    use TraitDescriptive;
    use TraitLinking;
    use TraitMediaInfo;
    use TraitStructuralAnnotations;
    use TraitStructuralStructures;
    use TraitTechnicalBehavior;
    use TraitTechnicalViewing;

    protected $type = 'Manifest';

    /**
     * Ordered list of properties associated with requirements for the type.
     *
     * @link https://iiif.io/api/presentation/3.0/#b-example-manifest-response
     *
     * @var array
     */
    protected $propertyRequirements = [
        '@context' => self::REQUIRED,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,

        // Descriptive and rights properties.
        'label' => self::REQUIRED,
        'metadata' => self::RECOMMENDED,
        'summary' => self::RECOMMENDED,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::OPTIONAL,
        'language' => self::NOT_ALLOWED,
        'provider' => self::RECOMMENDED,
        'thumbnail' => self::RECOMMENDED,
        'placeholderCanvas' => self::OPTIONAL,
        'accompanyingCanvas' => self::OPTIONAL,

        // Technical properties.
        // 'id' => self::REQUIRED,
        // 'type' => self::REQUIRED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        'height' => self::NOT_ALLOWED,
        'width' => self::NOT_ALLOWED,
        'duration' => self::NOT_ALLOWED,
        'viewingDirection' => self::OPTIONAL,
        'behavior' => self::OPTIONAL,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
        'homepage' => self::OPTIONAL,
        'logo' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::OPTIONAL,
        'supplementary' => self::NOT_ALLOWED,
        'services' => self::OPTIONAL,

        // Structural properties.
        'items' => self::REQUIRED,
        'structures' => self::OPTIONAL,
        'annotations' => self::OPTIONAL,
    ];

    protected $behaviors = [
        // Temporal behaviors.
        'auto-advance' => self::OPTIONAL,
        'no-auto-advance' => self::OPTIONAL,
        'repeat' => self::OPTIONAL,
        'no-repeat' => self::OPTIONAL,
        // Layout behaviors.
        'unordered' => self::OPTIONAL,
        'individuals' => self::OPTIONAL,
        'continuous' => self::OPTIONAL,
        'paged' => self::OPTIONAL,
        'facing-pages' => self::NOT_ALLOWED,
        'non-paged' => self::NOT_ALLOWED,
        // Collection behaviors.
        'multi-part' => self::NOT_ALLOWED,
        'together' => self::OPTIONAL,
        // Range behaviors.
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'no-nav' => self::NOT_ALLOWED,
        // Miscellaneous behaviors.
        'hidden' => self::NOT_ALLOWED,
    ];

    /**
     * @var array
     */
    protected $service = [];

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\RangeToArray
     */
    protected $rangeToArray;

    public function setResource(AbstractResourceEntityRepresentation $resource): self
    {
        parent::setResource($resource);

        $this
            ->prepareMediaInfoList()
            ->prepareExtraFilesInfoList()
        ;

        return $this;
    }

    public function id(): string
    {
        return $this->iiifUrl->__invoke($this->resource, 'iiifserver/manifest', '3');
    }

    /**
     * Unlike "services", "service" lists the services that applies only to the
     * current resource. The spec examples are limited to image service and to
     * an extension service. Search and autocompletion is used too by libraries.
     * Authentication services are used only as sub-servivces.
     *
     * The default list of services is:
     * ImageService1: Image API version 1
     * ImageService2: Image API version 2
     * SearchService1: Search API version 1
     * AutoCompleteService1: Search API version 1
     * AuthCookieService1: Authentication API version 1
     * AuthTokenService1: Authentication API version 1
     * AuthLogoutService1: Authentication API version 1
     *
     * @see https://iiif.io/api/presentation/3.0/#service
     */
    public function service(): array
    {
        return $this->service;
    }

    /**
     * In manifest, the rendering is used for media to be downloaded.
     */
    public function rendering(): array
    {
        $mediaTypes = $this->settings->get('iiifserver_manifest_rendering_media_types') ?: ['all'];
        if (in_array('none', $mediaTypes)) {
            return [];
        }

        $renderings = [];
        $siteSlug = $this->defaultSite ? $this->defaultSite->slug() : null;
        $allMediaTypes = in_array('all', $mediaTypes);
        foreach ($this->resource->media() as $media) {
            if (!$allMediaTypes && !in_array($media->mediaType(), $mediaTypes)) {
                continue;
            }
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo && $mediaInfo['on'] === 'Manifest') {
                $rendering = new Rendering();
                $rendering
                    // TODO Options should be set first for now for init, done in setResource().
                    ->setOptions([
                        'index' => $media->id(),
                        'siteSlug' => $siteSlug,
                        'content' => $mediaInfo['content'],
                        'on' => 'Manifest',
                    ])
                    ->setResource($media);
                if ($rendering->id() && $rendering->type()) {
                    $renderings[] = $rendering;
                }
            }
        }
        return $renderings;
    }

    /**
     * As the process converts Omeka resource, there is only one file by canvas
     * currently.
     *
     * Canvas Painting are always Image, Video, or Audio. Other files are Canvas
     * Annotation or Manifest Rendering, for example associated pdf to download.
     *
     * Currently, Canvas are determined by their content (image, video, audio).
     * Currently, there is only one file by canvas, so no supplementing, or
     * canvas rendering.
     *
     * @todo Manage multiple files by canvas for supplementing and rendering.
     * @todo Use the specific index (cover, etc.) instead of the index of painting.
     */
    public function items(): array
    {
        $items = [];
        // Don't loop media info directly.
        foreach ($this->resource->media() as $media) {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo && !empty($mediaInfo['painting'])) {
                $canvas = new Canvas();
                $canvas
                    // TODO Options should be set first for now for init, done in setResource().
                    ->setOptions([
                        'index' => $mediaInfo['index'] ?? null,
                        'content' => $mediaInfo['content'],
                        'key' => $mediaInfo['key'],
                        'motivation' => $mediaInfo['motivation'],
                        // The full media infos should be passed for SeeAlso and
                        // Annotations.
                        'mediaInfos' => [
                            'indexes' => array_column(array_filter($this->mediaInfos), 'index', 'id'),
                            'seeAlso' => array_filter($this->mediaInfos, fn ($v) => ($v['key'] ?? null) === 'seeAlso'),
                            'annotation' => array_filter($this->mediaInfos, fn ($v) => $v['relatedMediaOcr'] ?? false),
                            'extraFiles' => [
                                'alto' => $this->extraFiles['alto'][$this->resource->id()] ?? null,
                            ],
                        ],
                    ])
                    ->setResource($media);
                $items[] = $canvas;
            }
        }
        return $items;
    }
}
