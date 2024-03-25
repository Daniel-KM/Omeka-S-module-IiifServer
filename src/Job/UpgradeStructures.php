<?php declare(strict_types=1);

namespace IiifServer\Job;

use DOMDocument;
use DOMElement;
use Omeka\Job\AbstractJob;

class UpgradeStructures extends AbstractJob
{
    /**
     * @var \IiifServer\Mvc\Controller\Plugin\RangeToArray
     */
    protected $rangeToArray;

    public function perform(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Laminas\Log\LoggerInterface $logger
         * @var \Omeka\Settings\Settings $settings
         * @var \Common\Stdlib\EasyMeta $easyMeta
         * @var \Doctrine\DBAL\Connection $connection
         */
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');
        $easyMeta = $services->get('EasyMeta');
        $connection = $services->get('Omeka\Connection');

        $plugins = $services->get('ControllerPluginManager');
        $this->rangeToArray = $plugins->get('rangeToArray');

        $structureProperty = $settings->get('iiifserver_manifest_structures_property');
        $structurePropertyId = $easyMeta->propertyId($structureProperty);
        if (!$structurePropertyId) {
            $logger->warn('No property defined for structure.'); // @translate
            return;
        }

        $query = $this->getArg('query');
        if ($query && !is_array($query)) {
            $queryItem = null;
            parse_str($query, $queryItem);
            if (empty($query)) {
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
                $logger->err('A query was set but invalid or empty.'); // @translate
                return;
            }
            $query = $queryItem;
        }

        $itemIds = [];
        if ($query) {
            $itemIds = $api->search('items', $query, ['returnScalar' => 'id'])->getContent();
            if (!$itemIds) {
                $logger->warn('A query was set but returned no items.'); // @translate
                return;
            }
        }

        $qb = $connection->createQueryBuilder();
        $bind = [];
        $types = [];

        $qb
            ->select('value.id', 'value.resource_id', 'value.value')
            ->from('value', 'value')
            ->innerJoin('value', 'item', 'item', 'item.id = value.resource_id')
            ->where('value.property_id = :property_id')
            ->andWhere('value.value IS NOT NULL')
            ->andWhere('value.value != ""')
            ->orderBy('value.id', 'asc');
        $bind['property_id'] = $structurePropertyId;
        $types['property_id'] = \Doctrine\DBAL\ParameterType::INTEGER;
        if ($itemIds) {
            $qb->andWhere('value.resource_id IN (:item_ids)');
            $bind['item_ids'] = $itemIds;
            $types['item_ids'] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
        }

        $structures = $connection->executeQuery($qb, $bind, $types)->fetchAllAssociative();
        if (!count($structures)) {
            $logger->warn('No structure to process.'); // @translate
            return;
        }

        $processed = 0;
        $skipped = 0;
        foreach ($structures as $valueData) {
            $valueId = $valueData['id'];
            $itemId = $valueData['resource_id'];
            $oldStructure = trim((string) $valueData['value']);
            if (!$itemId || !$oldStructure) {
                ++$skipped;
                continue;
            }
            $oldToc = $this->extractStructure($oldStructure);
            if (!$oldToc) {
                ++$skipped;
                continue;
            }
            $toc = $this->convertStructure($oldToc);
            $isXml = mb_substr(trim($oldStructure), 0, 1) === '<';
            $newStructure = $isXml
                ? $this->storeStructureXml($toc)
                : $this->storeStructureCode($toc);
            if (!$newStructure || $oldStructure === $newStructure) {
                ++$skipped;
                continue;
            }
            $sql = 'UPDATE `value` SET `value` = :structure WHERE `id` = :id;';
            $connection->executeStatement($sql, ['structure' => $newStructure, 'id' => $valueId]);
            $logger->notice(
                "Old structure for item #{item_id}:\n{toc}\nConverted to:\n{toc_2}", // @translate
                ['item_id' => $itemId, 'toc' => $oldStructure, 'toc_2' => $newStructure]
            );
            ++$processed;
        }

        if ($skipped) {
            $logger->notice(
                'A total of {count}/{total} values in property {term} were converted. {count_2} were not old table of contents and were skipped.', // @translate
                ['count' => $processed, 'total' => count($structures), 'term' => $structureProperty, 'count_2' => $skipped]
            );
        } else {
            $logger->notice(
                'A total of {total} tables of contents (values in property {term}) were converted.', // @translate
                ['total' => count($structures), 'term' => $structureProperty]
            );
        }
    }

