<?php declare(strict_types=1);

/*
 * Copyright 2020-2021 Daniel Berthereau
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
 * @link https://iiif.io/api/presentation/3.0/#52-manifest
 */
class Manifest extends AbstractResourceType
{
    use TraitBehavior;
    use TraitDescriptive;
    use TraitLinking;
    use TraitThumbnail;
    use TraitViewing;

    protected $type = 'Manifest';

    /**
     * @link https://iiif.io/api/presentation/3.0/#b-example-manifest-response
     *
     * @var array
     */
    protected $keys = [
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
        'auto-advance' => self::OPTIONAL,
        'continuous' => self::OPTIONAL,
        'facing-pages' => self::NOT_ALLOWED,
        'individuals' => self::OPTIONAL,
        'multi-part' => self::NOT_ALLOWED,
        'no-auto-advance' => self::OPTIONAL,
        'no-nav' => self::NOT_ALLOWED,
        'no-repeat' => self::OPTIONAL,
        'non-paged' => self::NOT_ALLOWED,
        'hidden' => self::NOT_ALLOWED,
        'paged' => self::OPTIONAL,
        'repeat' => self::OPTIONAL,
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'together' => self::OPTIONAL,
        'unordered' => self::OPTIONAL,
    ];

    /**
     * @var array
     */
    protected $services = [];

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\RangeToArray
     */
    protected $rangeToArray;

    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        parent::__construct($resource, $options);

        $services = $this->resource->getServiceLocator();
        $this->rangeToArray = $services->get('ControllerPluginManager')->get('rangeToArray');

