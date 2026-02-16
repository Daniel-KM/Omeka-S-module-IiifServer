<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class ManifestV2Test extends IiifServerControllerTestCase
{
    public function testManifestV2EmptyItem(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('@context', $json);
        $context = is_string($json['@context']) ? $json['@context'] : implode(' ', $json['@context']);
        $this->assertStringContainsString('presentation/2', $context);
    }

    public function testManifestV2ItemWithMetadata(): void
    {
        $item = $this->createItemWithMetadata('My Title', 'My Description', 'An Author');
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('label', $json);
        $this->assertArrayHasKey('metadata', $json);
    }

    public function testManifestV2ItemWithHtmlMedia(): void
    {
        $item = $this->createItemWithHtmlMedia('Item with one media', 1);
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        // HTML media may not produce sequences/canvases since they are not
        // standard IIIF renderable types. Verify valid manifest.
        $this->assertArrayHasKey('@type', $json);
        $this->assertSame('sc:Manifest', $json['@type']);
    }

    public function testManifestV2ItemWithMultipleMedia(): void
    {
        $item = $this->createItemWithHtmlMedia('Item with multiple media', 3);
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertSame('sc:Manifest', $json['@type']);
    }

    public function testManifestV2HasRequiredProperties(): void
    {
        $item = $this->createItemWithMetadata();
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('@id', $json);
        $this->assertArrayHasKey('@type', $json);
        $this->assertArrayHasKey('label', $json);
        $this->assertSame('sc:Manifest', $json['@type']);
    }

    public function testManifestV2ContentType(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $contentType = $this->getResponse()->getHeaders()->get('Content-Type')->getFieldValue();
        $this->assertStringContainsString('json', $contentType);
    }
}
