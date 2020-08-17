<?php
namespace IiifServer\View\Helper;

use CleanUrl\View\Helper\GetIdentifiersFromResources;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * View helper to get the identifier from id and to url encode it.
 *
 * @link https://iiif.io/api/image/2.1/#uri-encoding-and-decoding
 * @link https://iiif.io/api/image/3.0/#9-uri-encoding-and-decoding
 */
class IiifCleanIdentifiers extends AbstractHelper
{
    /**
     * @var \CleanUrl\View\Helper\GetIdentifiersFromResources
     */
    protected $getIdentifiersFromResources;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var bool
     */
    protected $rawIdentifier;

    /**
     * Construct the helper.
     *
     * @param GetIdentifiersFromResources $getIdentifiersFromResources
     * @param bool $prefix
     * @param bool $rawIdentifier
     */
    public function __construct(
        GetIdentifiersFromResources $getIdentifiersFromResources = null,
        $prefix,
        $rawIdentifier
    ) {
        $this->getIdentifiersFromResources = $getIdentifiersFromResources;
        $this->prefix = $prefix;
        $this->rawIdentifier = $rawIdentifier;
    }

    /**
     * Get the identifier or the list of identifiers from ids or resources.
     *
     * @param AbstractResourceEntityRepresentation[]|AbstractResourceEntityRepresentation|int|]|int $resourcesOrIds
     * @return string[]|string Order and duplicates are kept. The internal id is
     * returned when there is no identifier.
     */
    public function __invoke($resourcesOrIds)
    {
        $isSingle = !is_array($resourcesOrIds);
        $in = $isSingle ? [$resourcesOrIds] : $resourcesOrIds;
        $first = reset($in);
        $isNumeric = is_numeric($first);

        if (!$this->getIdentifiersFromResources) {
            if ($isNumeric) {
                return $isSingle
                    ? (string) $first
                    : array_map('strval', $resourcesOrIds);
            }
            $returnId = function ($v) {
                return (string) $v->id();
            };
            return $isSingle
                ? $returnId($first)
                : array_map($returnId, $resourcesOrIds);
        }

        $returnId = function ($v) {
            return $v->id();
        };
        if (!$isNumeric) {
            $in = $isSingle
                ? $first->id()
                : array_map($returnId, $in);
        }

        if ($this->prefix) {
            if ($this->rawIdentifier) {
                $output = function ($v) {
                    return strpos($v, $this->prefix) === 0
                        ? mb_substr($v, mb_strlen($this->prefix))
                        : $v;
                };
            } else {
                $output = function ($v) {
                    return str_replace('%3A', ':', rawurlencode(strpos($v, $this->prefix) === 0
                        ?mb_substr($v, mb_strlen($this->prefix))
                        : $v));
                };
            }
        } elseif ($this->rawIdentifier) {
            $output = function ($v) {
                return $v;
            };
        }
        // Default options.
        else {
            // According to the specifications, the ":" should not be url encoded.
            $output = function ($v) {
                return str_replace('%3A', ':', rawurlencode($v));
            };
        }

        $result = $this->getIdentifiersFromResources->__invoke($in);
        if ($isSingle) {
            return $result ? $output(reset($result)) : reset($in);
        }

        $identifiers = [];
        foreach ($in as $id) {
            $identifiers[] = isset($result[$id]) ? $output($result[$id]) : $id;
        }
        return $identifiers;
    }
}
