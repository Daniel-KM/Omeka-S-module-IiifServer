<?php

namespace IiifServerTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class IiifServerControllerTestCase extends OmekaControllerTestCase
{
    protected $item;

    public function setUp()
    {
        $this->loginAsAdmin();

        $response = $this->api()->create('items');
        $this->item = $response->getContent();
    }

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->id());
    }
}
