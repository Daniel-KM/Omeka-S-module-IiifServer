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

namespace UniversalViewer\Controller;

use Omeka\Mvc\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

/**
 * The Media controller class.
 *
 * @package UniversalViewer
 */
class MediaController extends AbstractActionController
{
    /**
     * Redirect to the 'info' action, required by the feature "baseUriRedirect".
     *
     * @see self::infoAction()
     */
    public function indexAction()
    {
        $id = $this->params('id');
        $this->redirect()->toRoute('universalviewer_media_info', array('id' => $id));
    }

    /**
     * Send "info.json" for the current file.
     *
     * @internal The info is managed by the MediaControler because it indicates
     * capabilities of the IXIF server for the request of a file.
     */
    public function infoAction()
    {
        $id = $this->params('id');
        if (empty($id)) {
            throw new NotFoundException;
        }

        $response = $this->api()->read('media', $id);
        $media = $response->getContent();
        if (empty($media)) {
            throw new NotFoundException;
        }

        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $iiifInfo = $viewHelperManager->get('iiifInfo');
        $info = $iiifInfo($media, false);

        return $this->jsonLd($info);
    }

    /**
     * Returns an error 400 to requests that are invalid.
     */
    public function badAction()
    {
        $response = $this->getResponse();

        $response->setStatusCode(400);

        $view = new ViewModel;
        $view->setVariable('message', $this->translate('The IIIF server cannot fulfill the request: the arguments are incorrect.'));
        $view->setTemplate('public/image/error');

        return $view;
    }

    /**
     * Returns the current file.
     */
    public function fetchAction()
    {
        $id = $this->params('id');

        $response = $this->api()->read('media', $id);
        $media = $response->getContent();
        if (empty($media)) {
            throw new NotFoundException;
        }

        $response = $this->getResponse();

        // Because there is no conversion currently, the format should be
        // checked.
        $format = strtolower($this->params('format'));
        if (pathinfo($media->filename(), PATHINFO_EXTENSION) != $format) {
            $response->setStatusCode(500);

            $view = new viewModel;
            $view->setVariable('message', $this->translate('The IXIF server encountered an unexpected error that prevented it from fulfilling the request: the requested format is not supported.'));
            $view->setTemplate('public/image/error');

            return $view;
        }

        // The source can be a local file or an external one (Amazon S3).
        // A check can be added if the file is local.
        $fileManager = $this->getServiceLocator()->get('Omeka\File\Manager');
        $store = $fileManager->getStore();
        if (get_class($store) == 'LocalStore') {
            $filepath = $fileManager->getStoragePath('original', $media->filename());
            if (!file_exists($filepath) || filesize($filepath) == 0) {
                $response->setStatusCode(500);

                $view = new ViewModel;
                $view->setVariable('message', $this->translate('The IXIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found.'));
                $view->setTemplate('public/image/error');

                return $view;
            }
        }
        // TODO Check if the external url is not empty.

        // Header for CORS, required for access of IXIF.
        $response->getHeaders()->addHeaderLine('access-control-allow-origin', '*');
        $response->getHeaders()->addHeaderLine('Content-Type', $media->mediaType());

        // TODO This is a local file (normal server): use 200.

        // Redirect (302/307) to the url of the file.
        $fileurl = $media->originalUrl();
        return $this->redirect()->toUrl($fileurl);
    }
}
