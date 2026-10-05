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

use Common\Stdlib\PsrMessage;
use IiifServer\Iiif\Exception\RuntimeException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

/**
 *@link https://iiif.io/api/presentation/3.0/#53-canvas
 */
class Canvas extends AbstractResourceType
{
    use TraitDescriptive;
    // TODO Use TraitMedia in Canvas?
    // use TraitMedia;
    // TODO Use TraitLinking for seeAlso.
    use TraitTechnicalBehavior;

    protected $type = 'Canvas';

    /**
     * @link https://iiif.io/api/presentation/3.0/#b-example-manifest-response
     *
     * @var array
     */
    protected $propertyRequirements = [
        '@context' => self::OPTIONAL,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,

        // Descriptive and rights properties.
        'label' => self::RECOMMENDED,
        'metadata' => self::OPTIONAL,
        'summary' => self::OPTIONAL,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::OPTIONAL,
        'language' => self::NOT_ALLOWED,
        'provider' => self::OPTIONAL,
        'thumbnail' => self::OPTIONAL,
        // Forbidden when current canvas is a placeholder canvas or an
        // accompaying canvas.
        'placeholderCanvas' => self::OPTIONAL,
        'accompanyingCanvas' => self::OPTIONAL,

        // Technical properties.
        // 'id' => self::REQUIRED,
        // 'type' => self::REQUIRED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        // Height and width should be set together.
        // Either size and/or duration are required.
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
        'items' => self::RECOMMENDED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::OPTIONAL,
    ];

    protected $behaviors = [
        // Temporal behaviors.
        'auto-advance' => self::OPTIONAL,
        'no-auto-advance' => self::OPTIONAL,
        'repeat' => self::NOT_ALLOWED,
        'no-repeat' => self::NOT_ALLOWED,
        // Layout behaviors.
        'unordered' => self::NOT_ALLOWED,
        'individuals' => self::NOT_ALLOWED,
        'continuous' => self::NOT_ALLOWED,
        'paged' => self::NOT_ALLOWED,
        'facing-pages' => self::OPTIONAL,
        'non-paged' => self::OPTIONAL,
        // Collection behaviors.
        'multi-part' => self::NOT_ALLOWED,
        'together' => self::NOT_ALLOWED,
        // Range behaviors.
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'no-nav' => self::NOT_ALLOWED,
        // Miscellaneous behaviors.
        'hidden' => self::NOT_ALLOWED,
    ];

    /**
     * This method is required, because the resource must be a media for now.
     *
     * Warning: setResource() should be called after setOptions() in order to manage init.
     * @todo Make init independant of order setResource() or setOptions().
     */
    public function setResource(AbstractResourceEntityRepresentation $resource): self
    {
        parent::setResource($resource);

        // Accept Media and DigitalObject (file-backed resources with a parent
        // item context).
        $isDo = class_exists('DigitalObject\Module', false)
            && $resource instanceof \DigitalObject\Api\Representation\DigitalObjectRepresentation;
        if (!$resource instanceof MediaRepresentation && !$isDo) {
            $message = new PsrMessage(
                'Resource #{resource_id}: A media is required to build a Canvas.', // @translate
                ['resource_id' => $resource->id()]
            );
            $this->logger->err($message->getMessage(), $message->getContext());
            throw new RuntimeException((string) $message);
        }

        // TODO Move this option management in setOptions().
        // TODO Add linking properties when not in manifest.
        // The option should contain an index, that is the position from 1 in
        // the list of canvases (available in storage too).
        $this->options['target_type'] = 'canvas';

        if (empty($this->options['target_name'])) {
            // Don't mix index and resource id.
            if (empty($this->options['index'])) {
                $this->options['target_name'] = 'c' . $this->resource->id();
            } else {
                $name = $this->options['index'];
                $this->options['target_name'] = (string) (int) $name === (string) $name
                    ? 'p' . $name
                    : $name;
            }
        }

        return $this;
    }

    public function id(): ?string
    {
        // TODO Use a specific value if any in the resource.
        // TODO Factorize with TraitLinking->start().
        // Here, the resource is a media.
        return $this->iiifUrl->__invoke($this->resource->item(), 'iiifserver/uri', '3', [
            'type' => 'canvas',
            'name' => $this->options['target_name'],
        ]);
    }

