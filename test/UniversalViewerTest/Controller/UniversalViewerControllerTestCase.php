<?php

namespace UniversalViewerTest\Controller;

use Omeka\Test\AbstractHttpControllerTestCase;

abstract class UniversalViewerControllerTestCase extends AbstractHttpControllerTestCase
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

    protected function loginAsAdmin()
    {
        $application = $this->getApplication();
        $serviceLocator = $application->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function api()
    {
        $serviceLocator = $this->getApplication()->getServiceManager();
        return $serviceLocator->get('Omeka\ApiManager');
    }
}
