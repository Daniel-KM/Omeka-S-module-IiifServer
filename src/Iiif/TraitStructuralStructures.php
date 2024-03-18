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

/**
 * Iiif structures is possible only for Manifest
 *
 * @see https://iiif.io/api/presentation/3.0/#structures
 */
trait TraitStructuralStructures
{
    use TraitMediaInfo;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\RangeToArray
     */
    protected $rangeToArray;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var array
     */
    protected $paintingMedias;

    /**
     * In order to make old version of the plugin "download" of Mirador working,
     * a flat structure is created by default when none is set and when there
     * are more than one media.
     *
     * @return Range[]
     */
    public function structures(): array
    {
        // Count number of available medias.
        $total = 0;

        // Don't loop on media_info directly.
        foreach ($this->resource->media() as $media) {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo && $mediaInfo['motivation'] === 'painting') {
                ++$total;
            }
        }

        if (!$total) {
            return [];
        }

        $appendFlatStructure = !$this->settings->get('iiifserver_manifest_structures_skip_flat');
        $structureProperty = $this->settings->get('iiifserver_manifest_structures_property');

        if (!$structureProperty) {
            if ($appendFlatStructure && $total > 1) {
                $structure = $this->defaultStructure();
                return $structure ? [$structure] : [];
            }
            return [];
        }

        $stValues = $this->resource->value($structureProperty, ['all' => true]);
        if (!count($stValues)) {
            if ($appendFlatStructure && $total > 1) {
                $structure = $this->defaultStructure();
                return $structure ? [$structure] : [];
            }
            return [];
        }

        // TODO A structure requires a media for now to build reference to canvas.
        if (!$this->resource->primaryMedia()) {
            if ($appendFlatStructure && $total > 1) {
                $structure = $this->defaultStructure();
                return $structure ? [$structure] : [];
            }
            return [];
        }

        $structures = [];
        foreach ($stValues as $literalStructure) {
            // Check if a structure is stored as xml or toc code.
            // Json is currently unsupported.
            $literalStructure = (string) $literalStructure;
            $toc = $this->extractStructure($literalStructure);
            if (!empty($toc)) {
                $structure = $this->convertStructure($toc);
                if ($structure) {
                    $structures[] = $structure;
                }
            }
        }

