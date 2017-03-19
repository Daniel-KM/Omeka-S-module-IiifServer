<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
 * Copyright 2016-2017 BibLibre
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

use \Exception;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\Manager as FileManager;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Mvc\Exception\NotFoundException;
use IiifServer\ImageServer;

/**
 * The Image controller class.
 *
 * @todo Move all image processing stuff in Image Server.
 *
 * @package IiifServer
 */
class ImageController extends AbstractActionController
{
    protected $fileManager;
    protected $moduleManager;
    protected $translator;
    protected $commandLineArgs;

    public function __construct(
        FileManager $fileManager,
        ModuleManager $moduleManager,
        TranslatorInterface $translator,
        array $commandLineArgs
    ) {
        $this->fileManager = $fileManager;
        $this->moduleManager = $moduleManager;
        $this->translator = $translator;
        $this->commandLineArgs = $commandLineArgs;
    }

    /**
     * Redirect to the 'info' action, required by the feature "baseUriRedirect".
     *
     * @see self::infoAction()
     */
    public function indexAction()
    {
        $id = $this->params('id');
        $this->redirect()->toRoute('iiifserver_image_info', array('id' => $id));
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
     * Send "info.json" for the current file.
     *
     * @internal The info is managed by the ImageControler because it indicates
     * capabilities of the IIIF server for the request of a file.
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

        $iiifInfo = $this->viewHelpers()->get('iiifInfo');
        $info = $iiifInfo($media);

        return $this->jsonLd($info);
    }

