<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class PresentationControllerTest extends IiifServerControllerTestCase
{
    public function testIndexActionCanBeAccessed(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/' . $item->id());
        $this->assertResponseStatusCode(200);
    }

    public function testIndexActionWithInvalidIdCannotBeAccessed(): void
    {
        $this->dispatch('/iiif/' . 999999);
        $this->assertResponseStatusCode(404);
    }

    public function testManifestActionCanBeAccessed(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
    }

    public function testManifestActionWithInvalidIdCannotBeAccessed(): void
    {
        $this->dispatch('/iiif/' . 999999 . '/manifest');
        $this->assertResponseStatusCode(404);
    }

    public function testCollectionActionCanBeAccessed(): void
    {
        $itemSet = $this->createItemSet();
        $this->dispatch('/iiif/collection/' . $itemSet->id());
        $this->assertResponseStatusCode(200);
    }

    public function testCollectionActionWithInvalidIdCannotBeAccessed(): void
    {
        $this->dispatch('/iiif/collection/' . 999999);
        $this->assertResponseStatusCode(404);
    }

    public function testListActionCanBeAccessed(): void
    {
        $item = $this->createItem();
        $itemSet = $this->createItemSet();
        $this->dispatch('/iiif/collection/' . $itemSet->id() . ',' . $item->id());
        $this->assertResponseStatusCode(200);
    }

    public function testListActionWithOneIdCanBeAccessed(): void
    {
        $itemSet = $this->createItemSet();
        $this->dispatch('/iiif/collection/' . $itemSet->id() . ',');
        $this->assertResponseStatusCode(200);
    }
}