        return $structures;
    }

    protected function defaultStructure(): ?Range
    {
        // Take iiif media in the order they are.
        $canvases = [];

        // Don't loop on media_info directly.
        foreach ($this->resource->media() as $media) {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo && $mediaInfo['motivation'] === 'painting') {
                $canvas = new ReferencedCanvas();
                $canvas
                    // TODO Options should be set first for now for init, done in setResource().
                    ->setOptions([
                        'index' => $mediaInfo['index'] ?? null,
                    ])
                    ->setResource($mediaInfo['content']->getResource());
                $canvases[] = $canvas;
            }
        }

        $range = new Range();
        $range
            ->setOptions([
                'index' => 'rstructure1',
                'label' => 'Content',
                'items' => $canvases,
                'skip' => ['@context' => true],
            ])
            ->setResource($this->resource);
        return $range;
    }

    /**
     * Prepare to convert a literal or xml structure into a range.
     *
     * For literal formats, there are two columns (label and view), three
     * columns (name, label and view) or four columns (name, label, views and
     * sub-sections).
     * For xml, the structure may be flat or nested.
     *
     * The previous process allowed more complex table of contents, with
     * unreferenced, duplicate or missing files.
     *
     * @see https://iiif.io/api/presentation/3.0/#54-range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     *
     * @see \IiifServer\View\Helper\IiifManifest2::extractStructure()
     */
    protected function extractStructure(string $literalStructure): ?array
    {
        if (mb_substr(trim($literalStructure), 0, 1) !== '<') {
            // Split by newline code, but don't filter empty lines in order to
            // keep range indexes in complex cases.
            $sourceLines = explode("\n", $literalStructure);
            // Check if this is the old format (three columns and mixed pages
            // numbers and ranges) or the new one.
            $matches = [];
            $lines2 = [];
            $lines3 = [];
            $lines4 = [];
            $total = 0;
            $empty = 0;
            foreach ($sourceLines as $lineIndex => $line) {
                ++$total;
                $line = trim($line);
                if (!$line) {
                    ++$empty;
                    continue;
                }
                if (preg_match('~^(?<label>.*)\s*,\s*(?<views>[\d\s;/-]*)$~u', $line, $matches)) {
                    $lines2[$lineIndex] = ['name' => null, 'label' => $matches['label'], 'views' => $matches['views'], 'ranges' => ''];
                }
                if (preg_match('~^(?<name>[^,]*)\s*,\s*(?<label>.*)\s*,\s*(?<views>[\d\s;/-]*)$~u', $line, $matches)) {
                    $lines3[$lineIndex] = ['name' => $matches['name'], 'label' => $matches['label'], 'views' => $matches['views'], 'ranges' => ''];
                }
                if (preg_match('~^(?<name>[^,]*?)\s*,\s*(?<label>.*?)\s*,\s*(?<views>[\d\s;/-]*)\s*,\s*(?<ranges>[^,]+)$~u', $line, $matches)) {
                    $lines4[$lineIndex] = ['name' => $matches['name'], 'label' => $matches['label'], 'views' => $matches['views'], 'ranges' => $matches['ranges']];
                }
            }
            if ($empty === $total) {
                return null;
            }
            return count($lines4) === ($total - $empty) ? $lines4 : (count($lines3) >= count($lines2) ? $lines3 : $lines2);
        }

        // Nested xml requires SimpleXML, because the sub-sections are not set
        // as attributes.
        $xml = simplexml_load_string($literalStructure);
        if (!$xml) {
            return null;
        }

        $xmlToArray = null;
        $xmlToArray = function($xml, &$lines) use (&$xmlToArray) {
            // Get ranges.
            $ranges = [];
            foreach ($xml->children() as $element) {
                if (isset($element['id'])) {
                    $ranges[] = (string) $element['id'];
                }
            }
            $lines[] = [
                'name' => isset($xml['id']) ? (string) $xml['id'] : '',
                'label' => isset($xml['label']) ? (string) $xml['label'] : '',
                'views' => isset($xml['views']) ? (string) $xml['views'] : '',
                'ranges' => $ranges,
            ];
            foreach ($xml->children() as $element) {
                if ($element->children()) {
                    $xmlToArray($element, $lines);
                }
            }
        };
        $lines = [];
        $xmlToArray($xml, $lines);
        return $lines;
    }

    /**
     * Convert a literal structure into a range (with view number).
     *
     * The lines are the preprocessed literal structure.
     *
     * @see https://iiif.io/api/presentation/3.0/#54-range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     *
     * @see \IiifServer\View\Helper\IiifManifest2::convertStructure()
     */
    protected function convertStructure(array $toc): ?Range
    {
        // Prepare indexes one time.
        $this->paintingMedias ??= array_column(array_filter($this->mediaInfos), 'id', 'painting');

        // Convert the literal value and prepare all the ranges.
        $ranges = [];
        foreach ($toc as $lineIndex => $lineData) {
            $name = !isset($lineData['name']) || strlen($lineData['name']) === 0 ? 'r' . ($lineIndex + 1) : $lineData['name'];
            $label = $lineData['label'] ?? '';
            // TODO Use view, that is the index of all viewed files, so like painting index.
            $views = empty($lineData['views'])
                ? []
                : (is_array($lineData['views']) ? $lineData['views'] : $this->rangeToArray->__invoke($lineData['views']));
            $mediaIds = [];
            foreach ($views as $view) {
                $mediaIds[] = $this->paintingMedias[$view] ?? null;
            }
            if (empty($lineData['ranges']) || $lineData['ranges'] === '-') {
                $children = [];
            } elseif (is_array($lineData['ranges'])) {
                $children = array_map('trim', $lineData['ranges']);
            } elseif (strpos($lineData['ranges'], '/')) {
                // Here the range should be r1-1/r1-8, so base is r1- and all
                // ranges from 1 to 8 are filled.
                $children = explode('/', $lineData['ranges']);
                $base = trim(substr($children[0], 0, strrpos($children[0], '-') + 1));
                $last = trim(substr($children[1], strrpos($children[1], '-') + 1));
                $children = [];
                for ($i = 1; $i <= $last; $i++) {
                    $children[] = $base . $i;
                }
            } else {
                $children = array_map('trim', explode(';', $lineData['ranges']));
            }
            // Some elements of structure can have a canvas to display.
            // Multiple ranges can have the same canevas, for example multiple
            // sections or paragraphs inside a page.
            $isRange = !empty($children) || empty($view);
            $isCanvas = !empty($mediaIds) && !empty($view);
            if (!$isRange && !$isCanvas) {
                continue;
            }
            // Unlike previous version, the line is kept even without children.
            // A line is always a range to display.
            $ranges[$name] = [
                'line' => $lineIndex + 1,
                'name' => $name,
                'label' => $label,
                // 'is_range' => $isRange,
                'is_canvas' => $isCanvas,
                'views' => $views,
                'ranges' => $children,
                // 'media_ids' => $mediaIds,
            ];
        }

        // If the values wasn't a formatted structure, there is no indexes.
        if (!count($ranges)) {
            return null;
        }

        // In a referenced canvas, the media is not used, but a media is
        // required to init the referenced canvases with the primary media.
        // TODO Or add "target_name" as option in referenced canvas.
        // TODO For canvases, the primary media should not be used, only the right media, even if it is useless for a reference.
        $primaryMedia = $this->resource->primaryMedia();

        // Prepare the list of canvases. This second step is needed because the
        // list of ranges should be complete to determine if an index is a
        // range or a canvas.
        // Furthermore, unlike previous process, in the current format, all
        // missing views (iiif canvases) should be added since they may not be
        // all in the coded toc.

        $referencedCanvases = [];
        foreach (array_keys($this->paintingMedias) as $index) {
            // TODO The type may be "SpecificResource" too (canvas part: fragment of an image, etc.).
            $referencedCanvas = new ReferencedCanvas();
            $referencedCanvas
                // TODO Options should be set first for now for init, done in setResource().
                ->setOptions([
                    'index' => $index,
                ])
                ->setResource($primaryMedia);
            $referencedCanvases[$index] = $referencedCanvas;
        }

        $isFlatToc = !array_filter(array_column($ranges, 'ranges', 'name'));
        if ($isFlatToc) {
            // Use the labels from the toc, not from the referenced canvases.
            $isFirst = true;
            $mainIiifItems = [];
            foreach ($ranges as $rangeData) {
                if ($isFirst) {
                    $isFirst = false;
                    continue;
                }
                if ($rangeData['label'] === null && $rangeData['label'] === '' || !$rangeData['views']) {
                    continue;
                }
                $iiifItems = array_intersect_key($referencedCanvases, array_flip($rangeData['views']));
                if (!$iiifItems) {
                    continue;
                }
                $range = new Range();
                $range
                    ->setOptions([
                        'index' => $rangeData['name'],
                        'label' => $rangeData['label'] === null || $rangeData['label'] === ''
                            ? sprintf($this->view->translate('[View %s]'), key($iiifItems)) // @translate
                            : $rangeData['label'],
                        'items' => $iiifItems,
                        'skip' => ['@context' => true],
                    ])
                    ->setResource($this->resource);
                $mainIiifItems[] = $range;
            }
            if (!$mainIiifItems) {
                return null;
            }

            reset($ranges);
            $topKey = key($ranges);
            $rangeData = $ranges[$topKey];

            $range = new Range();
            $range
                ->setOptions([
                    'index' => $rangeData['name'],
                    'label' => $rangeData['label'] === null || $rangeData['label'] === ''
                        ? $this->view->translate('[Content]') // @translate
                        : $rangeData['label'],
                    'items' => $mainIiifItems,
                    'skip' => ['@context' => true],
                ])
                ->setResource($this->resource);
            return $range;
        }

        // TODO Improve process to avoid recursive process (one loop and by-reference variables? or flat process).

        $buildStructure = null;
        $buildStructure = function (array $rangeData, array $ascendants = [])
            /** @return Range|ReferenceCanvas|array|null */
            use ($ranges, $referencedCanvases, &$buildStructure) {

            $iiifItems = [];

            // Check if the item is in ascendants to avoid an infinite loop.
            if (in_array($rangeData['name'], $ascendants)) {
                foreach ($rangeData['views'] as $view) {
                    if (isset($referencedCanvases[$view])) {
                        $iiifItems[] = $referencedCanvases[$view];
                    }
                }
                return $iiifItems;
            }

            // Append the current canvas, if it is a canvas.
            // Manage short toc with missing ranges.
            if ($rangeData['is_canvas']) {
                // Append all views when children are not present in ranges.
                if (array_intersect($rangeData['ranges'], array_keys($ranges))) {
                    $view = reset($rangeData['views']);
                    if (isset($referencedCanvases[$view])) {
                        $iiifItems[] = $referencedCanvases[$view];
                    }
                } else {
                    foreach ($rangeData['views'] as $view) {
                        if (isset($referencedCanvases[$view])) {
                            $iiifItems[] = $referencedCanvases[$view];
                        }
                    }
                }
            }

            // Create iiif items, that may be ranges or canvas.
            $ascendants[] = $rangeData['name'];
            foreach ($rangeData['ranges'] as $iiifItem) {
                if (isset($ranges[$iiifItem])) {
                    $iiifItem = $buildStructure($ranges[$iiifItem], $ascendants);
                    if ($iiifItem) {
                        if (is_array($iiifItem)) {
                            $iiifItems = array_merge(array_values($iiifItems), array_values($iiifItem));
                        } else {
                            $iiifItems[] = $iiifItem;
                        }
                    }
                }
            }

            // Don't create a range without canvas.
            if (!$iiifItems) {
                return isset($referencedCanvases[$rangeData['name']])
                    ? $referencedCanvases[$rangeData['name']]
                    : null;
            }

            $range = new Range();
            $range
                ->setOptions([
                    'index' => $rangeData['name'],
                    'label' => $rangeData['label'],
                    'items' => $iiifItems,
                    'skip' => ['@context' => true],
                ])
                ->setResource($this->resource);
            return $range;
        };

        // Use first line as root. Next lines are passed via "use".
        $rangeData = reset($ranges);
        $result = $buildStructure($rangeData);

        // Normally not possible.
        if ($result instanceof ReferencedCanvas) {
            $range = new Range();
            $range
                ->setOptions([
                    'index' => $rangeData['name'],
                    'label' => $rangeData['label'],
                    'items' => [$result],
                    'skip' => ['@context' => true],
                ])
                ->setResource($this->resource);
            return $range;
        }

        return $result;
    }
}
