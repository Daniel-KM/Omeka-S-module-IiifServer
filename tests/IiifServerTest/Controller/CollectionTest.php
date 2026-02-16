<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class CollectionTest extends IiifServerControllerTestCase
{
    public function testCollectionV3(): void
    {
        $itemSet = $this->createItemSet();
        $this->dispatch('/iiif/3/collection/' . $itemSet->id());
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertSame('Collection', $json['type']);
    }

    public function testCollectionV2(): void
    {
        $itemSet = $this->createItemSet();
        $this->dispatch('/iiif/2/collection/' . $itemSet->id());
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertSame('sc:Collection', $json['@type']);
    }

    public function testCollectionV3WithItems(): void
    {
        $item1 = $this->createItemWithMetadata('Item 1');
        $item2 = $this->createItemWithMetadata('Item 2');
        $itemSet = $this->createItemSetWithItems([$item1->id(), $item2->id()], 'Collection with items');
        $this->dispatch('/iiif/3/collection/' . $itemSet->id());
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertSame('Collection', $json['type']);
        $this->assertArrayHasKey('items', $json);
        $this->assertGreaterThanOrEqual(2, count($json['items']));
    }

    public function testCollectionV2WithItems(): void
    {
        $item1 = $this->createItemWithMetadata('Item 1');
        $item2 = $this->createItemWithMetadata('Item 2');
        $itemSet = $this->createItemSetWithItems([$item1->id(), $item2->id()], 'Collection with items');
        $this->dispatch('/iiif/2/collection/' . $itemSet->id());
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertSame('sc:Collection', $json['@type']);
        $this->assertArrayHasKey('manifests', $json);
        $this->assertGreaterThanOrEqual(2, count($json['manifests']));
    }
}
