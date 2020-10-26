<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class PresentationControllerTest extends IiifServerControllerTestCase
{
    public function testIndexActionCanBeAccessed(): void
    {
        $this->dispatch('/iiif/' . $this->item->id());
        $this->assertResponseStatusCode(200);
    }

    public function testIndexActionWithInvalidIdCannotBeAccessed(): void
    {
        $this->dispatch('/iiif/' . 999999);
        $this->assertResponseStatusCode(404);
    }

    public function testManifestActionCanBeAccessed(): void
    {
        $this->dispatch('/iiif/' . $this->item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
    }

    public function testManifestActionWithInvalidIdCannotBeAccessed(): void
    {
        $this->dispatch('/iiif/' . 999999 . '/manifest');
        $this->assertResponseStatusCode(404);
    }

    public function testCollectionActionCanBeAccessed(): void
    {
        $this->dispatch('/iiif/collection/' . $this->itemSet->id());
        $this->assertResponseStatusCode(200);
    }

    public function testCollectionActionWithInvalidIdCannotBeAccessed(): void
    {
        $this->dispatch('/iiif/collection/' . 999999);
        $this->assertResponseStatusCode(404);
    }

    public function testListActionCanBeAccessed(): void
    {
        $this->dispatch('/iiif/collection/' . $this->itemSet->id() . ',' . $this->item->id());
        $this->assertResponseStatusCode(200);
    }

    public function testListActionWithOneIdCanBeAccessed(): void
    {
        $this->dispatch('/iiif/collection/' . $this->itemSet->id() . ',');
        $this->assertResponseStatusCode(200);
    }
}
