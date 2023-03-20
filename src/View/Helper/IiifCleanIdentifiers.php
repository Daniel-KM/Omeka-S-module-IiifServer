<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use CleanUrl\View\Helper\GetIdentifiersFromResources;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

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
     * @todo Manage new CleanUrl.
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
     * @param AbstractResourceEntityRepresentation[]|AbstractResourceEntityRepresentation|int|]|int|string[]|string $resourcesOrIds Or urls.
     * @return string[]|string Order and duplicates are kept. The internal id is
     * returned when there is no identifier.
     */
    public function __invoke($resourcesOrIds)
    {
        $isSingle = !is_array($resourcesOrIds);
        $in = $isSingle ? [$resourcesOrIds] : $resourcesOrIds;
        $first = reset($in);
        $isNumeric = is_numeric($first);
        $isResource = is_object($first);

        $returnId = function ($v) {
            return is_object($v) ? (string) $v->id() : (string) $v;
        };

        if (!$this->getIdentifiersFromResources
            || (!$isNumeric && !$isResource)
        ) {
            if ($isNumeric) {
                return $isSingle
                    ? (string) $first
                    : array_map('strval', $resourcesOrIds);
            }
            return $isSingle
                ? $returnId($first)
                : array_map($returnId, $resourcesOrIds);
        }

        if ($isResource) {
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
                        ? mb_substr($v, mb_strlen($this->prefix))
                        : $v));
                };
            }
        } elseif ($this->rawIdentifier) {
            // Fake function for simplicity.
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