    public function label(): ?array
    {
        if (!empty($this->options['pdfPage'])) {
            return ['none' => [(string) $this->options['pdfPage']]];
        }

        $labelOption = $this->settings->get('iiifserver_manifest_canvas_label');
        $values = [];
        $fallback = (string) $this->options['index'];
        switch ($labelOption) {
            case 'property':
                $labelProperty = $this->settings->get('iiifserver_manifest_canvas_label_property');
                $values = $this->resource->value($labelProperty, ['all' => true, 'default' => $fallback]);
                break;
            case 'property_or_source':
                $labelProperty = $this->settings->get('iiifserver_manifest_canvas_label_property');
                $values = $this->resource->value($labelProperty, ['all' => true]);
                if (count($values)) {
                    break;
                }
                // no break;
            case 'source':
                $values = $this->resource->displayTitle($fallback);
                break;
            case 'template_or_source':
                $fallback = $this->resource->displayTitle($fallback);
                // no break;
            case 'template':
                // displayTitle() can't be used, because language is needed.
                $template = $this->resource->resourceTemplate();
                if ($template) {
                    $titleProperty = $template->titleProperty();
                    if ($titleProperty) {
                        $titlePropertyTerm = $titleProperty->term();
                        if ($titlePropertyTerm !== 'dcterms:title') {
                            $values = $this->resource->value($titlePropertyTerm, ['all' => true]);
                        }
                    }
                }
                if (empty($values)) {
                    $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => $fallback]);
                }
                break;
            case 'position':
            default:
                // Use fallback.
                break;
        }
        return ValueLanguage::output($values, false, $fallback);
    }

    /**
     * @todo Manage the provider at the canvas level.
     */
    public function provider(): ?array
    {
        return null;
    }

    public function height(): ?int
    {
        return $this->canvasDimensions()['height'];
    }

    public function width(): ?int
    {
        return $this->canvasDimensions()['width'];
    }

    public function duration(): ?float
    {
        return $this->canvasDimensions()['duration'];
    }

    public function seeAlso(): array
    {
        if (array_key_exists('seeAlso', $this->cache)) {
            return $this->cache['seeAlso'];
        }

        // OCR seeAlso is now produced exclusively by the IiifSearch module;
        // when it is not active the canvas exposes no OCR seeAlso. Non-OCR
        // seeAlso sources would plug in here in the future.
        $this->cache['seeAlso'] = [];
        if (!self::isMediaLikeResource($this->resource)
            || !$this->iiifSearchAnnotationUrl
            || !$this->iiifSearchAnnotationUrl->isActive()
        ) {
            return $this->cache['seeAlso'];
        }
        $item = $this->resource->item();
        if (!$this->iiifSearchAnnotationUrl->hasOcrSource($item)) {
            return $this->cache['seeAlso'];
        }
        $canvasIndex = (int) ($this->options['index'] ?? 1);
        // Do not advertise the OCR of a page that is not readable: the endpoint
        // of IiifSearch refuses it anyway, so the link would only add an error
        // in the viewer.
        if (!$this->isOcrExposed($item, $canvasIndex - 1)) {
            return $this->cache['seeAlso'];
        }
        $altoUrl = $this->iiifSearchAnnotationUrl->altoUrl($item, $canvasIndex);
        if ($altoUrl) {
            $this->cache['seeAlso'][] = [
                'id' => $altoUrl,
                'type' => 'Dataset',
                'format' => 'application/alto+xml',
                'profile' => 'http://www.loc.gov/standards/alto/',
            ];
        }
        return $this->cache['seeAlso'];
    }

    public function rendering(): array
    {
        if (array_key_exists('rendering', $this->cache)) {
            return $this->cache['rendering'];
        }

        $this->cache['rendering'] = [];
        if ($this->settings->get('iiifserver_manifest_rendering_skip')) {
            return $this->cache['rendering'];
        }
        if (isset($this->options['key']) && $this->options['key'] === 'rendering') {
            // When module Access is active, expose rendering only for resources
            // with status "free": rendering is a direct download link bypassing
            // image server access checks.
            if ($this->accessStatus) {
                $status = $this->accessStatus->__invoke($this->resource);
                if (!$status || $status->getLevel() !== \Access\Entity\AccessStatus::FREE) {
                    return $this->cache['rendering'];
                }
            }
            $rendering = new Rendering();
            // TODO Options should be set first for now for init, done in setResource().
            $rendering
                ->setOptions($this->options)
                ->setResource($this->resource);
            $this->cache['rendering'][] = $rendering;
        }
        return $this->cache['rendering'];
    }

    /**
     * As the process converts Omeka resource, there is only one file by canvas
     * currently.
     */
    public function items(): array
    {
        if (array_key_exists('items', $this->cache)) {
            return $this->cache['items'];
        }

        $this->cache['items'] = [];
        if (!empty($this->options['pdfPage'])) {
            $this->cache['items'][] = $this->pdfPageAnnotationPage();
            return $this->cache['items'];
        }
        if (isset($this->options['key']) && $this->options['key'] === 'annotation'
            && isset($this->options['motivation']) && $this->options['motivation'] === 'painting'
        ) {
            $opts = $this->options;
            $opts['callingResource'] = $this->resource;
            $opts['callingMotivation'] = 'painting';
            $item = new AnnotationPage();
            // TODO Options should be set first for now for init, done in setResource().
            $item
                ->setOptions($opts)
                ->setResource($this->resource);
            $this->cache['items'][] = $item;
        }
        return $this->cache['items'];
    }

    public function annotations(): array
    {
        if (array_key_exists('annotations', $this->cache)) {
            return $this->cache['annotations'];
        }

        // OCR annotations are now produced exclusively by the IiifSearch
        // module; when it is not active, the canvas exposes no inline OCR
        // annotations. Other annotation sources (Annotate module, etc.) would
        // plug in here in the future.
        $this->cache['annotations'] = [];
        if (!self::isMediaLikeResource($this->resource)
            || !$this->iiifSearchAnnotationUrl
            || !$this->iiifSearchAnnotationUrl->isActive()
        ) {
            return $this->cache['annotations'];
        }
        $item = $this->resource->item();
        if (!$this->iiifSearchAnnotationUrl->hasOcrSource($item)) {
            return $this->cache['annotations'];
        }
        $canvasIndex = (int) ($this->options['index'] ?? 1);
        // Do not advertise the OCR of a page that is not readable: the endpoint
        // of IiifSearch refuses it anyway, so the link would only add an error
        // in the viewer.
        if (!$this->isOcrExposed($item, $canvasIndex - 1)) {
            return $this->cache['annotations'];
        }
        $url = $this->iiifSearchAnnotationUrl->__invoke($item, $canvasIndex, 3);
        if ($url) {
            $this->cache['annotations'][] = [
                'id' => $url,
                'type' => 'AnnotationPage',
            ];
        }
        return $this->cache['annotations'];
    }

    /**
     * The canvas dimensions is the max size of any of its items.
     * @return array
     */
    protected function canvasDimensions(): array
    {
        if (array_key_exists('dimensions', $this->cache)) {
            return $this->cache['dimensions'];
        }

        $this->cache['dimensions'] = [];
        if (!empty($this->options['pdfPage'])) {
            $this->cache['dimensions'] = [
                'width' => $this->options['pdfPageSize']['width'] ?? null,
                'height' => $this->options['pdfPageSize']['height'] ?? null,
                'duration' => null,
            ];
            return $this->cache['dimensions'];
        }
        $heights = [0];
        $widths = [0];
        $durations = [0];
        /** @var \IiifServer\Iiif\AnnotationPage $item */
        foreach ($this->items() as $item) {
            /** @var \IiifServer\Iiif\Annotation $itemItem */
            foreach ($item->items() as $itemItem) {
                if ($itemItem->motivation() !== 'painting') {
                    continue;
                }
                $body = $itemItem->body();
                $heights[] = $body['height'] ?? 0;
                $widths[] = $body['width'] ?? 0;
                $durations[] = $body['duration'] ?? 0.0;
            }
        }
        $this->cache['dimensions']['height'] = max($heights) ?: null;
        $this->cache['dimensions']['width'] = max($widths) ?: null;
        $this->cache['dimensions']['duration'] = max($durations) ?: null;

        /* // TODO Required dimensions of canvas. It should work with pdf and UV.
        // The canvas must have a size or a duration. It depends on the type
        // of the view.
        // Image / Video.
        if (empty($this->cache['dimensions']['duration'])) {
            $this->propertyRequirements['width'] = self::REQUIRED;
            $this->propertyRequirements['height'] = self::REQUIRED;
        }
        // Audio / Video.
        if (empty($this->cache['dimensions']['width'])) {
            $this->propertyRequirements['duration'] = self::REQUIRED;
        }
        */
        return $this->cache['dimensions'];
    }

    /**
     * Build the painting annotation page of a page of a pdf.
     *
     * The page is an image served by an image server like Cantaloupe, with the
     * size of the page in points for the canvas, the same unit than the ocr.
     */
    protected function pdfPageAnnotationPage(): array
    {
        $page = (int) $this->options['pdfPage'];
        $item = $this->resource->item();
        $name = $this->resource->id() . '-p' . $page;
        $version = (string) $this->settings->get('iiifserver_media_api_default_version', '3') === '2' ? '2' : '3';
        $serviceUrl = $this->services->get('ViewHelperManager')->get('iiifMediaUrl')
            ->__invoke($this->resource, 'imageserver/id', $version, ['page' => $page]);
        $service = $version === '3'
            ? [
                'id' => $serviceUrl,
                'type' => 'ImageService3',
                'profile' => 'level2',
            ]
            : [
                '@id' => $serviceUrl,
                '@type' => 'ImageService2',
                'profile' => 'http://iiif.io/api/image/2/level2.json',
            ];
        return [
            'id' => $this->iiifUrl->__invoke($item, 'iiifserver/uri', '3', [
                'type' => 'annotation-page',
                'name' => $name,
            ]),
            'type' => 'AnnotationPage',
            'items' => [[
                'id' => $this->iiifUrl->__invoke($item, 'iiifserver/uri', '3', [
                    'type' => 'annotation',
                    'name' => $name,
                ]),
                'type' => 'Annotation',
                'motivation' => 'painting',
                'body' => [
                    'id' => $serviceUrl . ($version === '3' ? '/full/max/0/default.jpg' : '/full/full/0/default.jpg'),
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                    'service' => [$service],
                ],
                'target' => $this->id(),
            ]],
        ];
    }

    /**
     * Check the access to the OCR of a page, with the rule of the module
     * IiifSearch: the OCR follows the access level of the file it belongs to,
     * and the image of the page when it is not an attached file. Without the
     * module Access or the module IiifSearch, there is no restriction.
     */
    /**
     * Should the ocr of the page be advertised in this manifest?
     *
     * The check cannot use the rights of the current user: the manifest is
     * cached and shared, so it would freeze the rights of the visitor who
     * happened to build it. Like the medias, the ocr is removed only from the
     * manifest built for the unauthorized visitors, according to the access
     * status of the resource that carries the ocr of the page.
     */
    protected function isOcrExposed(\Omeka\Api\Representation\ItemRepresentation $item, int $pageIndex): bool
    {
        if ($this->accessFilter !== self::ACCESS_FILTER_FREE || !$this->accessStatus) {
            return true;
        }

        $viewHelpers = $this->services->get('ViewHelperManager');
        if (!$viewHelpers->has('xmlAltoSplitter')) {
            return true;
        }

        // The ocr follows the access level of the resource it belongs to: the
        // attached alto when there is one, else the image of the page, else the
        // item itself.
        $resource = $viewHelpers->get('xmlAltoSplitter')->sourceResource($item, $pageIndex);
        if (!$resource) {
            $resource = self::isMediaLikeResource($this->resource) ? $this->resource : $item;
        }

        $status = $this->accessStatus->__invoke($resource);
        return !$status
            || $status->getLevel() === \Access\Entity\AccessStatus::FREE;
    }
}