    /**
     * Returns sized image for the current file.
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

        // Check if the original file is an image.
        if (strpos($media->mediaType(), 'image/') !== 0) {
            $response->setStatusCode(501);
            $view = new ViewModel;
            $view->setVariable('message', $this->translate('The source file is not an image.'));
            $view->setTemplate('public/image/error');
            return $view;
        }

        // Check, clean and optimize and fill values according to the request.
        $this->_view = new ViewModel;
        $transform = $this->_cleanRequest($media);
        if (empty($transform)) {
            // The message is set in view.
            $response->setStatusCode(400);
            $this->_view->setTemplate('public/image/error');
            return $this->_view;
        }

        $settings = $this->settings();

        // Now, process the requested transformation if needed.
        $imageUrl = '';
        $imagePath = '';

        // A quick check when there is no transformation.
        if ($transform['region']['feature'] == 'full'
                && $transform['size']['feature'] == 'full'
                && $transform['mirror']['feature'] == 'default'
                && $transform['rotation']['feature'] == 'noRotation'
                && $transform['quality']['feature'] == 'default'
                && $transform['format']['feature'] == $media->mediaType()
            ) {
            $imageUrl = $media->originalUrl();
        }

        // A transformation is needed.
        else {
            // Quick check if an Omeka derivative is appropriate.
            $pretiled = $this->_useOmekaDerivative($media, $transform);
            if ($pretiled) {
                // Check if a light transformation is needed.
                if ($transform['size']['feature'] != 'full'
                        || $transform['mirror']['feature'] != 'default'
                        || $transform['rotation']['feature'] != 'noRotation'
                        || $transform['quality']['feature'] != 'default'
                        || $transform['format']['feature'] != $pretiled['media_type']
                    ) {
                    $args = $transform;
                    $args['source']['filepath'] = $pretiled['filepath'];
                    $args['source']['media_type'] = $pretiled['media_type'];
                    $args['source']['width'] = $pretiled['width'];
                    $args['source']['height'] = $pretiled['height'];
                    $args['region']['feature'] = 'full';
                    $args['region']['x'] = 0;
                    $args['region']['y'] = 0;
                    $args['region']['width'] = $pretiled['width'];
                    $args['region']['height'] = $pretiled['height'];
                    $imagePath = $this->_transformImage($args);
                }
                // No transformation.
                else {
                    $imageUrl = $media->thumbnailUrl($pretiled['derivativeType']);
                }
            }

            // Check if another image can be used.
            else {
                // Check if the image is pre-tiled.
                $pretiled = $this->_usePreTiled($media, $transform);
                if ($pretiled) {
                    // Warning: Currently, the tile server does not manage
                    // regions or special size, so it is possible to process the
                    // crop of an overlap in one transformation.

                    // Check if a light transformation is needed (all except
                    // extraction of the region).
                    if (($pretiled['overlap'] && !$pretiled['isSingleCell'])
                            || $transform['mirror']['feature'] != 'default'
                            || $transform['rotation']['feature'] != 'noRotation'
                            || $transform['quality']['feature'] != 'default'
                            || $transform['format']['feature'] != $pretiled['media_type']
                        ) {
                        $args = $transform;
                        $args['source']['filepath'] = $pretiled['filepath'];
                        $args['source']['media_type'] = $pretiled['media_type'];
                        $args['source']['width'] = $pretiled['width'];
                        $args['source']['height'] = $pretiled['height'];
                        // The tile server returns always the true tile, so crop
                        // it when there is an overlap.
                        if ($pretiled['overlap']) {
                            $args['region']['feature'] = 'regionByPx';
                            $args['region']['x'] = $pretiled['isFirstColumn'] ? 0 : $pretiled['overlap'];
                            $args['region']['y'] = $pretiled['isFirstRow'] ? 0 : $pretiled['overlap'];
                            $args['region']['width'] = $pretiled['size'];
                            $args['region']['height'] = $pretiled['size'];
                        }
                        // Normal tile.
                        else {
                            $args['region']['feature'] = 'full';
                            $args['region']['x'] = 0;
                            $args['region']['y'] = 0;
                            $args['region']['width'] = $pretiled['width'];
                            $args['region']['height'] = $pretiled['height'];
                        }
                        $args['size']['feature'] = 'full';
                        $imagePath = $this->_transformImage($args);
                    }
                    // No transformation.
                    else {
                        $imageUrl = $pretiled['fileurl'];
                    }
                }

                // The image needs to be transformed dynamically.
                else {
                    $maxFileSize = $settings->get('iiifserver_image_max_size');
                    if (!empty($maxFileSize) && $this->_mediaFileSize($media) > $maxFileSize) {
                        $response->setStatusCode(500);
                        $view = new ViewModel;
                        $view->setVariable('message', $this->translate('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the file is not tiled for dynamic processing.'));
                        $view->setTemplate('public/image/error');
                        return $view;
                    }
                    $imagePath = $this->_transformImage($transform);
                }
            }
        }

        // Redirect to the url when an existing file is available.
        if ($imageUrl) {
            // Header for CORS, required for access of IIIF.
            $response->getHeaders()->addHeaderLine('access-control-allow-origin', '*');
            // Recommanded by feature "profileLinkHeader".
            $response->getHeaders()->addHeaderLine('Link', '<http://iiif.io/api/image/2/level2.json>;rel="profile"');
            $response->getHeaders()->addHeaderLine('Content-Type', $transform['format']['feature']);

            // Redirect (302/307) to the url of the file.
            // TODO This is a local file (normal server, except iiip server): use 200.
            return $this->redirect()->toUrl($imageUrl);
        }

        //This is a transformed file.
        elseif ($imagePath) {
            $output = file_get_contents($imagePath);
            unlink($imagePath);

            if (empty($output)) {
                $response->setStatusCode(500);
                $view = new ViewModel;
                $view->setVariable('message', $this->translate('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found or empty.'));
                $view->setTemplate('public/image/error');
                return $view;
            }

            // Header for CORS, required for access of IIIF.
            $response->getHeaders()->addHeaderLine('access-control-allow-origin', '*');
            // Recommanded by feature "profileLinkHeader".
            $response->getHeaders()->addHeaderLine('Link', '<http://iiif.io/api/image/2/level2.json>;rel="profile"');
            $response->getHeaders()->addHeaderLine('Content-Type', $transform['format']['feature']);

            $response->setContent($output);
            return $response;
        }

        // No result.
        else {
            $response->setStatusCode(500);
            $view = new ViewModel;
            $view->setVariable('message', $this->translate('The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is empty or not found.'));
            $view->setTemplate('public/image/error');
            return $view;
        }
    }

    protected function _mediaFileSize(MediaRepresentation $media)
    {
        $filepath = $this->_mediaFilePath($media);
        return filesize($filepath);
    }

    protected function _mediaFilePath(MediaRepresentation $media, $imageType = 'original')
    {
        if ($imageType == 'original') {
            $storagePath = $this->fileManager->getStoragePath($imageType, $media->filename());
        } else {
            $basename = $this->fileManager->getBasename($media->filename());
            $storagePath = $this->fileManager->getStoragePath($imageType, $basename, FileManager::THUMBNAIL_EXTENSION);
        }
        $filepath = OMEKA_PATH
            . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . $storagePath;

        return $filepath;
    }

    /**
     * Check, clean and optimize the request for quicker transformation.
     *
     * @todo Move the maximum of checks in the Image Server.
     *
     * @param MediaRepresentation $media
     * @return array|null Array of cleaned requested image, else null.
     */
    protected function _cleanRequest(MediaRepresentation $media)
    {
        $transform = array();

        $transform['source']['filepath'] = $this->_getImagePath($media, 'original');
        $transform['source']['media_type'] = $media->mediaType();

        list($sourceWidth, $sourceHeight) = array_values($this->_getImageSize($media, 'original'));
        $transform['source']['width'] = $sourceWidth;
        $transform['source']['height'] = $sourceHeight;

        // The regex in the route implies that all requests are valid (no 501),
        // but may be bad formatted (400).

        $region = $this->params('region');
        $size = $this->params('size');
        $rotation = $this->params('rotation');
        $quality = $this->params('quality');
        $format = $this->params('format');

        // Determine the region.

        // Full image.
        if ($region == 'full') {
            $transform['region']['feature'] = 'full';
            // Next values may be needed for next parameters.
            $transform['region']['x'] = 0;
            $transform['region']['y'] = 0;
            $transform['region']['width'] = $sourceWidth;
            $transform['region']['height'] = $sourceHeight;
        }

        // "pct:x,y,w,h": regionByPct
        elseif (strpos($region, 'pct:') === 0) {
            $regionValues = explode(',', substr($region, 4));
            if (count($regionValues) != 4) {
                $this->_view->setVariable('message', sprintf($this->translate('The IIIF server cannot fulfill the request: the region "%s" is incorrect.'), $region));
                return;
            }
            $regionValues = array_map('floatval', $regionValues);
            // A quick check to avoid a possible transformation.
            if ($regionValues[0] == 0
                    && $regionValues[1] == 0
                    && $regionValues[2] == 100
                    && $regionValues[3] == 100
                ) {
                $transform['region']['feature'] = 'full';
                // Next values may be needed for next parameters.
                $transform['region']['x'] = 0;
                $transform['region']['y'] = 0;
                $transform['region']['width'] = $sourceWidth;
                $transform['region']['height'] = $sourceHeight;
            }
            // Normal region.
            else {
                $transform['region']['feature'] = 'regionByPct';
                $transform['region']['x'] = $regionValues[0];
                $transform['region']['y'] = $regionValues[1];
                $transform['region']['width'] = $regionValues[2];
                $transform['region']['height'] = $regionValues[3];
            }
        }

        // "x,y,w,h": regionByPx.
        else {
            $regionValues = explode(',', $region);
            if (count($regionValues) != 4) {
                $this->_view->setVariable('message', sprintf($this->translate('The IIIF server cannot fulfill the request: the region "%s" is incorrect.'), $region));
                return;
            }
            $regionValues = array_map('intval', $regionValues);
            // A quick check to avoid a possible transformation.
            if ($regionValues[0] == 0
                    && $regionValues[1] == 0
                    && $regionValues[2] == $sourceWidth
                    && $regionValues[3] == $sourceHeight
                ) {
                $transform['region']['feature'] = 'full';
                // Next values may be needed for next parameters.
                $transform['region']['x'] = 0;
                $transform['region']['y'] = 0;
                $transform['region']['width'] = $sourceWidth;
                $transform['region']['height'] = $sourceHeight;
            }
            // Normal region.
            else {
                $transform['region']['feature'] = 'regionByPx';
                $transform['region']['x'] = $regionValues[0];
                $transform['region']['y'] = $regionValues[1];
                $transform['region']['width'] = $regionValues[2];
                $transform['region']['height'] = $regionValues[3];
            }
        }

        // Determine the size.

        // Full image.
        if ($size == 'full') {
            $transform['size']['feature'] = 'full';
        }

        // "pct:x": sizeByPct
        elseif (strpos($size, 'pct:') === 0) {
            $sizePercentage = floatval(substr($size, 4));
            if (empty($sizePercentage) || $sizePercentage > 100) {
                $this->_view->setVariable('message', sprintf($this->translate('The IIIF server cannot fulfill the request: the size "%s" is incorrect.'), $size));
                return;
            }
            // A quick check to avoid a possible transformation.
            if ($sizePercentage == 100) {
                $transform['size']['feature'] = 'full';
            }
            // Normal size.
            else {
                $transform['size']['feature'] = 'sizeByPct';
                $transform['size']['percentage'] = $sizePercentage;
            }
        }

        // "!w,h": sizeByWh
        elseif (strpos($size, '!') === 0) {
            $pos = strpos($size, ',');
            $destinationWidth = (integer) substr($size, 1, $pos);
            $destinationHeight = (integer) substr($size, $pos + 1);
            if (empty($destinationWidth) || empty($destinationHeight)) {
                $this->_view->setVariable('message', sprintf($this->translate('The IIIF server cannot fulfill the request: the size "%s" is incorrect.'), $size));
                return;
            }
            // A quick check to avoid a possible transformation.
            if ($destinationWidth == $transform['region']['width']
                    && $destinationHeight == $transform['region']['width']
                ) {
                $transform['size']['feature'] = 'full';
            }
            // Normal size.
            else {
                $transform['size']['feature'] = 'sizeByWh';
                $transform['size']['width'] = $destinationWidth;
                $transform['size']['height'] = $destinationHeight;
            }
        }

        // "w,h", "w," or ",h".
        else {
            $pos = strpos($size, ',');
            $destinationWidth = (integer) substr($size, 0, $pos);
            $destinationHeight = (integer) substr($size, $pos + 1);
            if (empty($destinationWidth) && empty($destinationHeight)) {
                $this->_view->setVariable('message', sprintf($this->translate('The IIIF server cannot fulfill the request: the size "%s" is incorrect.'), $size));
                return;
            }

            // "w,h": sizeByWhListed or sizeByForcedWh.
            if ($destinationWidth && $destinationHeight) {
                // Check the size only if the region is full, else it's forced.
                if ($transform['region']['feature'] == 'full') {
                    $availableTypes = array('square', 'medium', 'large', 'original');
                    foreach ($availableTypes as $imageType) {
                        $filepath = $this->_getImagePath($media, $imageType);
                        if ($filepath) {
                            list($testWidth, $testHeight) = array_values($this->_getImageSize($media, $imageType));
                            if ($destinationWidth == $testWidth && $destinationHeight == $testHeight) {
                                $transform['size']['feature'] = 'full';
                                // Change the source file to avoid a transformation.
                                // TODO Check the format?
                                if ($imageType != 'original') {
                                    $transform['source']['filepath'] = $filepath;
                                    $transform['source']['media_type'] = 'image/jpeg';
                                    $transform['source']['width'] = $testWidth;
                                    $transform['source']['height'] = $testHeight;
                                }
                                break;
                            }
                        }
                    }
                }
                if (empty($transform['size']['feature'])) {
                    $transform['size']['feature'] = 'sizeByForcedWh';
                    $transform['size']['width'] = $destinationWidth;
                    $transform['size']['height'] = $destinationHeight;
                }
            }

            // "w,": sizeByW.
            elseif ($destinationWidth && empty($destinationHeight)) {
                $transform['size']['feature'] = 'sizeByW';
                $transform['size']['width'] = $destinationWidth;
            }

            // ",h": sizeByH.
            elseif (empty($destinationWidth) && $destinationHeight) {
                $transform['size']['feature'] = 'sizeByH';
                $transform['size']['height'] = $destinationHeight;
            }

            // Not supported.
            else {
                $this->_view->setVariable('message', sprintf($this->translate('The IIIF server cannot fulfill the request: the size "%s" is not supported.'), $size));
                return;
            }

            // A quick check to avoid a possible transformation.
            if (isset($transform['size']['width']) && empty($transform['size']['width'])
                    || isset($transform['size']['height']) && empty($transform['size']['height'])
                ) {
                $this->_view->setVariable('message', sprintf($this->translate('The IIIF server cannot fulfill the request: the size "%s" is not supported.'), $size));
                return;
            }
        }

        // Determine the mirroring and the rotation.

        $transform['mirror']['feature'] = substr($rotation, 0, 1) === '!' ? 'mirror' : 'default';
        if ($transform['mirror']['feature'] != 'default') {
            $rotation = substr($rotation, 1);
        }

        // Strip leading and ending zeros.
        if (strpos($rotation, '.') === false) {
            $rotation += 0;
        }
        // This may be a float, so keep all digits, because they can be managed
        // by the image server.
        else {
            $rotation = trim($rotation, '0');
            $rotationDotPos = strpos($rotation, '.');
            if ($rotationDotPos === strlen($rotation)) {
                $rotation = (integer) trim($rotation, '.');
            } elseif ($rotationDotPos === 0) {
                $rotation = '0' . $rotation;
            }
        }

        // No rotation.
        if (empty($rotation)) {
            $transform['rotation']['feature'] = 'noRotation';
        }

        // Simple rotation.
        elseif ($rotation == 90 || $rotation == 180 || $rotation == 270)  {
            $transform['rotation']['feature'] = 'rotationBy90s';
            $transform['rotation']['degrees'] = $rotation;
        }

        // Arbitrary rotation.
        else {
            $transform['rotation']['feature'] = 'rotationArbitrary';
            $transform['rotation']['degrees'] = $rotation;
        }

        // Determine the quality.
        // The regex in route checks it.
        $transform['quality']['feature'] = $quality;

        // Determine the format.
        // The regex in route checks it.
        $mediaTypes = array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'tif' => 'image/tiff',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'jp2' => 'image/jp2',
            'webp' => 'image/webp',
        );
        $transform['format']['feature'] = $mediaTypes[$format];

