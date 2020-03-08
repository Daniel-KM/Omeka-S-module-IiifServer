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
use Doctrine\Common\Inflector\Inflector;
use JsonSerializable;

/**
 * Manage the IIIF objects.
 *
 * @todo Use JsonLdSerializable?
 *
 * @author Daniel Berthereau
 */
abstract class AbstractType implements JsonSerializable
{
    const REQUIRED = 'required';
    const RECOMMENDED = 'recommended';
    const OPTIONAL = 'optional';
    const NOT_ALLOWED = 'not_allowed';

    /**
     * @var string
     */
    protected $type;

    /**
     * List of ordered keys for the type.
     *
     * @var array
     */
    protected $keys = [];

    /**
     * Store the current object for output.
     *
     * @var ArrayObject
     */
    protected $content;

    /**
     * In some cases, it is useful to have an internal storage for temp values.
     * This property is a uniform way to manage them.
     *
     * @var array
     */
    protected $_storage = [];

    /**
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation
     */
    public function getResource()
    {
        return $this->resource;
    }

    public function getContent()
    {
        // Always refresh the content.
        $this->content = new ArrayObject;

        $allowedKeys = array_filter($this->keys, function($v) {
            return $v !== self::NOT_ALLOWED;
        });
        foreach (array_keys($allowedKeys) as $key) {
            $method = 'get' . Inflector::classify(str_replace('@', '', $key));
            if (method_exists($this, $method)) {
                $this->content[$key] = $this->$method();
            }
        }

        return $this->content;
    }

    public function jsonSerialize()
    {
        $output = $this->getCleanContent();

        // TODO Remove useless context from sub-objects. And other copied data (homepage, etc.).

        // Check if all required data are present.
        $requiredKeys = array_filter($this->keys, function($v) {
            return $v === self::REQUIRED;
        });
        $intersect = array_intersect_key($requiredKeys, $output);
        if (count($requiredKeys) !== count($intersect)) {
            $missingKeys = array_keys(array_diff_key($requiredKeys, $intersect));
            throw new \RuntimeException(sprintf(
                'Missing required keys for resource type "%1$s": "%2$s".', // @translate
                $this->getType(), implode('", "', $missingKeys)
            ));
        }

        return (object) $output;
    }

    /**
     * Remove useless key/values.
     *
     * There is no "0", "", "null" or empty array at first level, except for
     * collection items, that may be empty after a search.
     *
     * @return array
     */
    protected function getCleanContent()
    {
        return array_filter($this->getContent()->getArrayCopy(), function($v) {
            if ($v instanceof ArrayObject) {
                return (bool) $v->count();
            }
            if ($v instanceof JsonSerializable) {
                return (bool) $v->jsonSerialize();
            }
            return !empty($v);
        });
    }

    /**
     * @return string
     */
    public function getType()
    {
        return (string) $this->type;
    }
}
