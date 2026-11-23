<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase as LaminasAbstractHttpControllerTestCase;

/**
 * With the cache, the validators of the manifest use the date of the cached
 * file, so removing it refreshes the clients without changing the resources.
 */
class ManifestCacheValidatorTest extends IiifServerControllerTestCase
{
    protected $previousCache;

    public function setUp(): void
    {
        parent::setUp();
        $settings = $this->getApplicationServiceLocator()->get('Omeka\Settings');
        $this->previousCache = $settings->get('iiifserver_manifest_cache');
        $settings->set('iiifserver_manifest_cache', true);
    }

    public function tearDown(): void
    {
        $this->loginAsAdmin();
        $services = $this->getApplicationServiceLocator();
        $services->get('Omeka\Settings')->set('iiifserver_manifest_cache', $this->previousCache);
        $services->get('ControllerPluginManager')->get('iiifAccessCache')
            ->removeManifests($this->basePath(), $this->createdItemIds);
        parent::tearDown();
    }

    public function testEtagFollowsCachedFile(): void
    {
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $url = '/iiif/3/' . $item->id() . '/manifest';
        $plugin = $this->getApplicationServiceLocator()->get('ControllerPluginManager')->get('iiifAccessCache');

        // First request: no cached file yet.
        $this->dispatchAnonymous($url);
        $this->assertResponseStatusCode(200);
        $this->assertNotEmpty(glob($this->basePath() . '/iiif/3/' . $item->id() . '.*manifest.json'));

        // The cached file is newer than the item, so its date is used.
        sleep(1);
        $this->dispatchAnonymous($url);
        $etag = $this->etag();
        $this->dispatchAnonymous($url);
        $this->assertSame($etag, $this->etag());

        // Without module Access, the manifest of anonymous is the same for all.
        $this->dispatchAnonymous($url, $etag);
        $this->assertResponseStatusCode(304);

        // A job that updates the data of a media removes the cached file.
        sleep(1);
        $plugin->removeManifests($this->basePath(), [$item->id()]);
        $this->assertEmpty(glob($this->basePath() . '/iiif/3/' . $item->id() . '.*manifest.json'));
        $this->dispatchAnonymous($url);
        $this->assertResponseStatusCode(200);
        $this->assertNotSame($etag, $this->etag());
    }

    public function testEtagFollowsMediaDataWithoutCache(): void
    {
        $settings = $this->getApplicationServiceLocator()->get('Omeka\Settings');
        $settings->set('iiifserver_manifest_cache', false);
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $mediaId = $item->media()[0]->id();
        $url = '/iiif/3/' . $item->id() . '/manifest';

        $this->dispatchAnonymous($url);
        $etag = $this->etag();
        $this->dispatchAnonymous($url, $etag);
        $this->assertResponseStatusCode(304);

        // A job stores the dimensions without changing the date of the media.
        $this->getApplicationServiceLocator()->get('Omeka\Connection')->executeStatement(
            'UPDATE `media` SET `data` = ? WHERE `id` = ?',
            [json_encode(['dimensions' => ['original' => ['width' => 10, 'height' => 20]]]), $mediaId]
        );
        $this->dispatchAnonymous($url, $etag);
        $this->assertResponseStatusCode(200);
        $this->assertNotSame($etag, $this->etag());
    }

    protected function etag(): string
    {
        return $this->getResponse()->getHeaders()->get('ETag')->getFieldValue();
    }

    protected function dispatchAnonymous(string $url, ?string $etag = null): void
    {
        $this->reset();
        $this->getApplication();
        $this->logout();
        if ($etag) {
            $this->getRequest()->getHeaders()->addHeaderLine('If-None-Match', $etag);
        }
        LaminasAbstractHttpControllerTestCase::dispatch($url);
    }

    protected function basePath(): string
    {
        $config = $this->getApplicationServiceLocator()->get('Config');
        return $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    }
}
