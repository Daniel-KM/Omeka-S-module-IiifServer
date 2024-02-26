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
use JsonSerializable;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Manage the IIIF objects.
 *
 * Whatever the array is filled with, it returns always a valid json IIIF object.
 * Default values can be set too.
 *
 * The internal array contains the data.
 * Unlike previous version, calling jsonSerialize() does not validate.
 * Calling `normalize()` is required to set the internal array checked, fixed,
 * reordered and completed.
 *
 * @todo Implements JsonLdSerializable, that will force validity.
 * @todo Add a flag isValidated that is reset each time a data is updated? Useless for now, since there is only one call, maybe a second time with modules.
 *
 * Note: in an ArrayObject, the properties are separated from the keys of the
 * internal array.
 *
 * @author Daniel Berthereau
 */
abstract class AbstractType extends ArrayObject implements JsonSerializable
{
    const REQUIRED = 'required';
    const RECOMMENDED = 'recommended';
    const OPTIONAL = 'optional';
    const NOT_ALLOWED = 'not_allowed';

    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

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
     * @var array
     */
    protected $options = [];

    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function setServiceLocator(ServiceLocatorInterface $services): self
    {
        $this->services = $services;
        return $this;
    }

    /**
     * Unlike previous version, calling jsonSerialize() does not validate. Use
     * jsonLd() instead.
     * Nevertheless, it remove empty properties.
     */
    public function jsonSerialize(): array
    {
        // Remove useless key/values. There is no empty values.
        // Normally, there is no empty ArrayObject or any other object.
        // For now, there are exceptions with Collection and CollectionList,
        // so use a method.
        // return array_filter($result , fn ($v) => $v !== null && $v !== '' && $v !== []);
        return array_filter($this->getArrayCopy(), [$this, 'filterContentFilled'], ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Set property requirements.
     *
     * This method is used to adapt, complete or skip property requirements, for
     * example for an extra service. It is not used internally.
     */
    public function setPropertyRequirements(array $propertyRequirements): array
    {
        $this->propertyRequirements = $propertyRequirements;
        return $this;
    }

    public function getPropertyRequirements(): array
    {
        return $this->propertyRequirements;
    }

    /**
     * Get the list of allowed properties of this type.
     */
    public function getProperties(): array
    {
        return array_keys(array_filter($this->propertyRequirements, fn ($v) => $v !== self::NOT_ALLOWED));
    }

    /**
     * Get the list of required properties of this type.
     */
    public function getPropertiesRequired(): array
    {
        return array_keys(array_filter($this->propertyRequirements, fn ($v) => $v === self::REQUIRED));
    }

    /**
     * Get the type of the resource.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * This is a method for the internal array, but type is forced.
     */
    public function type(): ?string
    {
        return $this->type;
    }

    /**
     * Normalize reorder, complete, skip properties and fill internal array.
     *
     * The output is always standard, or it is replaced by an empty array.
     * Empty properties are not removed in order to keep same results through
     * multiple normalize().So use jsonSerialize() in that case.
     * Non-standard properties are kept as long as they are not forbidden.
     *
     * @todo Remove useless context from sub-objects. And other copied data (homepage, etc.).
     * @todo Manage version (2.1 / 3.0).
     */
    public function normalize(): self
    {
        // Prepare data for possible messages.
        // TODO Use easyMeta.
        $resource = method_exists($this, 'getResource') ? $this->getResource() : null;
        $resourceNames = [
            'items' => 'item',
            'item_sets' => 'item set',
            'media' => 'media',
        ];
        $resourceName = $resource ? $resourceNames[$resource->resourceName()] ?? $resource->resourceName() : null;
        // Fill allowed properties.
        $allowedProperties = $this->getProperties();
        foreach ($allowedProperties as $property) {
            // Don't normalize property that is already in the internal final array.
            if ($this->offsetExists($property)) {
                continue;
            }
            /** @see https://iiif.io/api/presentation/3.0/#48-keyword-mappings */
            // By default, only "@context" requires a "@". "@id", "@type" and
            // "@none" may exist and are mapped to "id", "type" and "none".
            // Old implementation or external specs can use "@" everywhere, like
            // "@graph".
            $method = mb_substr($property, 0, 1) === '@' ? mb_substr($property, 1) : $property;
            if (method_exists($this, $method)) {
                $value = $this->$method();
                if (is_object($value) && method_exists($value, 'normalize')) {
                    $this[$property] = $value->normalize();
                } elseif (is_array($value) && is_integer(key($value))) {
                    // TODO Make items, etc. a generic collection.
                    // There is no sub-arrays. Sub-normalization is recursively managed.
                    foreach ($value as $val) {
                        $this[$property][] = is_object($val) && method_exists($val, 'normalize')
                            ? $val->normalize()
                            : $val;
                    }
                } else {
                    $this[$property] = $value;
                }
            } else {
                if ($resource) {
                    $message = new PsrMessage(
                        '{resource} #{resource_id}: Unknown property "{property}" for iiif resource type {type}.', // @translate
                        [
                            'resource' => ucfirst($resourceName),
                            'resource_id' => $resource->id(),
                            'property' => $property,
                            'type' => $this->type(),
                        ]
                    );
                } else {
                    $message = new PsrMessage(
                        'Unknow iiif property "{property}" for type {type}.', // @translate
                        ['property' => $property, 'type' => $this->type]
                    );
                }
                if ($this->services) {
                    $this->services->get('Omeka\Logger')->err($message->getMessage(), $message->getContext());
                }
            }
        }

        // Remove forbidden properties.
        $forbiddenProperties = array_filter($this->propertyRequirements, fn ($v) => $v === self::NOT_ALLOWED);
        $forbiddenIntersect = array_intersect_key($forbiddenProperties, $this->getArrayCopy());
        if ($forbiddenIntersect) {
            $this->exchangeArray(array_diff_key($this->getArrayCopy(), $forbiddenIntersect));
            $this->services->get('Omeka\Logger')->warn(
                'Forbidden iiif properties {properties} for type {type}.', // @translate
                ['properties' => implode(', ', array_keys($forbiddenIntersect)), 'type' => $this->type]
            );
        }

        // Reorder properties, keeping non-standard properties.
        // The reorder is not required in iiif or json-ld.
        $arrayCopy = $this->getArrayCopy();
        $this->exchangeArray(array_replace(array_intersect_key($allowedProperties, $arrayCopy), $arrayCopy));

        return $this;
    }

    /**
     * Check validity for the passed data.
     *
     * This method is no more used internally, since normalize() output right content.
     *
     * @todo Call isValid() only in debug mode: it is probably useless in production.
     * @todo Finalize isValid() getting righ properties requirements of each level.
     *
     * @throws \IiifServer\Iiif\Exception\RuntimeException
     */
    public function isValid(array $data, bool $throwException = false): bool
    {
        // Check if all required data are present in root properties.
        $requiredProperties = array_filter($this->propertyRequirements, fn ($v) => $v === self::REQUIRED);
        $requiredIntersect = array_intersect_key($requiredProperties, $data);
        $forbiddenProperties = array_filter($this->propertyRequirements, fn ($v) => $v === self::NOT_ALLOWED);
        $forbiddenIntersect = array_keys(array_intersect_key($forbiddenProperties, $data));

        // FIXME Find a better way to check children.
        $e = null;
        if (count($requiredProperties) === count($requiredIntersect) && !count($forbiddenIntersect)) {
            // Second check for the children.
            // Instead of a recursive method, use jsonSerialize, that does the
            // same de facto.
            // TODO Find a way to get only the upper exception with the path of classes.
            // TODO This process is too much slow.
            try {
                json_encode($data);
                return true;
            } catch (\IiifServer\Iiif\Exception\RuntimeException $e) {
            }
        }

        if (!$throwException && !$this->services) {
            return false;
        }

        // Ideally should be in class AbstractResourceType.
        $resource = method_exists($this, 'getResource') ? $this->getResource() : null;
        // TODO Use easyMeta.
        $resourceNames = [
            'items' => 'item',
            'item_sets' => 'item set',
            'media' => 'media',
        ];
        $resourceName = $resource ? $resourceNames[$resource->resourceName()] ?? $resource->resourceName() : null;

        $missingProperties = array_keys(array_diff_key($requiredProperties, $requiredIntersect));
        if ($missingProperties) {
            if ($resource) {
                $message = new PsrMessage(
                    '{resource} #{resource_id}: Missing required properties for iiif resource type "{type}": {properties}.', // @translate
                    [
                        'resource' => ucfirst($resourceName),
                        'resource_id' => $resource->id(),
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
            if ($this->services) {
                $this->services->get('Omeka\Logger')->err($message->getMessage(), $message->getContext());
            }
        }

        if ($forbiddenIntersect) {
            if ($resource) {
                $message = new PsrMessage(
                    '{resource} #{resource_id}: Forbidden properties for iiif resource type "{type}": {properties}', // @translate
                    [
                        'resource' => ucfirst($resourceName),
                        'resource_id' => $resource->id(),
                        'type' => $this->type(),
                        'properties' => implode(', ', $forbiddenIntersect),
                    ]
                );
            } else {
                $message = new PsrMessage(
                    'Forbidden properties for iiif resource type "{type}": {properties}', // @translate
                    [
                        'type' => $this->type(),
                        'properties' => implode(', ', $forbiddenIntersect),
                    ]
                );
            }
            if ($this->services) {
                $this->services->get('Omeka\Logger')->err($message->getMessage(), $message->getContext());
            }
        }

        if ($e) {
            if ($resource) {
                $message = new PsrMessage(
                    "{resource} #{resource_id}: Exception when processing iiif resource type \"{type}\":\n{message}", // @translate
                    [
                        'resource' => ucfirst($resourceName),
                        'resource_id' => $resource->id(),
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
            if ($this->services) {
                $this->services->get('Omeka\Logger')->err($message->getMessage(), $message->getContext());
            }
        }

        // The exception is catched and passed to the upper level. Only the
        // upper one is needed. The same for logger.
        // The lower level does not know if it called for itself or not.
        if ($throwException) {
            throw new \IiifServer\Iiif\Exception\RuntimeException((string) $message);
        }

        return false;
    }

    /**
     * Remove empty values of a iiif array.
     *
     * In IIIF, there is no empty array, empty string, number 0, boolean false
     * or null, String "0" is possible.
     */
    protected function filterContentFilled($v, $k): bool
    {
        if ($v === '0') {
            return true;
        }
        if (is_array($v) || is_scalar($v) || is_null($v)) {
            return !empty($v);
        }
        if ($v instanceof \JsonSerializable) {
            return (bool) $v->jsonSerialize();
        }
        return !empty($v);
    }
}
