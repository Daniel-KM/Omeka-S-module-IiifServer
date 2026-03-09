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
     * Construct the helper.
     *
     * Identifiers are always returned raw (unencoded). The Laminas router
     * handles percent-encoding, and IiifUrl/IiifMediaUrl conditionally
     * restore literal slashes based on the encode_slash setting.
     *
     * @todo Manage new CleanUrl.
     */
    public function __construct(
        ?GetIdentifiersFromResources $getIdentifiersFromResources = null,
        $prefix
    ) {
        $this->getIdentifiersFromResources = $getIdentifiersFromResources;
        $this->prefix = $prefix;
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

        $returnId = fn ($v) => is_object($v) ? (string) $v->id() : (string) $v;

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

        // Always return raw identifiers: the Laminas router handles
        // percent-encoding, and IiifUrl/IiifMediaUrl conditionally restore
        // literal slashes based on the encode_slash setting.
        if ($this->prefix) {
            $output = function ($v) {
                return strpos($v, $this->prefix) === 0
                    ? mb_substr($v, mb_strlen($this->prefix))
                    : $v;
            };
        } else {
            $output = fn ($v) => $v;
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
