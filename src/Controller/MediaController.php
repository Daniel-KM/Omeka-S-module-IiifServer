<?php
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
