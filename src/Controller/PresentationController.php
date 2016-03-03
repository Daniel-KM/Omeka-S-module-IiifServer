<?php
namespace UniversalViewer\Controller;

use Omeka\Mvc\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class PresentationController extends AbstractActionController
{
    public function manifestAction()
    {
        $id = $this->params('id');
        if (empty($id)) {
            throw new NotFoundException;
        }

        $recordtype = $this->params('recordtype');
        $response = $this->api()->read($recordtype, $id);
        $resource = $response->getContent();
        if (empty($resource)) {
            throw new NotFoundException;
        }

        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $iiifManifest = $viewHelperManager->get('iiifManifest');
        $manifest = $iiifManifest($resource, false);

        return $this->jsonLd($manifest);
    }
}
