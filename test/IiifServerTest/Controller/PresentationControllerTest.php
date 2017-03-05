<?php

namespace IiifServerTest\Controller;

use IiifServerTest\Controller\IiifServerControllerTestCase;

class PresentationControllerTest extends IiifServerControllerTestCase
{
    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('/item/' . $this->item->id() . '/manifest');

        $this->assertResponseStatusCode(200);
    }
}
