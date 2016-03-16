<?php

namespace UniversalViewerTest\Controller;

use UniversalViewerTest\Controller\UniversalViewerControllerTestCase;

class PlayerControllerTest extends UniversalViewerControllerTestCase
{
    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('/items/play/' . $this->item->id());

        $this->assertResponseStatusCode(200);
    }
}
