<?php
namespace UniversalViewer\Controller;

use Omeka\Mvc\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class PresentationController extends AbstractActionController
{
    /**
     * Forward to the 'play' action
     *
     * @see self::playAction()
     */
    public function indexAction()
    {
        $this->forward('manifest');
    }

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

        return $this->_sendJson($manifest);
    }

    protected function _sendJson($data)
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        // According to specification, the response should be json, except if
        // client asks json-ld.
        $accept = $request->getHeader('Accept');
        if ($accept->hasMediaType('application/ld+json')) {
            $response->getHeaders()->addHeaderLine('Content-Type', 'application/ld+json; charset=utf-8', true);
        }
        // Default to json with a link to json-ld.
        else {
            $response->getHeaders()->addHeaderLine('Content-Type', 'application/json; charset=utf-8', true);
            $response->getHeaders()->addHeaderLine('Link', '<http://iiif.io/api/image/2/context.json>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"', true);
       }

        // Header for CORS, required for access of IIIF.
        $response->getHeaders()->addHeaderLine('access-control-allow-origin', '*');
        //$response->clearBody();
        $body = version_compare(phpversion(), '5.4.0', '<')
            ? json_encode($data)
            : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->setContent($body);
        return $response;
    }

}
