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

    public function __construct(Connection $connection, bool $hasModuleAccess)
    {
        $this->connection = $connection;
        $this->hasModuleAccess = $hasModuleAccess;
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
        $sql = <<<'SQL'
            SELECT access_status.id
            FROM access_status AS access_status
            LEFT JOIN media AS media ON media.id = access_status.id
            WHERE access_status.level != "free"
                AND (access_status.id = :item_id OR media.item_id = :item_id)
            ORDER BY access_status.id ASC
            LIMIT 1
            SQL;
        $id = $this->connection
            ->executeQuery($sql, ['item_id' => $itemId])
            ->fetchOne();
        return $id === false || $id === null ? null : (int) $id;
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