    /**
     * Prepare to convert a literal or xml structure into a list of ranges.
     *
     * The previous process allowed more complex table of contents, with
     * unreferenced, duplicate or missing files.
     * Here, the missing ranges, that were identified by pages, are reincluded for a
     * strictly flat or nested table.
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
                if (preg_match('~^(?<name>[^,]*?)\s*,\s*(?<label>.*?)\s*,\s*(?<children>[^,]+?)$~u', $line, $matches)) {
                    $lines3[$lineIndex] = ['name' => $matches['name'], 'label' => $matches['label'], 'children' => $matches['children']];
                }
                if (preg_match('~^(?<id>[^,]*?)\s*,\s*(?<label>.*?)\s*,\s*(?<views>[\d -]*)\s*,\s*(?<ranges>[^,]+?)$~u', $line, $matches)) {
                    $lines4[$lineIndex] = ['id' => $matches['id'], 'label' => $matches['label'], 'views' => $matches['views'], 'ranges' => $matches['ranges']];
                }
            }
            if (!$lines3 || $empty === $total) {
                return null;
            }
            return count($lines4) === ($total - $empty) ? null : $lines3;
        }

        // Flat xml for old format (three columns): the new format is always nested.
        $isFlat = mb_strpos($literalStructure, '"/>') < mb_strpos($literalStructure, '<c ', 1);
        if ($isFlat) {
            // Split by newline code, but don't filter empty lines in order to
            // keep range indexes in complex cases.
            $sourceLines = explode("\n", $literalStructure);
            // Check if this is the old format (three columns and mixed pages
            // numbers and ranges) or the new one.
            $matches = [];
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
                if (preg_match('~\s*<c\s*(?:id="(?<name>[^"]*)")?\s*(?:label="(?<label>[^"]*)")?\s*(?:range_standard="(?<children>[^"]*)")?\s*/>~', $line, $matches)) {
                    $lines3[$lineIndex] = ['name' => $matches['name'] ?? '', 'label' => $matches['label'] ?? '', 'children' => $matches['children'] ?? ''];
                }
                if (preg_match('~\s*<c\s*(?:id="(?<id>[^"]*)")?\s*(?:label="(?<label>[^"]*)")?\s*(?:views="(?<views>[^"]*)")?\s*(?:ranges="(?<ranges>[^"]*)")?\s*/>~', $line, $matches)) {
                    $lines4[$lineIndex] = ['id' => $matches['id'] ?? '', 'label' => $matches['label'] ?? '', 'views' => $matches['views'] ?? '', 'ranges' => $matches['ranges'] ?? ''];
                }
            }
            if (!$lines3 || $empty === $total) {
                return null;
            }
            return count($lines4) === ($total - $empty) ? null : $lines3;
        }

        $xml = simplexml_load_string($literalStructure);
        if (!$xml) {
            return null;
        }

        $is3 = (bool) strpos($literalStructure, 'range_standard="');
        if (!$is3) {
            return null;
        }

        $xmlToArray = null;
        $xmlToArray = function($xml, &$lines) use (&$xmlToArray) {
            $lines[] = [
                'name' => (string) ($xml['id'] ?? null),
                'label' => (string) ($xml['label'] ?? null),
                'children' => (string) ($xml['range_standard'] ?? null),
            ];
            foreach ($xml->children() as $element) {
                if ($element->children()) {
                    $xmlToArray($element, $lines);
                }
            }
        };
        $lines = [];
        $xmlToArray($xml, $lines);

        return $lines ?: null;
    }

    /**
     * Upgrade a raw structure. Indentation is not kept.
     */
    protected function convertStructure(array $toc): array
    {
        // First loop to prepare children, views and ranges.
        $lines = [];
        $rangesAll = [];
        foreach ($toc as $lineIndex => $line) {
            $line['name'] = trim($line['name']);
            $name = strlen($line['name']) === 0 ? 'r' . ($lineIndex + 1) : $line['name'];
            $label = trim($line['label']);
            $children = $this->rangeToArray->__invoke($line['children'], 1, null, false, false, ';') ?: [];
            $ranges = [];
            foreach ($children as $child) {
                if (!is_numeric($child)) {
                    $ranges[] = $child;
                    $rangesAll[$child] ??= null;
                }
            }
            $lines[$name] = [
                'name' => $name,
                'line_index' => $lineIndex,
                'label' => $label,
                'children' => $children,
                'views' => [],
                'ranges' => $ranges,
                'ranges_full' => [],
            ];
            $rangesAll[$name] = $children;
        }

        foreach ($rangesAll as &$ranges) {
            if ($ranges === null) {
                $ranges = [];
            }
        }
        unset($ranges);
        ksort($rangesAll);

        // Second step to fill ranges with views.
        $totalRanges = count($rangesAll);
        for ($i = 0; $i < $totalRanges; $i++) {
            $rangesAllNew = [];
            foreach ($rangesAll as $name => $ranges) {
                $rangesAllNew[$name] = [];
                foreach ($ranges as $range) {
                    if (empty($range) || is_numeric($range) || empty($rangesAll[$range])) {
                        $rangesAllNew[$name][] = $range;
                    } else {
                        foreach ($rangesAll[$range] as $rang) {
                            $rangesAllNew[$name][] = $rang;
                        }
                    }
                    // Avoid memory issue on big table.
                    $rangesAllNew[$name] = array_unique($rangesAllNew[$name]);
                }
            }
            $rangesAll = $rangesAllNew;
        }
        foreach ($rangesAll as &$range) {
            $range = array_unique($range);
            sort($range);
        }
        unset($range);

        // Sort names by first pages for quicker process during rebuild.
        uasort($rangesAll, function($a, $b) {
            $aa = reset($a);
            $bb = reset($b);
            if ($aa === $bb) {
                return count($a) > count($b) ? -1 : (count($a) < count($b) ? 1 : 0);
            }
            return $aa <=> $bb;
        });
        $rangesAllFirst = [];
        foreach ($rangesAll as $name => $views) {
            $rangesAllFirst[$name] = $views ? reset($views) : null;
        }

        // Fill all views for all lines.
        foreach ($lines as $name => &$line) {
            $views = array_filter($rangesAll[$name], 'is_numeric');
            $line['views'] = $views;
        }
        unset($line);

        // Keep initial string ranges, but fill missing ranges.
        foreach ($lines as $name => &$line) {
            foreach ($line['children'] as $child) {
                if (is_numeric($child)) {
                    foreach ($rangesAllFirst as $rangeName => $rangeFirst) {
                        if ($child === $rangeFirst && $name !== $rangeName) {
                            $line['ranges_full'][$rangeName] = $rangeName;
                        }
                    }
                } else {
                    $line['ranges_full'][$child] = $child;
                }
            }
        }
        unset($line);

        // Manage the special case where the sub-view has the same view number
        // than the upper page.
        foreach ($lines as $name => $line) {
            foreach ($line['ranges_full'] as $child) {
                if (isset($lines[$child]['ranges_full'][$name])) {
                    if (count($line['views']) > count($lines[$child]['views'])) {
                        unset($lines[$child]['ranges_full'][$name]);
                    } else {
                        unset($lines[$name]['ranges_full'][$child]);
                    }
                    break;
                }
            }
        }

        // In the full toc, there may be missing view numbers.
        // When all views are missing, it was a full toc, so a list of views.
        // Else, it was a missing page, in which case the previous view number
        // can be used.
        $allMissing = 0;
        foreach ($lines as $line) {
            if (!count($line['views'])) {
                ++$allMissing;
            }
        }
        if ($allMissing === count($lines)) {
            // The full toc may contain only the first view of each level.
            // The first view was built as the main title, without page.
            foreach ($lines as &$line) {
                if (!count($line['views'])) {
                    $line['views'] = $line['line_index'] ? [$line['line_index']] : [];
                }
            }
        } else {
            $prev = 1;
            foreach ($lines as &$line) {
                if (!count($line['views'])) {
                    $line['views'] = [$prev];
                } else {
                    $prev = reset($line['views']);
                }
            }
        }
        unset($line);

        // The level is useless and not computed.

        return $lines;
    }

    /**
     * Convert a raw structure into an coded structure.
     */
    protected function storeStructureCode(array $lines): string
    {
        $structure = [];
        foreach ($lines as $name => $line) {
            $viewsString = $line['views'] ? $this->arrayToRange($line['views']) : '';
            $rangesString = $line['ranges_full'] ? implode('; ', $line['ranges_full']) : '-';
            $structure[] = sprintf('%s, %s, %s, %s', $name, $line['label'], $viewsString, $rangesString);
        }
        return implode("\n", $structure);
    }

    /**
     * Convert a raw structure into an xml structure.
     */
    protected function storeStructureXml(array $lines): ?string
    {
        $buildXml = null;
        $buildXml = function (array $lines, DOMDocument $dom, ?DOMElement $parent, array &$remainingLines) use (&$buildXml) {
            foreach ($lines as $name => $line) {
                if (!isset($remainingLines[$name])) {
                    continue;
                }
                unset($remainingLines[$name]);
                $viewsString = $line['views'] ? $this->arrayToRange($line['views']) : '';
                $element = $dom->createElement('c');
                $element->setAttribute('id', $name);
                // $element->setIdAttribute('id', true);
                $element->setAttribute('label', $line['label']);
                $element->setAttribute('views', $viewsString);
                if ($line['ranges_full']) {
                    $buildXml(array_intersect_key($remainingLines, $line['ranges_full']), $dom, $element, $remainingLines);
                }
                $parent
                    ? $parent->appendChild($element)
                    : $dom->appendChild($element);
            }
        };

        $dom = new DOMDocument('1.1', 'UTF-8');
        $buildXml($lines, $dom, null, $lines);
        $dom->xmlStandalone = true;
        $dom->substituteEntities = true;
        $dom->formatOutput = true;
        $output = $dom->saveXml();
        return mb_substr($output, mb_strpos($output, '<', 1));
    }

    protected function arrayToRange(array $array): string
    {
        $array = array_unique($array);
        if (!$array) {
            return '';
        }
        if (count($array) === 1) {
            return (string) reset($array);
        }
        sort($array);
        $first = $array[0];
        $last = $array[count($array) - 1];
        if (($first + count($array) - 1) === $last) {
            return $first . '-' . $last;
        }
        return implode('; ', $array);
    }
}
