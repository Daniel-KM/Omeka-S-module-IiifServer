<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use IiifServer\Iiif\AbstractResourceType;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Manage the two variants of the cached manifest of an item.
 *
 * When the module Access is used, an item may contain medias that are not
 * "free", so it needs two manifests: one without them for the visitors that
 * have no right on them, one with all medias for the authorized visitors. The
 * variant depends only on the access status of the resources, so both files can
 * be built by a background job, then served according to the rights of the
 * visitor.
 *
 * An item with only free resources, or any item when the module Access is not
 * installed, has a single manifest.
 */
class IiifAccessCache extends AbstractPlugin
{
    /**
     * Suffix of the manifest built with all medias, whatever their status.
     */
    const SUFFIX_ALLOWED = '.allowed';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var bool
     */
    protected $hasModuleAccess;

    /**
     * @var int[] Ids of the properties that link digital objects to the item.
     */
    protected $digitalObjectPropertyIds;

    public function __construct(Connection $connection, bool $hasModuleAccess, array $digitalObjectPropertyIds = [])
    {
        $this->connection = $connection;
        $this->hasModuleAccess = $hasModuleAccess;
        $this->digitalObjectPropertyIds = $digitalObjectPropertyIds;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * The id of a resource of the item that is not "free", if any.
     *
     * The item needs two manifests only when such a resource exists. The check
     * is a single indexed query, so it can be done on each request.
     */
    public function reservedResourceId(int $itemId): ?int
    {
        if (!$this->hasModuleAccess) {
            return null;
        }
        // Each part uses its own index and stops at the first reserved resource:
        // a single condition "item OR media of the item" prevents the indexes and
        // scans all the reserved resources of the base.
        // Unlike the medias, the digital objects are not linked to the item by a
        // column, but by values of the properties of the module Digital Object.
        $bind = ['item_id' => $itemId];
        $types = [];
        $sqlDigitalObjects = '';
        if ($this->digitalObjectPropertyIds) {
            $sqlDigitalObjects = <<<'SQL'
                    UNION ALL
                    (
                        SELECT access_status.id
                        FROM `value`
                        INNER JOIN resource AS linked ON linked.id = `value`.value_resource_id
                        INNER JOIN access_status AS access_status ON access_status.id = `value`.value_resource_id
                        WHERE `value`.resource_id = :item_id
                            AND `value`.property_id IN (:property_ids)
                            AND linked.resource_type = :digital_object_type
                            AND access_status.level != "free"
                        ORDER BY access_status.id ASC
                        LIMIT 1
                    )
                SQL;
            $bind['property_ids'] = $this->digitalObjectPropertyIds;
            $bind['digital_object_type'] = 'DigitalObject\\Entity\\DigitalObject';
            $types['property_ids'] = Connection::PARAM_INT_ARRAY;
        }

        $sql = <<<SQL
            SELECT MIN(reserved.id)
            FROM (
                (
                    SELECT access_status.id
                    FROM access_status AS access_status
                    WHERE access_status.id = :item_id
                        AND access_status.level != "free"
                )
                UNION ALL
                (
                    SELECT media.id
                    FROM media AS media
                    INNER JOIN access_status AS access_status ON access_status.id = media.id
                    WHERE media.item_id = :item_id
                        AND access_status.level != "free"
                    ORDER BY media.id ASC
                    LIMIT 1
                )
            $sqlDigitalObjects
            ) AS reserved
            SQL;
        $id = $this->connection
            ->executeQuery($sql, $bind, $types)
            ->fetchOne();
        return $id === false || $id === null ? null : (int) $id;
    }

    /**
     * Check if the manifest of an item may contain private data.
     *
     * The cached manifests are built as anonymous, so they can be served to a
     * user who can see the private resources (admin, owner) only when there is
     * no private data: the item, its medias, their values, the resources linked
     * by these values (digital objects included) and its item sets. Each part
     * stops at the first private row.
     */
    public function hasPrivateContent(int $itemId): bool
    {
        $sql = <<<'SQL'
            SELECT 1 FROM (
                (
                    SELECT 1 FROM resource
                    WHERE resource.id = :item_id AND resource.is_public = 0
                )
                UNION ALL
                (
                    SELECT 1 FROM media
                    INNER JOIN resource ON resource.id = media.id
                    WHERE media.item_id = :item_id AND resource.is_public = 0
                    LIMIT 1
                )
                UNION ALL
                (
                    SELECT 1 FROM `value`
                    LEFT JOIN resource AS linked ON linked.id = `value`.value_resource_id
                    WHERE `value`.resource_id = :item_id
                        AND (`value`.is_public = 0 OR linked.is_public = 0)
                    LIMIT 1
                )
                UNION ALL
                (
                    SELECT 1 FROM media
                    INNER JOIN `value` ON `value`.resource_id = media.id
                    LEFT JOIN resource AS linked ON linked.id = `value`.value_resource_id
                    WHERE media.item_id = :item_id
                        AND (`value`.is_public = 0 OR linked.is_public = 0)
                    LIMIT 1
                )
                UNION ALL
                (
                    SELECT 1 FROM item_item_set
                    INNER JOIN resource ON resource.id = item_item_set.item_set_id
                    WHERE item_item_set.item_id = :item_id AND resource.is_public = 0
                    LIMIT 1
                )
            ) AS private_content
            LIMIT 1
            SQL;
        return (bool) $this->connection
            ->executeQuery($sql, ['item_id' => $itemId])
            ->fetchOne();
    }

    /**
     * The path of the manifest to read or to write for the given variant.
     *
     * The manifest without the reserved medias keeps the historical path, so it
     * is also the single manifest of an item that has only free resources.
     */
    public function filepath(string $basePath, string $version, int $itemId, string $accessFilter): string
    {
        $suffix = $accessFilter === AbstractResourceType::ACCESS_FILTER_ALL
            ? self::SUFFIX_ALLOWED
            : '';
        return sprintf('%s/iiif/%s/%d%s.manifest.json', $basePath, $version, $itemId, $suffix);
    }

    /**
     * The variant to serve to the current visitor.
     *
     * The visitor gets the full manifest when the module Access allows him the
     * content of a reserved resource of the item, whatever the way the right is
     * granted (ip, guest, sso, individual request, etc.). An item without any
     * reserved resource has a single manifest.
     */
    public function accessFilterForVisitor(int $itemId): string
    {
        $reservedId = $this->reservedResourceId($itemId);
        if (!$reservedId) {
            return AbstractResourceType::ACCESS_FILTER_FREE;
        }

        $controller = $this->getController();
        $plugins = $controller->getPluginManager();
        if (!$plugins->has('isAllowedMediaContent')) {
            return AbstractResourceType::ACCESS_FILTER_FREE;
        }

        try {
            $reserved = $controller->api()
                ->read('resources', ['id' => $reservedId])->getContent();
        } catch (\Exception $e) {
            return AbstractResourceType::ACCESS_FILTER_FREE;
        }

        return $plugins->get('isAllowedMediaContent')->__invoke($reserved)
            ? AbstractResourceType::ACCESS_FILTER_ALL
            : AbstractResourceType::ACCESS_FILTER_FREE;
    }
}
