<?php

/*
 * Copyright 2015  Daniel Berthereau
 * Copyright 2016  BibLibre
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

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
            // TODO Remove json ld keys if client ask json.
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
