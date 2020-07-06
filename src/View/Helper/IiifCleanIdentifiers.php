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
     * Construct the helper.
     *
     * @param GetIdentifiersFromResources $getIdentifiersFromResources
     */
    public function __construct(GetIdentifiersFromResources $getIdentifiersFromResources = null)
    {
        $this->getIdentifiersFromResources = $getIdentifiersFromResources;
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

        // According to the specifications, the ":" should not be url encoded.
        $urlEncode = function ($v) {
            return str_replace('%3A', ':', rawurlencode($v));
        };

        $helper = $this->getIdentifiersFromResources;
        $result = $helper($in);
        if ($isSingle) {
            return $result ? $urlEncode(reset($result)) : reset($in);
        }

        $identifiers = [];
        foreach ($in as $id) {
            $identifiers[] = isset($result[$id]) ? $urlEncode($result[$id]) : $id;
        }
        return $identifiers;
    }
}
