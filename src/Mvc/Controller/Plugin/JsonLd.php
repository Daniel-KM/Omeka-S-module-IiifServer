<?php
namespace UniversalViewer\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class JsonLd extends AbstractPlugin
{
    public function __invoke($data)
    {
        $controller = $this->getController();

        $request = $controller->getRequest();
        $response = $controller->getResponse();

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
