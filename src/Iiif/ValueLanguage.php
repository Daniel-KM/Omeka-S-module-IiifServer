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

use JsonSerializable;
use Omeka\Api\Representation\ValueRepresentation;

class ValueLanguage implements JsonSerializable
{
    /**
     * @var \Omeka\Api\Representation\ValueRepresentation[]
     */
    protected $values;

    /**
     * @var bool
     */
    protected $allowHtml;

    /**
     * @var string
     */
    protected $fallback;

    /**
     * @var bool
     */
    protected $multipleValues;

    /**
     * @var array
     */
    protected $output;

    /**
     * Return a value as a iiif language value.
     *
     * @link https://iiif.io/api/presentation/3.0/#44-language-of-property-values
     *
     * @param ValueRepresentation[]|array|ValueRepresentation|string $values When the
     *   first value is not a ValueRepresentation, the values are returned directly.
     * @param bool $allowHtml Html is allowed only in summary, metadata value
     *   and requiredStatement.
     * @param string $fallback
     * @param bool $multipleValues Don't keep only the first value of each language.
     * @param array|string $fallback Should have only one value if only one
     *   value is allowed.
     */
    public function __construct($values, bool $allowHtml = false, ?string $fallback = null, bool $multipleValues = false)
    {
        if (is_string($values)) {
            $values = ['none' => [$values]];
        } elseif (!is_array($values)) {
            $values = [$values];
        }

        $this->values = $values;
        $this->allowHtml = $allowHtml;
        $this->fallback = $fallback;
        $this->multipleValues = $multipleValues;

        $this->prepareOutput();
    }

    public static function output($values, bool $allowHtml = false, ?string $fallback = null, bool $multipleValues = false): ?array
    {
        return (new ValueLanguage($values, $allowHtml, $fallback, $multipleValues))
            ->jsonSerialize();
    }

    /**
     * Get all the data as array.
     */
    public function data(): array
    {
        return $this->output;
    }

    /**
     * Get the languages keys.
     */
    public function langs(): array
    {
        return array_keys($this->output);
    }

    public function count(): int
    {
        return count($this->output);
    }

    public function jsonSerialize(): ?array
    {
        return count($this->output)
            ? $this->output
            : null;
    }

    protected function prepareOutput(): array
    {
        $this->output = [];

        if (count($this->values)) {
            $first = reset($this->values);
            if (gettype($first) === 'object' && $first instanceof ValueRepresentation) {
                if ($this->allowHtml) {
                    $helpers = $first->getServiceLocator()->get('ViewHelperManager');
                    $publicResourceUrl = $helpers->get('publicResourceUrl');
                    $escape = $helpers->get('escapeHtml');
                    $escapeAttr = $helpers->get('escapeHtmlAttr');
                    foreach ($this->values as $value) {
                        $lang = $value->lang() ?: 'none';
                        if ($this->multipleValues || empty($this->output[$lang])) {
                            if (strpos($value->type(), 'resource') === 0 && $vr = $value->valueResource()) {
                                $html = '<a class="resource-link" href="' . $escapeAttr($publicResourceUrl($vr, true)) . '">'
                                    . '<span class="resource-name">' . $escape($vr->displayTitle()) . '</span>'
                                    . '</a>';
                            } else {
                                $html = $value->asHtml();
                            }
                            $this->output[$lang][] = $html;
                        }
                    }
                } else {
                    foreach ($this->values as $value) {
                        $lang = $value->lang() ?: 'none';
                        if ($this->multipleValues || empty($this->output[$lang])) {
                            $this->output[$lang][] = $value->type() === 'uri'
                                ? (string) $value->uri()
                                : strip_tags((string) $value);
                        }
                    }
                }
            } else {
                // This check is normally useless.
                if (!$this->multipleValues) {
                    foreach ($this->values as $lang => &$v) {
                        $v = [is_array($v) ? (string) reset($v) : (string) $v];
                    }
                    unset($v);
                }
                $this->output = $this->values;
            }

            // Keep none at last.
            if (count($this->output) > 1 && isset($this->output['none'])) {
                $none = $this->output['none'];
                unset($this->output['none']);
                $this->output['none'] = $none;
            }
        } elseif ($this->fallback) {
            $this->output['none'][] = $this->fallback;
        }

        return $this->output;
    }
}
