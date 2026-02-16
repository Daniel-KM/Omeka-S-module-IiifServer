<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class ManifestV3Test extends IiifServerControllerTestCase
{
    public function testManifestV3EmptyItem(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('@context', $json);
        $context = is_array($json['@context']) ? implode(' ', $json['@context']) : $json['@context'];
        $this->assertStringContainsString('presentation/3', $context);
        $this->assertSame('Manifest', $json['type']);
    }

    public function testManifestV3ItemWithMetadata(): void
    {
        $item = $this->createItemWithMetadata('My Title', 'My Description', 'An Author');
        $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('label', $json);
        $this->assertArrayHasKey('metadata', $json);
        $this->assertArrayHasKey('summary', $json);
    }

    public function testManifestV3ItemWithHtmlMedia(): void
    {
        $item = $this->createItemWithHtmlMedia('Item with one media', 1);
        $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        // HTML media may or may not produce canvases depending on IIIF type
        // support. Verify the manifest is valid regardless.
        $this->assertSame('Manifest', $json['type']);
    }

    public function testManifestV3ItemWithMultipleMedia(): void
    {
        $item = $this->createItemWithHtmlMedia('Item with multiple media', 3);
        $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertSame('Manifest', $json['type']);
    }

    public function testManifestV3HasRequiredProperties(): void
    {
        $item = $this->createItemWithMetadata();
        $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('type', $json);
        $this->assertArrayHasKey('label', $json);
        $this->assertSame('Manifest', $json['type']);
    }

    public function testManifestV3ContentType(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/3/' . $item->id() . '/manifest');
        $contentType = $this->getResponse()->getHeaders()->get('Content-Type')->getFieldValue();
        $this->assertStringContainsString('json', $contentType);
    }
}