        return $transform;
    }

    /**
     * Get a pre tiled image from Omeka derivatives.
     *
     * Omeka derivative are light and basic pretiled files, that can be used for
     * a request of a full region as a fullsize.
     * @todo To be improved. Currently, thumbnails are not used.
     *
     * @param MediaRepresentation $file
     * @param array $transform
     * @return array|null Associative array with the file path, the derivative
     * type, the width and the height. Null if none.
     */
    protected function _useOmekaDerivative(MediaRepresentation $media, $transform)
    {
        // Some requirements to get tiles.
        if ($transform['region']['feature'] != 'full') {
            return;
        }

        // Check size. Here, the "full" is already checked.
        $useDerivativePath = false;

        // Currently, the check is done only on fullsize.
        $derivativeType = 'large';
        list($derivativeWidth, $derivativeHeight) = array_values($this->_getImageSize($media, $derivativeType));
        switch ($transform['size']['feature']) {
            case 'sizeByW':
            case 'sizeByH':
                $constraint = $transform['size']['feature'] == 'sizeByW'
                    ? $transform['size']['width']
                    : $transform['size']['height'];

                // Check if width is lower than fulllsize or thumbnail.
                // Omeka and IIIF doesn't use the same type of constraint, so
                // a double check is done.
                // TODO To be improved.
                if ($constraint <= $derivativeWidth || $constraint <= $derivativeHeight) {
                    $useDerivativePath = true;
                }
                break;

            case 'sizeByWh':
            case 'sizeByWhListed':
            case 'sizeByForcedWh':
                $constraintW = $transform['size']['width'];
                $constraintH = $transform['size']['height'];

                // Check if width is lower than fulllsize or thumbnail.
                if ($constraintW <= $derivativeWidth || $constraintH <= $derivativeHeight) {
                    $useDerivativePath = true;
                }
                break;

            case 'sizeByPct':
                if ($transform['size']['percentage'] <= ($derivativeWidth * 100 / $transform['source']['width'])) {
                    $useDerivativePath = true;
                }
                break;

            case 'full':
                // Not possible to use a derivative, because the region is full.
            default:
                return;
        }

        if ($useDerivativePath) {
            $derivativePath = $this->_getImagePath($media, $derivativeType);

            return array(
                'filepath' => $derivativePath,
                'derivativeType' => $derivativeType,
                'media_type' => 'image/jpeg',
                'width' => $derivativeWidth,
                'height' => $derivativeHeight,
            );
        }
    }

    /**
     * Get a pre tiled image.
     *
     * @todo Prebuild tiles directly with the IIIF standard (same type of url).
     *
     * @param MediaRepresentation $media
     * @param array $transform
     * @return array|null Associative array with the file path, the derivative
     * type, the width and the height. Null if none.
     */
    protected function _usePreTiled(MediaRepresentation $media, $transform)
    {
        $tileInfo = $this->tileInfo($media);
        if ($tileInfo) {
            $tile = $this->tileServer($tileInfo, $transform);
            return $tile;
        }
    }

    /**
     * Transform a file according to parameters.
     *
     * @param array $args Contains the filepath and the parameters.
     * @return string|null The filepath to the temp image if success.
     */
    protected function _transformImage($args)
    {
        $imageServer = new ImageServer($this->fileManager, $this->commandLineArgs, $this->settings());
        $imageServer->setLogger($this->logger());
        $imageServer->setTranslator($this->translator);

        return $imageServer->transform($args);
    }

    /**
     * Get an array of the width and height of the image file.
     *
     * @param MediaRepresentation $media
     * @param string $imageType
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     *
     * @see UniversalViewer_View_Helper_IiifManifest::_getImageSize()
     * @see UniversalViewer_View_Helper_IiifInfo::_getImageSize()
     * @see UniversalViewer_ImageController::_getImageSize()
     * @todo Refactorize.
     */
    protected function _getImageSize(MediaRepresentation $media, $imageType = 'original')
    {
        // Check if this is an image.
        if (empty($media) || strpos($media->mediaType(), 'image/') !== 0) {
            return array(
                'width' => null,
                'height' => null,
            );
        }

        // The storage adapter should be checked for external storage.
        if ($imageType == 'original') {
            $storagePath = $this->fileManager->getStoragePath($imageType, $media->filename());
        } else {
            $basename = $this->fileManager->getBasename($media->filename());
            $storagePath = $this->fileManager->getStoragePath($imageType, $basename, FileManager::THUMBNAIL_EXTENSION);
        }
        $filepath = OMEKA_PATH . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->_getWidthAndHeight($filepath);

        if (empty($result['width']) || empty($result['height'])) {
            throw new Exception("Failed to get image resolution: $filepath");
        }

        return $result;
    }

    /**
     * Get the path to an original or derivative file for an image.
     *
     * @param File $file
     * @param string $derivativeType
     * @return string|null Null if not exists.
     * @see UniversalViewer_View_Helper_IiifInfo::_getImagePath()
     */
    protected function _getImagePath($media, $derivativeType = 'original')
    {
        // Check if the file is an image.
        if (strpos($media->mediaType(), 'image/') === 0) {
            // Don't use the webpath to avoid the transfer through server.
            $filepath = $this->_mediaFilePath($media, $derivativeType);
            if (file_exists($filepath)) {
                return $filepath;
            }
            // Use the web url when an external storage is used. No check can be
            // done.
            // TODO Load locally the external path? It will be done later.
            else {
                $filepath = $media->thumbnailUrl($derivativeType);
                return $filepath;
            }
        }
    }

    /**
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     * @see UniversalViewer_View_Helper_IiifInfo::_getWidthAndHeight()
     */
    protected function _getWidthAndHeight($filepath)
    {
        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $file = $this->fileManager->getTempFile();
            $tempPath = $file->getTempPath();
            $file->delete();
            $result = file_put_contents($tempPath, $filepath);
            if ($result !== false) {
                list($width, $height) = getimagesize($tempPath);
                unlink($tempPath);
                return array(
                    'width' => $width,
                    'height' => $height,
                );
            }
            unlink($tempPath);
        }
        // A normal path.
        elseif (file_exists($filepath)) {
            list($width, $height) = getimagesize($filepath);
            return array(
                'width' => $width,
                'height' => $height,
            );
        }

        return array(
            'width' => null,
            'height' => null,
        );
    }
}
