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

use ArrayObject;
use Common\Stdlib\PsrMessage;
use Doctrine\Inflector\InflectorFactory;
use JsonSerializable;

/**
 * Manage the IIIF objects.
 *
 * Whatever the array is filled with, it returns always a valid json IIIF object.
 * Default values can be set too.
 *
 * @todo Implements JsonLdSerializable.
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
     * @var \Laminas\Log\Logger|null
     */
    protected $logger;

    /**
     * @var string|null
     */
    protected $type = null;

    /**
     * Ordered list of properties associated with requirements for the type.
     *
     * @var array
     */
    protected $propertyRequirements = [];

    /**
     * Store the current object for output.
     */
    protected $content = [];

    /**
     * In some cases, it is useful to have an internal storage for temp values.
     * This property is a uniform way to manage them.
     *
     * @var array
     */
    protected $_storage = [];

    public function type(): ?string
    {
        return (string) $this->type;
    }

    public function getContent(): array
    {
        // Always refresh the content.
        $this->content = [];

        $allowedProperties = array_filter($this->propertyRequirements, fn ($v) => $v !== self::NOT_ALLOWED);
        $inflector = InflectorFactory::create()->build();
        foreach (array_keys($allowedProperties) as $property) {
            $method = $inflector->camelize(str_replace('@', '', $property));
            if (method_exists($this, $method)) {
                $this->content[$property] = $this->$method();
            }
        }

        return $this->content;
    }

    public function jsonSerialize(): array
    {
        // The validity check updates the content.
        $this->isValid(true);
        // TODO Remove useless context from sub-objects. And other copied data (homepage, etc.).
        return $this->content;
    }

    /**
     * Check validity for the object and related objects.
     *
     * @todo Add a debug mode: the method "isValid()" is useless in production.
     *
     * @throws \IiifServer\Iiif\Exception\RuntimeException
     */
    public function isValid(bool $throwException = false): bool
    {
        $output = $this->getCleanContent();

        // Check if all required data are present in root properties.
        $requiredProperties = array_filter($this->propertyRequirements, fn ($v) => $v === self::REQUIRED);
        $intersect = array_intersect_key($requiredProperties, $output);

        $e = null;
        if (count($requiredProperties) === count($intersect)) {
            // Second check for the children.
            // Instead of a recursive method, use jsonSerialize, that does the
            // same de facto.
            //  TODO Find a way to get only the upper exception with the path of classes.
            try {
                json_encode($output);
                return true;
            } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            }
        }

        if (!$throwException && !$this->logger) {
            return false;
        }

        // Ideally should be in class AbstractResourceType.
        $missingProperties = array_keys(array_diff_key($requiredProperties, $intersect));
        $hasResource = isset($this->resource);
        // TODO Use easyMeta.
        $resourceName = $hasResource ? $this->resource->resourceName() : null;
        $resourceNames = [
            'items' => 'item',
            'item_sets' => 'item set',
            'media' => 'media',
        ];
        if ($e) {
            if ($hasResource) {
                $message = new PsrMessage(
                    "{resource} #{resource_id}: Exception when processing iiif resource type \"{type}\":\n{message}", // @translate
                    [
                        'resource' => ucfirst($resourceNames[$resourceName]),
                        'resource_id' => $this->resource->id(),
                        'type' => $this->type(),
                        'message' => $e->getMessage(),
                    ]
                );
            } else {
                $message = new PsrMessage(
                    "Exception when processing iiif resource type \"{type}\":\n{message}", // @translate
                    [
                        'type' => $this->type(),
                        'message' => $e->getMessage(),
                    ]
                );
            }
        } elseif ($hasResource) {
            $message = new PsrMessage(
                '{resource} #{resource_id}: Missing required properties for iiif resource type "{type}": {properties}.', // @translate
                [
                    'resource' => ucfirst($resourceNames[$resourceName]),
                    'resource_id' => $this->resource->id(),
                    'type' => $this->type(),
                    'properties' => implode(', ', $missingProperties),
                ]
            );
        } else {
            $message = new PsrMessage(
                'Missing required properties for iiif resource type "{type}": {properties}.', // @translate
                [
                    'type' => $this->type(),
                    'properties' => implode(', ', $missingProperties),
                ]
            );
        }

        // The exception is catched and passed to the upper level. Only the
        // upper one is needed. The same for logger.
        // The lower level does not know if it called for itself or not.
        if ($this->logger) {
            $this->logger->err($message->getMessage(), $message->getContext());
        }

        if ($throwException) {
            throw new \IiifServer\Iiif\Exception\RuntimeException((string) $message);
        }

        return false;
    }

    /**
     * Remove useless key/values.
     *
     * There is no "0", "", "null" or empty array, except some exceptions.
     *
     * @return array
     */
    protected function getCleanContent(): array
    {
        return $this->content = array_filter($this->getContent(), function ($v) {
            if (is_array($v) || is_scalar($v) || is_null($v) || is_bool($v)) {
                return !empty($v);
            }
            // Normally, there is no object anymore.
            if ($v instanceof ArrayObject) {
                return (bool) $v->count();
            }
            if ($v instanceof JsonSerializable) {
                return (bool) $v->jsonSerialize();
            }
            return !empty($v);
        });
    }
}
