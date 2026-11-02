<?php declare(strict_types=1);

namespace IiifServerTest\Mvc\Controller\Plugin;

use IiifServerTest\Controller\IiifServerControllerTestCase;

/**
 * The cached manifest may be served to a privileged user only when the item has
 * no private data.
 */
class IiifAccessCachePrivateContentTest extends IiifServerControllerTestCase
{
    /**
     * @var \IiifServer\Mvc\Controller\Plugin\IiifAccessCache
     */
    protected $iiifAccessCache;

    public function setUp(): void
    {
        parent::setUp();
        $this->iiifAccessCache = $this->getApplicationServiceLocator()
            ->get('ControllerPluginManager')->get('iiifAccessCache');
    }

    public function testPublicItemHasNoPrivateContent(): void
    {
        $item = $this->createItemWithHtmlMedia('Public item', 2);
        $this->assertFalse($this->iiifAccessCache->hasPrivateContent($item->id()));
    }

    public function testPrivateItem(): void
    {
        $item = $this->createItemWithHtmlMedia();
        $this->setPrivate('resource', $item->id());
        $this->assertTrue($this->iiifAccessCache->hasPrivateContent($item->id()));
    }

    public function testPrivateMedia(): void
    {
        $item = $this->createItemWithHtmlMedia('Item', 2);
        $this->setPrivate('resource', $item->media()[1]->id());
        $this->assertTrue($this->iiifAccessCache->hasPrivateContent($item->id()));
    }

    public function testPrivateValueOfItem(): void
    {
        $item = $this->createItemWithMetadata();
        $this->connection()->executeStatement(
            'UPDATE `value` SET `is_public` = 0 WHERE `resource_id` = ? AND `property_id` = 4',
            [$item->id()]
        );
        $this->assertTrue($this->iiifAccessCache->hasPrivateContent($item->id()));
    }

    public function testPrivateValueOfMedia(): void
    {
        $item = $this->createItemWithHtmlMedia();
        $this->connection()->executeStatement(
            'UPDATE `value` SET `is_public` = 0 WHERE `resource_id` = ?',
            [$item->media()[0]->id()]
        );
        $this->assertTrue($this->iiifAccessCache->hasPrivateContent($item->id()));
    }

    public function testValueLinkedToPrivateResource(): void
    {
        $linked = $this->createItem();
        $this->setPrivate('resource', $linked->id());
        $item = $this->createItem();
        $this->api()->update('items', $item->id(), [
            'dcterms:relation' => [[
                'type' => 'resource',
                'property_id' => 13,
                'value_resource_id' => $linked->id(),
            ]],
        ]);
        $this->assertTrue($this->iiifAccessCache->hasPrivateContent($item->id()));
    }

    public function testPrivateItemSet(): void
    {
        $item = $this->createItem();
        $itemSet = $this->createItemSetWithItems([$item->id()]);
        $this->assertFalse($this->iiifAccessCache->hasPrivateContent($item->id()));
        $this->setPrivate('resource', $itemSet->id());
        $this->assertTrue($this->iiifAccessCache->hasPrivateContent($item->id()));
    }

    public function testAdminGetsCachedManifestOfPublicItemOnly(): void
    {
        $services = $this->getApplicationServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $previous = $settings->get('iiifserver_manifest_cache');
        $settings->set('iiifserver_manifest_cache', true);

        $item = $this->createItemWithMetadata();
        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        // The variant depends on the module Access, that may be missing.
        $filepaths = array_unique([
            $this->iiifAccessCache->filepath($basePath, '3', $item->id(), 'free'),
            $this->iiifAccessCache->filepath($basePath, '3', $item->id(), 'all'),
        ]);
        @mkdir(dirname(reset($filepaths)), 0775, true);
        foreach ($filepaths as $filepath) {
            file_put_contents($filepath, json_encode(['@context' => 'http://iiif.io/api/presentation/3/context.json', 'id' => 'cached', 'type' => 'Manifest']));
            // A cache written in the same second as the item is outdated.
            touch($filepath, time() + 60);
        }

        try {
            $this->loginAsAdmin();
            $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
            $this->assertResponseStatusCode(200);
            $this->assertStringContainsString('"cached"', $this->getResponse()->getContent());

            $this->connection()->executeStatement(
                'UPDATE `value` SET `is_public` = 0 WHERE `resource_id` = ? AND `property_id` = 4',
                [$item->id()]
            );
            $this->reset();
            $this->loginAsAdmin();
            $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
            $this->assertResponseStatusCode(200);
            $this->assertStringNotContainsString('"cached"', $this->getResponse()->getContent());
            // The fresh manifest of the admin is not written in the cache.
            foreach ($filepaths as $filepath) {
                $this->assertStringContainsString('"cached"', (string) file_get_contents($filepath));
            }
        } finally {
            array_map('unlink', array_filter($filepaths, 'file_exists'));
            $this->getApplicationServiceLocator()->get('Omeka\Settings')->set('iiifserver_manifest_cache', $previous);
        }
    }

    public function testOutdatedCachedManifestIsRebuilt(): void
    {
        $services = $this->getApplicationServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $previous = $settings->get('iiifserver_manifest_cache');
        $settings->set('iiifserver_manifest_cache', true);

        $item = $this->createItemWithMetadata();
        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $filepath = $this->iiifAccessCache->filepath($basePath, '3', $item->id(), 'free');
        @mkdir(dirname($filepath), 0775, true);
        file_put_contents($filepath, json_encode(['@context' => 'http://iiif.io/api/presentation/3/context.json', 'id' => 'cached', 'type' => 'Manifest']));
        // The item or a media was modified after the cache.
        touch($filepath, time() - 60);

        try {
            $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
            $this->assertResponseStatusCode(200);
            $this->assertStringNotContainsString('"cached"', $this->getResponse()->getContent());
        } finally {
            @unlink($filepath);
            $this->getApplicationServiceLocator()->get('Omeka\Settings')->set('iiifserver_manifest_cache', $previous);
        }
    }

    protected function setPrivate(string $table, int $id): void
    {
        $this->connection()->executeStatement("UPDATE `$table` SET `is_public` = 0 WHERE `id` = ?", [$id]);
    }

    protected function connection(): \Doctrine\DBAL\Connection
    {
        return $this->getApplicationServiceLocator()->get('Omeka\Connection');
    }
}
