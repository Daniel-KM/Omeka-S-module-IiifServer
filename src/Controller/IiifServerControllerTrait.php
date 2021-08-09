<?php declare(strict_types=1);

/*
 * Copyright 2021 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace IiifServer\Controller;

use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Exception\BadRequestException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Stdlib\Message;

trait IiifServerControllerTrait
{
    public function indexAction()
    {
        return $this->jsonError(new BadRequestException(), \Laminas\Http\Response::STATUS_CODE_404);
    }

    /**
     * Returns an error 400 to requests that are invalid.
     */
    public function badAction()
    {
        return $this->viewError(new Message(
                'The Image server cannot fulfill the request: the arguments are incorrect.' // @translate
            ),
            \Laminas\Http\Response::STATUS_CODE_400
        );
    }

    /**
     * Forward to the 'manifest' or redirect to the "info.json" action.
     *
     * The short/base urls of the manifest and the info.json have the same format,
     * so a check is required. The route to the info.json can be overridden but
     * the module Image Server or an external image server.
     *
     * @see \IiifServer\Controller\PresentationController::manifestAction()
     * @see \IiifServer\Controller\NoopServerController::infoAction()
     *
     * @todo Move check of manifest/info.json into a MVC listener.
     */
    public function idAction()
    {
        // Check if there is a resource in case of a forward or a mvc listener,
        // and if it is an item or a media (image or any other media).
        $params = $this->params()->fromRoute();
        $resource = array_key_exists('resource', $params)
            ? $params['resource'] ?? $this->fetchResource('media')
            : $this->fetchResource('resources');

        if (!$resource) {
            return $this->jsonError(new Message(
                'Media "%s" not found.', // @translate
                $this->params('id')
            ), \Laminas\Http\Response::STATUS_CODE_404);
        }

        $settings = $this->settings();

        // A redirect is not required for manifest.
        if (!($resource instanceof \Omeka\Api\Representation\MediaRepresentation)) {
            $params['action'] = 'manifest';
            $params['resource'] = $resource;
            // Quick add "/manifest".
            /** @var \Laminas\Uri\Http $requestUrl */
            $requestUri = $this->getRequest()->getUri();
            $requestUri->setPath(rtrim($requestUri->getPath()) . '/manifest');
            $this->getResponse()->getHeaders()
                ->addHeaderLine('Location', $requestUri->toString());
            return $this->forward()->dispatch(\IiifServer\Controller\PresentationController::class, $params);
        }

        // Don't only add "/info.json", but get the canonical url.
        $version = $this->requestedVersion()
            ?? $this->settings()->get('iiifserver_media_api_default_version', '2') ?: '2';
        $url = $this->url()->fromRoute($this->routeInfo, [
            'version' => $version,
            'prefix' => empty($params['prefix']) ? $settings->get('iiifserver_media_api_prefix', '') : $params['prefix'],
            'id' => $params['id'],
        ], ['force_canonical' => true]);
        $response = $this->getResponse();
        $response
            ->getHeaders()->addHeaderLine('Location', $url);
        // The iiif image api specification recommends 303, not 302.
        return $response
            ->setStatusCode(\Laminas\Http\Response::STATUS_CODE_303);
    }

    /**
     * Similar to \IiifServer\Controller\PresentationController::fetchResource().
     */
    protected function fetchResource(string $resourceType = 'resources'): ?AbstractResourceEntityRepresentation
    {
        $id = $this->params('id');

        // We don't know yet if it is a resource or a media.
        if ($resourceType === 'resources' || $resourceType === 'items') {
            $useCleanIdentifier = $this->useCleanIdentifier();
            if ($useCleanIdentifier) {
                $getResourceFromIdentifier = $this->viewHelpers()->get('getResourceFromIdentifier');
                $resource = $getResourceFromIdentifier($id);
                if ($resource) {
                    return $resource;
                }
            } else {
                try {
                    return $this->api()->read('resources', $id)->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                }
            }
        }

        if ($resourceType !== 'resources' && $resourceType !== 'media') {
            return null;
        }

        // It is a private resource, an unknown resource or a media, so do more
        // check for media.

        $identifierType = $this->settings()->get('iiifserver_media_api_identifier');
        switch ($identifierType) {
            default:
            case 'default':
                if ($resourceType === 'resources') {
                    return null;
                }
                $useCleanIdentifier = $this->useCleanIdentifier();
                if ($useCleanIdentifier) {
                    $getResourceFromIdentifier = $this->viewHelpers()->get('getResourceFromIdentifier');
                    $resource = $getResourceFromIdentifier($id, 'media');
                    if ($resource) {
                        return $resource;
                    }
                }
                // no break.
            case 'media_id':
                try {
                    return $this->api()->read('media', $id)->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
            case 'storage_id':
                try {
                    return $this->api()->read('media', ['storageId' => $id])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
            case 'filename':
                $id = str_replace(['%2F', '%2f'], ['/', '/'], $id);
                $extension = (string) pathinfo($id, PATHINFO_EXTENSION);
                $lengthExtension = strlen($extension);
                $storageId = $lengthExtension
                    ? substr($id, 0, strlen($id) - $lengthExtension - 1)
                    : $id;
                try {
                    return $this->api()->read('media', ['storageId' => $storageId, 'extension' => $extension])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
        }
        return null;
    }

    protected function useCleanIdentifier(): bool
    {
        return $this->viewHelpers()->has('getResourcesFromIdentifiers')
            && $this->settings()->get('iiifserver_identifier_clean');
    }

    protected function requestedVersion(): ?string
    {
        // Check the version from the url first.
        $version = $this->params('version');
        if ($version === '2' || $version === '3') {
            return $version;
        }

        $accept = $this->getRequest()->getHeaders()->get('Accept')->toString();
        if (strpos($accept, 'iiif.io/api/presentation/3/context.json')
            || strpos($accept, 'iiif.io/api/image/3/context.json')
        ) {
            return '3';
        }
        if (strpos($accept, 'iiif.io/api/presentation/2/context.json')
            || strpos($accept, 'iiif.io/api/image/2/context.json')
        ) {
            return '2';
        }
        return null;
    }

    protected function jsonError($exceptionOrMessage, $statusCode = 500): JsonModel
    {
        $this->getResponse()->setStatusCode($statusCode);
        return new JsonModel([
            'status' => 'error',
            'message' => $this->viewMessage($exceptionOrMessage),
        ]);
    }

    /**
     * @see https://iiif.io/api/image/3.0/#73-error-conditions
     */
    protected function viewError($exceptionOrMessage, $statusCode = 500): ViewModel
    {
        $this->getResponse()->setStatusCode($statusCode);
        $view = new ViewModel([
            'message' => $this->viewMessage($exceptionOrMessage),
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('iiif-server/error');
    }

    protected function viewMessage($exceptionOrMessage): string
    {
        if (is_object($exceptionOrMessage)) {
            if ($exceptionOrMessage instanceof \Exception) {
                return $exceptionOrMessage->getMessage();
            }
            if ($exceptionOrMessage instanceof Message) {
                return (string) sprintf($this->translate($exceptionOrMessage->getMessage()), ...$exceptionOrMessage->getArgs());
            }
        }
        return $this->translate((string) $exceptionOrMessage);
    }
}
