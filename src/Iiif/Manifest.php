<?php declare(strict_types=1);

/*
 * Copyright 2020-2026 Daniel Berthereau
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
        if ($this->settings->get('iiifserver_manifest_rendering_skip')) {
            return [];
        }

        $mediaTypes = $this->settings->get('iiifserver_manifest_rendering_media_types') ?: ['all'];
        if (in_array('none', $mediaTypes)) {
            return [];
        }

        $renderings = [];
        $siteSlug = $this->defaultSite ? $this->defaultSite->slug() : null;
        $allMediaTypes = in_array('all', $mediaTypes);
        // Use mediaInfos directly: it preserves insertion order, already
        // excludes private media, and avoids rebuilding representations.
        foreach ($this->mediaInfos as $mediaInfo) {
            if (empty($mediaInfo) || ($mediaInfo['on'] ?? null) !== 'Manifest') {
                continue;
            }
            $media = $mediaInfo['resource'];
            if (!$allMediaTypes && !in_array($media->mediaType(), $mediaTypes)) {
                continue;
            }
            // When module Access is active, expose rendering only for resources
            // with status "free": rendering is a direct download link bypassing
            // image server access checks.
            if ($this->accessStatus) {
                $status = $this->accessStatus->__invoke($media);
                if (!$status || $status->getLevel() !== \Access\Entity\AccessStatus::FREE) {
                    continue;
                }
            }
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

        $zipRendering = $this->renderingZipDownload();
        if ($zipRendering) {
            $renderings[] = $zipRendering;
        }

        return $renderings;
    }

    /**
     * Build a manifest-level rendering entry pointing to the ZipDownload
     * endpoint so viewers can offer a "download all as zip" link.
     */
    protected function renderingZipDownload(): ?array
    {
        if (!$this->settings->get('iiifserver_manifest_rendering_zip')) {
            return null;
        }
        if (!$this->defaultSite) {
            return null;
        }
        $viewHelpers = $this->services->get('ViewHelperManager');
        if (!$viewHelpers->has('downloadZip')) {
            return null;
        }
        $enabled = (bool) $this->siteSettings->get('zipdownload_enabled', false, $this->defaultSite->id());
        if (!$enabled) {
            return null;
        }
        $resourceName = $this->resource->resourceName();
        if (!in_array($resourceName, ['items', 'media'])) {
            return null;
        }
        $url = $this->urlHelper->__invoke(
            'site/zip-download',
            [
                'site-slug' => $this->defaultSite->slug(),
                'resource-type' => $resourceName === 'media' ? 'media' : 'item',
                'resource-id' => $this->resource->id(),
            ],
            ['query' => ['content' => 'all', 'type' => 'original'], 'force_canonical' => true]
        );
        return [
            'id' => $url,
            'type' => 'Dataset',
            'format' => 'application/zip',
            'label' => ['none' => [
                $this->translator->translate('Download all files as zip'), // @translate
            ]],
        ];
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
        // The pages of a pdf may be displayed as images by the image server,
        // so a media may have multiple canvases, and the index is the one of
        // the canvas.
        $pdfPages = (bool) $this->settings->get('iiifserver_manifest_pdf_pages');
        $pdfPageSizes = $this->services->get('ViewHelperManager')->get('pdfPageSizes');
        $index = 0;
        // Use mediaInfos directly: it preserves insertion order, already
        // excludes private media, and avoids rebuilding representations.
        foreach ($this->mediaInfos as $mediaInfo) {
            if (empty($mediaInfo) || empty($mediaInfo['painting'])) {
                continue;
            }
            $media = $mediaInfo['resource'];
            $pages = $pdfPages
                && $mediaInfo['content']->format() === 'application/pdf'
                && !$this->isLockedByAccess($media)
                ? $pdfPageSizes($media)
                : [];
            foreach ($pages ?: [null] as $pageIndex => $pageSize) {
                $options = [
                    'accessFilter' => $this->accessFilter,
                    // An embedded resource has no "@context": it is required on
                    // the outermost object only, that is the manifest itself.
                    'skip' => ['@context' => true],
                    'index' => ++$index,
                    'content' => $mediaInfo['content'],
                    'key' => $mediaInfo['key'],
                    'motivation' => $mediaInfo['motivation'],
                    // The full media infos should be passed for SeeAlso and
                    // Annotations.
                    'mediaInfos' => [
                        'indexes' => array_column(array_filter($this->mediaInfos), 'index', 'id'),
                        'seeAlso' => array_filter($this->mediaInfos, fn ($v) => ($v['key'] ?? null) === 'seeAlso'),
                        'annotation' => [],
                        'extraFiles' => [
                            'alto' => $this->extraFiles['alto'][$this->resource->id()] ?? null,
                        ],
                    ],
                ];
                if ($pageSize) {
                    $options['pdfPage'] = $pageIndex + 1;
                    $options['pdfPageSize'] = $pageSize;
                }
                $canvas = new Canvas();
                // TODO Options should be set first for now for init, done in setResource().
                $canvas
                    ->setOptions($options)
                    ->setResource($media);
                $items[] = $canvas;
            }
        }

        // A manifest requires at least one canvas, but an item may have no
        // displayable file, for example a single pdf or an external link. So
        // add a placeholder canvas, like the manifest v2 does, else the
        // manifest is invalid and a viewer refuses to load it.
        return $items ?: [$this->placeholderCanvas()];
    }

    /**
     * Build the canvas used when the item has no displayable file.
     */
    protected function placeholderCanvas(): array
    {
        $viewHelpers = $this->services->get('ViewHelperManager');
        $serverUrl = $viewHelpers->get('serverUrl')->__invoke('');
        $basePath = $viewHelpers->get('basePath');
        $translate = $viewHelpers->get('translate');

        $canvasId = $serverUrl . $basePath('/iiif/ixif-message/canvas/c1');

        // Use the asset directly and not the route "mediaserver/placeholder":
        // when an external image server (Cantaloupe) is proxied on the path
        // "/iiif", it answers instead of Omeka and the image is not found.
        $assetUrl = $viewHelpers->get('assetUrl');
        $imageUrl = $serverUrl . $assetUrl('img/thumbnails/placeholder-image.png', 'ImageServer', true);

        // The size of the placeholder image of the module Image Server.
        $width = 200;
        $height = 200;

        return [
            'id' => $canvasId,
            'type' => 'Canvas',
            'label' => ['none' => [$translate('Placeholder image')]], // @translate
            'width' => $width,
            'height' => $height,
            'items' => [[
                'id' => $serverUrl . $basePath('/iiif/ixif-message/annotationpage/p1'),
                'type' => 'AnnotationPage',
                'items' => [[
                    'id' => $serverUrl . $basePath('/iiif/ixif-message/annotation/a1'),
                    'type' => 'Annotation',
                    'motivation' => 'painting',
                    'body' => [
                        'id' => $imageUrl,
                        'type' => 'Image',
                        'format' => 'image/png',
                        'width' => $width,
                        'height' => $height,
                    ],
                    'target' => $canvasId,
                ]],
            ]],
        ];
    }
}
