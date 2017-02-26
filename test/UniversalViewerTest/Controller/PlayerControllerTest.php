<?php

namespace UniversalViewerTest\Controller;

use UniversalViewerTest\Controller\UniversalViewerControllerTestCase;

class PlayerControllerTest extends UniversalViewerControllerTestCase
{
    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('/item/' . $this->item->id() . '/play');

        $this->assertResponseStatusCode(200);
    }
}
