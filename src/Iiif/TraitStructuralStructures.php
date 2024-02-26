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
        foreach ($stValues as $index => $literalStructure) {
            $literalStructure = (string) $literalStructure;
            $structure = @json_decode($literalStructure, true);
            if ($structure && is_array($structure)) {
                $firstRange = reset($structure);
                if (!is_array($firstRange)) {
                    continue;
                }
                $structure = isset($firstRange['@type'])
                    ? $this->convertToStructure3($structure)
                    : $this->checkStructure($structure);
            } else {
                $structure = $this->extractStructure($literalStructure, ++$index);
            }
            if (!empty($structure)) {
                $structures[] = $structure;
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
                    ->setOptions([])
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
     * Prepare to convert a json, literal or xml structure into a range.
     *
     * @see https://iiif.io/api/presentation/3.0/#54-range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     *
     * @see \IiifServer\View\Helper\IiifManifest2::extractStructure()
     */
    protected function extractStructure(string $literalStructure, int $indexStructure): ?Range
    {
        if (mb_substr(trim($literalStructure), 0, 1) !== '<') {
            return $this->extractStructureProcess($literalStructure, $indexStructure);
        }

        // Flat xml.
        // Nested xml.
        // TODO Manage nested xml toc with SimpleXml.
        // $isFlat = mb_strpos($literalStructure, '"/>') < mb_strpos($literalStructure, '<c ', 1);
        $matches = [];
        $lines = explode("\n", $literalStructure);
        foreach ($lines as &$line) {
            $line = trim($line);
            if ($line && $line !== '</c>') {
                preg_match('~\s*(<c\s*id="(?<id>[^"]*)")?\s*(label="(?<label>[^"]*)")?\s*(?:range="(?<range>[^"]*)"\s*/>)?~', $line, $matches);
                $line = $matches['id'] . ', ' . $matches['label'] . ', ' . $matches['range'];
            }
        }
        unset($line);
        return $this->extractStructureProcess(implode("\n", $lines), $indexStructure);
    }

    /**
     * Convert a literal structure into a range.
     *
     * @see https://iiif.io/api/presentation/3.0/#54-range
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-IiifServer#input-format-of-the-property-for-structures-table-of-contents
     */
    protected function extractStructureProcess(string $literalStructure, int $indexStructure): ?Range
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
                $referencedCanvas = new ReferencedCanvas();
                $referencedCanvas
                    // TODO Options should be set first for now for init, done in setResource().
                    ->setOptions([
                        'index' => $cleanItemName,
                    ])
                    ->setResource($this->resource->primaryMedia());
                $canvases[$itemName] = $referencedCanvas;
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
                    $referencedCanvas = new ReferencedCanvas();
                    $referencedCanvas
                        // TODO Options should be set first for now for init, done in setResource().
                        ->setOptions([
                            'index' => $itemName,
                        ])
                        ->setResource($this->resource->primaryMedia());
                    $canvases[$itemName] = $referencedCanvas;
                    $parentRange['items'][] = $referencedCanvas;
                    continue;
                }
                $ascendants[] = $itemName;
                $range = ['items' => []];
                $buildStructure($rangesChildren[$itemName], $range, $ascendants);
                $rangeResource = new Range();
                $rangeResource
                    ->setOptions([
                        'index' => $itemName,
                        'label' => $ranges[$itemName],
                        'items' => $range['items'],
                        'skip' => ['@context' => true],
                    ])
                    ->setResource($this->resource);
                $parentRange['items'][] = $rangeResource;
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
            $range = new Range();
            $range
                ->setOptions([
                    'index' => 'rstructure' . $indexStructure,
                    'label' => 'Content',
                    'items' => $structure['items'],
                    'skip' => ['@context' => true],
                ])
                ->setResource($this->resource);
            $structure = $range;
        }

        return $structure;
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