        $this->initLinking();
        $this->initThumbnail();
    }

    public function id(): string
    {
        return $this->iiifUrl->__invoke($this->resource, 'iiifserver/manifest', '3');
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
     */
    public function items(): array
    {
        $items = [];
        $index = 0;
        foreach ($this->resource->media() as $media) {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo && $mediaInfo['on'] === 'Canvas') {
                $items[] = new Canvas($media, [
                    'index' => ++$index,
                    'content' => $mediaInfo['content'],
                    'key' => $mediaInfo['key'] ?? null,
                    'motivation' => $mediaInfo['motivation'] ?? null,
                ]);
            }
        }
        return $items;
    }

    /**
     * @return Range[]
     */
    public function structures(): array
    {
        $settings = $this->resource->getServiceLocator()->get('ControllerPluginManager')->get('settings');
        $stProperty = $settings()->get('iiifserver_manifest_structures_property');
        if (!$stProperty) {
            return [];
        }

        $stValues = $this->resource->value($stProperty, ['type' => 'literal', 'all' => true]);
        if (!count($stValues)) {
            return [];
        }

        // TODO A structure requires a media for now to build reference to canvas..
        if (!$this->resource->primaryMedia()) {
            return [];
        }

        $structures = [];
        foreach ($stValues as $index => $literalStructure) {
            $structure = @json_decode((string) $literalStructure, true);
            if ($structure && is_array($structure)) {
                $firstRange = reset($structure);
                if (!is_array($firstRange)) {
                    continue;
                }
                $structure = isset($firstRange['@type'])
                    ? $this->convertToStructure3($structure)
                    // : $this->checkStructure($structure);
                    : $structure;
            } else {
                $structure = $this->extractStructure((string) $literalStructure, ++$index);
            }
            if (!empty($structure)) {
                $structures[] = $structure;
            }
        }

        return $structures;
    }

    /**
     * Convert a literal structure into a range.
     *
     * @see https://iiif.io/api/presentation/3.0/#54-range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     */
    protected function extractStructure(string $literalStructure, int $indexStructure): ?Range
    {
        $structure = [];
        $ranges = [];
        $rangesChildren = [];
        $canvases = [];

        // Convert the literal value and prepare all the ranges.

        // Split by newline code, but don't filter empty lines in order to
        // keep range indexes in complex cases.
        $lines = explode("\n", $literalStructure);
        $matches = [];
        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if (!$line || !preg_match('~^(?<name>[^,]*?)\s*,\s*(?<label>.*?)\s*,\s*(?<children>[^,]+?)$~u', $line, $matches)) {
                continue;
            }
            $name = strlen($matches['name']) === 0 ? 'r' . ($lineIndex + 1) : $matches['name'];
            $label = $matches['label'];
            $children = $this->rangeToArray->__invoke($matches['children'], 1, null, false, false, ';');
            if (!count($children)) {
                continue;
            }

            // A line is always a range to display.
            $ranges[$name] = $label;
            $rangesChildren[$name] = $children;
        }

        // If the values wasn't a formatted structure, there is no indexes.
        if (!count($ranges)) {
            return null;
        }

        // TODO For canvases, the primary media should not be used, only the right media, even if it is useless for a reference.

        // Prepare the list of canvases. This second step is needed because the
        // list of ranges should be complete to determine if an index is a
        // range or a canvas.
        foreach ($rangesChildren as $name => $itemNames) {
            foreach ($itemNames as $itemName) {
                $itemName = (string) $itemName;
                // Manage an exception: protected duplicate name for a canvas
                // and a range).
                $isProtected = mb_strlen($itemName) > 1 && mb_substr($itemName, 0, 1) === '"' && mb_substr($itemName, -1, 1) === '"';
                $cleanItemName = $isProtected
                    ? trim(mb_substr($itemName, 1, -1))
                    : $itemName;
                if ((isset($ranges[$cleanItemName]) && !$isProtected)
                    || isset($canvases[$cleanItemName])
                ) {
                    continue;
                }
                // This is not a full Canvas, just a reference, so skip label.
                $canvases[$itemName] = new ReferencedCanvas($this->resource->primaryMedia(), [
                    'index' => $cleanItemName,
                ]);
            }
        }

        // TODO Improve process to avoid recursive process (one loop and by-reference variables).

        $buildStructure = null;
        $buildStructure = function (array $itemNames, &$parentRange, array &$ascendants) use ($ranges, $canvases, $rangesChildren, &$buildStructure): void {
            foreach ($itemNames as $itemName) {
                if (isset($canvases[$itemName])) {
                    $parentRange['items'][] = $canvases[$itemName];
                    continue;
                }
                // TODO The type may be "SpecificResource" too (canvas part: fragment of an image, etc.).
                // The index is a range.
                // Check if the item is in ascendants to avoid an infinite loop.
                if (in_array($itemName, $ascendants)) {
                    $canvases[$itemName] = new ReferencedCanvas($this->resource->primaryMedia(), [
                        'index' => $itemName,
                    ]);
                    $parentRange['items'][] = $canvases[$itemName];
                    continue;
                }
                $ascendants[] = $itemName;
                $range = ['items' => []];
                $buildStructure($rangesChildren[$itemName], $range, $ascendants);
                $parentRange['items'][] = new Range($this->resource, [
                    'index' => $itemName,
                    'label' => $ranges[$itemName],
                    'items' => $range['items'],
                    'skip' => ['@context' => true],
                ]);
                array_pop($ascendants);
            }
        };

        // Unlike iiif v2, there can be only one root, so a range may be added
        // to wrap the structure.
        $allIndexes = array_fill_keys(array_merge(...array_values($rangesChildren)), true);
        $roots = array_keys(array_diff_key($ranges, $allIndexes));
        if (count($roots) === 1) {
            $rangesToBuild = $roots;
        } else {
            $rangesToBuild = count($roots)
                ? $roots
                // No loop means an infinite loop, that is managed directly.
                : array_keys($ranges);
        }

        $structure = ['items' => []];
        $ascendants = [];
        $buildStructure($rangesToBuild, $structure, $ascendants);

        // TODO Modify the process to create the root directly in buildStructure().
        if (count($roots) === 1) {
            $structure = reset($structure['items']);
        } else {
            $structure = new Range($this->resource, [
                'index' => 'rstructure' . $indexStructure,
                'label' => 'Content',
                'items' => $structure['items'],
                'skip' => ['@context' => true],
            ]);
        }

        return $structure;
    }

    /**
     * In manifest, the rendering is used for media to be downloaded.
     */
    public function rendering(): array
    {
        $renderings = [];
        $site = $this->defaultSite();
        $siteSlug = $site ? $site->slug() : null;
        foreach ($this->resource->media() as $media) {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo && $mediaInfo['on'] === 'Manifest') {
                $rendering = new Rendering($media, [
                    'index' => $media->id(),
                    'siteSlug' => $siteSlug,
                    'content' => $mediaInfo['content'],
                    'on' => 'Manifest',
                ]);
                if ($rendering->id() && $rendering->type()) {
                    $renderings[] = $rendering;
                }
            }
        }
        return $renderings;
    }

    public function services(): ?array
    {
        return $this->services;
    }

    public function appendService(Service $service): AbstractType
    {
        $this->services[] = $service;
        return $this;
    }

    /**
     * Get the iiif type according to the type of the media.
     *
     * @param MediaRepresentation $media
     * @return array|null An array containing media infos and the category, that
     * can be a canvas motivation painting or supplementing, or a canvas
     * rendering, or a manifest rendering.
     */
    protected function mediaInfo(MediaRepresentation $media): ?array
    {
        if (!array_key_exists('media_info', $this->_storage)) {
            $this->_storage['media_info'] = $this->prepareMediaLists();
        }
        return $this->_storage['media_info'][$media->id()] ?? null;
    }

    /**
     * Categorize media, so they will be include only once in manifest.
     *
     * For example if there is only one media and if it is a pdf, it will be set
     * as Canvas Supplementing, else if there is an image too, it will be set as
     * Rendering. Images are nearly always Canvas Painting.
     * - Canvas annotation painting: main media to display: image, video, audio.
     * - Canvas annotation supplementing: related to main media, like a
     *   transcription or a tei. Any other motivation can be used, except
     *   painting.
     * - Canvas renderings: non-iiif alternative designed to be rendered in the
     *   viewer, like pdf, ebook, slide deck, 3D model, to be rendered.
     * - Manifest rendering: non-iiif alternative, like pdf, ebook, slide deck,
     *   3D model, to be downloaded.
     *
     * @todo Better manage mixed painting in canvas, for example an image that is part a video. In such a case, the manifest is generally build manually, so it's not the purpose of this module currently.
     */
    protected function prepareMediaLists(): array
    {
        // TODO Use ContentResources.
        // Note: hasThumbnails() is not only for images.

        $result = [];

        $canvasPaintings = [];
        $canvasSupplementings = [];
        $canvasRenderings = [];
        $manifestRenderings = [];

        // First loop to get the full list of types.
        $iiifTypes = [
            // Painting.
            'Image' => [],
            'Video' => [],
            'Sound' => [],
            // Supplementing or Rendering (Universal Viewer only for now).
            'Text' => [],
            'Dataset' => [],
            'Model' => [],
            'other' => [],
            'invalid' => [],
        ];

        $medias = $this->resource->media();
        foreach ($medias as $media) {
            $mediaId = $media->id();
            $result[$mediaId] = null;
            $contentResource = new ContentResource($media);
            if ($contentResource->hasIdAndType()) {
                $iiifType = $contentResource->type();
                if (in_array($iiifType, ['Image', 'Video', 'Sound', 'Text', 'Model'])) {
                    $iiifTypes[$iiifType][$mediaId] = [
                        'content' => $contentResource,
                    ];
                } else {
                    $iiifTypes['other'][$mediaId] = [
                        'content' => $contentResource,
                    ];
                }
            } else {
                $iiifTypes['invalid'][$mediaId] = [
                    'content' => $contentResource,
                ];
            }
        }
        unset($medias);

        // TODO Manage distinction between supplementing and rendering, mainly for text (transcription and/or pdf? Via linked properties?
        // TODO Manage 3D that may uses multiple files.
        // TODO Manage pdf, that are a Text, but not displayable as iiif.

        // Canvas manages only image, audio and video: it requires size and/or
        // duration.
        // Priorities are Model, Image, then Video, Sound, and Text.
        // Model has prioritary because when an item is a model, there are
        // multiple files, including texture images, not to be displayed.
        if ($iiifTypes['Model']) {
            // TODO Same issue for Model than for Text?
            // $canvasRenderings = $iiifTypes['Model'];
            $canvasPaintings = $iiifTypes['Model'];
            $iiifTypes['Model'] = [];
            // When an item is a model, images are skipped.
            $iiifTypes['Image'] = [];
        } elseif ($iiifTypes['Image']) {
            $canvasPaintings = $iiifTypes['Image'];
            $iiifTypes['Image'] = [];
        } elseif ($iiifTypes['Video']) {
            $canvasPaintings = $iiifTypes['Video'];
            $iiifTypes['Video'] = [];
        } elseif ($iiifTypes['Sound']) {
            $canvasPaintings = $iiifTypes['Sound'];
            $iiifTypes['Sound'] = [];
        } elseif ($iiifTypes['Text']) {
            // For pdf and other texts, Iiif says no painting, but manifest
            // rendering, but UV doesn't display it. Mirador doesn't manage them
            // anyway.
            // TODO The solution is to manage pdf as a list of images via the image server! And to make it type Image? And to add textual content.
            $canvasPaintings = $iiifTypes['Text'];
            // $canvasRenderings = $iiifTypes['Text'];
            // $manifestRendering = $iiifTypes['Text'];
            $iiifTypes['Text'] = [];
        }

        // All other files are downloadable.
        $manifestRenderings += array_replace($iiifTypes['Image'], $iiifTypes['Video'], $iiifTypes['Sound'],
            $iiifTypes['Text'], $iiifTypes['Dataset'], $iiifTypes['Model'], $iiifTypes['other']);

        // Second loop to store the category.
        foreach (array_keys($result) as $mediaId) {
            if (isset($canvasPaintings[$mediaId])) {
                $result[$mediaId] = $canvasPaintings[$mediaId];
                $result[$mediaId]['on'] = 'Canvas';
                $result[$mediaId]['key'] = 'annotation';
                $result[$mediaId]['motivation'] = 'painting';
            } elseif (isset($canvasSupplementings[$mediaId])) {
                $result[$mediaId] = $canvasSupplementings[$mediaId];
                $result[$mediaId]['on'] = 'Canvas';
                $result[$mediaId]['key'] = 'annotation';
                $result[$mediaId]['motivation'] = 'supplementing';
            } elseif (isset($canvasRenderings[$mediaId])) {
                $result[$mediaId] = $canvasRenderings[$mediaId];
                $result[$mediaId]['on'] = 'Canvas';
                $result[$mediaId]['key'] = 'rendering';
                $result[$mediaId]['motivation'] = null;
            } elseif (isset($manifestRenderings[$mediaId])) {
                $result[$mediaId] = $manifestRenderings[$mediaId];
                $result[$mediaId]['on'] = 'Manifest';
                $result[$mediaId]['key'] = 'rendering';
                $result[$mediaId]['motivation'] = null;
            }
        }

        return $result;
    }

    /**
     * @todo Check a json structure for iiif v3.
     */
    protected function checkStructure(array $structure): ?Range
    {
        return null;
    }

    /**
     * @todo Convert a v2 structure into a v3 structure.
     */
    protected function convertToStructure3(array $structure): ?Range
    {
        return null;
    }
}
