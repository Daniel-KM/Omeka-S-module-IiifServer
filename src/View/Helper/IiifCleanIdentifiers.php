<?php
namespace IiifServer\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * View helper to get the identifier from id.
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
     * @param string|null $defaultSiteSlug
     */
    public function __construct($getIdentifiersFromResources = null)
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

        $helper = $this->getIdentifiersFromResources;
        $result = $helper($in);
        if ($isSingle) {
            return $result ? reset($result) : reset($in);
        }

        $identifiers = [];
        foreach ($in as $id) {
            $identifiers[] = isset($result[$id]) ? $result[$id] : $id;
        }
        return $identifiers;
    }
}
