<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class IiifServerControllerTestCase extends OmekaControllerTestCase
{
    protected $item;
    protected $itemSet;

    public function setUp(): void
    {
        $this->loginAsAdmin();

        $response = $this->api()->create('items');
        $this->item = $response->getContent();

        $response = $this->api()->create('item_sets');
        $this->itemSet = $response->getContent();
    }

    public function tearDown(): void
    {
        $this->api()->delete('items', $this->item->id());
        $this->api()->delete('item_sets', $this->itemSet->id());
    }
}
