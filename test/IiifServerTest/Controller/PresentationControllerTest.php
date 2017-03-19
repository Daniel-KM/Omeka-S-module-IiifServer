<?php

namespace IiifServerTest\Controller;

class PresentationControllerTest extends IiifServerControllerTestCase
{
    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('/iiif/' . $this->item->id());
        $this->assertResponseStatusCode(200);
    }

    public function testIndexActionWithInvalidIdCannotBeAccessed()
    {
        $this->dispatch('/iiif/' . 999999);
        $this->assertResponseStatusCode(404);
    }

    public function testManifestActionCanBeAccessed()
    {
        $this->dispatch('/iiif/' . $this->item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
    }

    public function testManifestActionWithInvalidIdCannotBeAccessed()
    {
        $this->dispatch('/iiif/' . 999999 . '/manifest');
        $this->assertResponseStatusCode(404);
    }

    public function testCollectionActionCanBeAccessed()
    {
        $this->dispatch('/iiif/collection/' . $this->itemSet->id());
        $this->assertResponseStatusCode(200);
    }

    public function testCollectionActionWithInvalidIdCannotBeAccessed()
    {
        $this->dispatch('/iiif/collection/' . 999999);
        $this->assertResponseStatusCode(404);
    }

    public function testListActionCanBeAccessed()
    {
        $this->dispatch('/iiif/collection/' . $this->itemSet->id() . ',' . $this->item->id());
        $this->assertResponseStatusCode(200);
    }

    public function testListActionWithOneIdCanBeAccessed()
    {
        $this->dispatch('/iiif/collection/' . $this->itemSet->id() . ',');
        $this->assertResponseStatusCode(200);
    }
}
