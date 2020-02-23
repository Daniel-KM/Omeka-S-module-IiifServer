<?php

/*
 * Copyright 2020 Daniel Berthereau
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

use ArrayObject;
use JsonSerializable;
use Omeka\Api\Representation\ValueRepresentation;

class ValueLanguage implements JsonSerializable
{
    /**
     * @@var \Omeka\Api\Representation\ValueRepresentation[]
     */
    protected $values;

    /**
     * @@var string
     */
    protected $allowHtml;

    /**
     * @@var string
     */
    protected $fallback;

    /**
     * @var ArrayObject
     */
    protected $output;

    /**
     * Return a value as a iiif language value.
     *
     * @link https://iiif.io/api/presentation/3.0/#44-language-of-property-values
     *
     * @param ValueRepresentation|ValueRepresentation[]|array $values When the
     *   first value is not a ValueRepresentation, the values are returned directly.
     * @param bool $allowHtml Html is allowed only in summary, metadata value
     *   and requiredStatement.
     * @param array|string $fallback
     */
    public function __construct($values, $allowHtml = false, $fallback = null)
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $this->values = $values;
        $this->allowHtml = $allowHtml;
        $this->fallback = $fallback;

        $this->prepareOutput();
    }

    /**
     * Get all the data as array.
     *
     * @return ArrayObject
     */
    public function data()
    {
        return $this->output;
    }

    /**
     * Get the languages keys.
     *
     * @return array
     */
    public function langs()
    {
        return array_keys($this->output->getArrayCopy());
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->output->count();
    }

    public function jsonSerialize()
    {
        return $this->output->count()
            ? (object) $this->output
            : null;
    }

    protected function prepareOutput()
    {
        $this->output = new ArrayObject;

        if (count($this->values)) {
            $first = reset($this->values);
            if (gettype($first) === 'object' && $first instanceof ValueRepresentation) {
                if ($this->allowHtml) {
                    foreach ($this->values as $value) {
                        $lang = $value->lang() ?: 'none';
                        $this->output[$lang][] = $value->asHtml();
                    }
                } else {
                    foreach ($this->values as $value) {
                        $lang = $value->lang() ?: 'none';
                        $this->output[$lang][] = strip_tags($value);
                    }
                }
            } else {
                $this->output->exchangeArray($this->values);
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
