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

namespace IiifServer\View\Helper;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\Manager as FileManager;
use Zend\View\Helper\AbstractHelper;
use IiifServer\Mvc\Controller\Plugin\TileInfo;

/**
 * Helper to get a IIIF info.json for a file.
 */
class IiifInfo extends AbstractHelper
{
    protected $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Get the IIIF info for the specified record.
     *
     * @todo Replace all data by standard classes.
     *
     * @param Record|int|null $record
     * @return Object|null
     */
    public function __invoke(MediaRepresentation $media)
    {
        if (empty($media)) {
            return null;
        }

        if (strpos($media->mediaType(), 'image/') === 0) {
            $sizes = [];
            $availableTypes = ['medium', 'large', 'original'];
            foreach ($availableTypes as $imageType) {
                $imageSize = $this->_getImageSize($media, $imageType);
                $size = [];
                $size['width'] = $imageSize['width'];
                $size['height'] = $imageSize['height'];
                $size = (object) $size;
                $sizes[] = $size;
            }

            $imageType = 'original';
            $imageSize = $this->_getImageSize($media, $imageType);
            list($width, $height) = array_values($imageSize);
            $imageUrl = $this->view->url(
                'iiifserver_image',
                ['id' => $media->id()],
                ['force_canonical' => true]
            );
            $imageUrl = $this->view->iiifForceHttpsIfRequired($imageUrl);

            $tiles = [];
            $tileInfo = new TileInfo();
            $tilingData = $tileInfo($media);
            if ($tilingData) {
                $iiifTileInfo = $this->iiifTileInfo($tilingData);
                if ($iiifTileInfo) {
                    $tiles[] = $iiifTileInfo;
                }
            }

            $profile = [];
            $profile[] = 'http://iiif.io/api/image/2/level2.json';
            // Temporary fix. See https://github.com/UniversalViewer/universalviewer/issues/438.
            $profile[] = [];
            // According to specifications, the profile details should be omitted,
            // because only default formats, qualities and supports are supported
            // currently.
            /*
            $profileDetails = array();
            $profileDetails['format'] = array('jpg');
            $profileDetails['qualities'] = array('default');
            $profileDetails['supports'] = array('sizeByWhListed');
            $profileDetails = (object) $profileDetails;
            $profile[] = $profileDetails;
            */

            // Exemple of service, useless currently.
            /*
            $service = array();
            $service['@context'] = 'http://iiif.io/api/annex/service/physdim/1/context.json';
            $service['profile'] = 'http://iiif.io/api/annex/service/physdim';
            $service['physicalScale'] = 0.0025;
            $service['physicalUnits'] = 'in';
            $service = (object) $service;
            */

            $info = [];
            $info['@context'] = 'http://iiif.io/api/image/2/context.json';
            $info['@id'] = $imageUrl;
            $info['protocol'] = 'http://iiif.io/api/image';
            $info['width'] = $width;
            $info['height'] = $height;
            $info['sizes'] = $sizes;
            if ($tiles) {
                $info['tiles'] = $tiles;
            }
            $info['profile'] = $profile;
            // Useless currently.
            // $info['service'] = $service;
        }

        // Else non-image file.
        else {
            $info = [];
            $info['@context'] = [
                'http://iiif.io/api/presentation/2/context.json',
                // See MediaController::contextAction()
                'http://wellcomelibrary.org/ld/ixif/0/context.json',
                // WEB_ROOT . '/ld/ixif/0/context.json',
            ];
            $fileUrl = $this->view->url(
                'iiifserver_media',
                ['id' => $media->id()],
                ['force_canonical' => true]
            );
            $fileUrl = $this->view->iiifForceHttpsIfRequired($fileUrl);
            $info['@id'] = $fileUrl;
            // See MediaController::contextAction()
            $info['protocol'] = 'http://wellcomelibrary.org/ld/ixif';
        }

        $info = (object) $info;
        return $info;
    }

    /**
     * Create the data for a IIIF tile object.
     *
     * @param array $tileInfo
     * @return array|null
     */
    protected function iiifTileInfo($tileInfo)
    {
        $tile = [];

        $squaleFactors = [];
        $maxSize = max($tileInfo['source']['width'], $tileInfo['source']['height']);
        $tileSize = $tileInfo['size'];
        $total = (integer) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $squaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($squaleFactors) <= 1) {
            return;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;
        return $tile;
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
            return [
                'width' => null,
                'height' => null,
            ];
        }

        // The storage adapter should be checked for external storage.
        if ($imageType == 'original') {
            $storagePath = $this->fileManager->getStoragePath($imageType, $media->filename());
        } else {
            $storagePath = $this->fileManager->getStoragePath($imageType, $media->storageId(), FileManager::THUMBNAIL_EXTENSION);
        }
        $filepath = OMEKA_PATH
            . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->_getWidthAndHeight($filepath);

        if (empty($result['width']) || empty($result['height'])) {
            throw new \Exception("Failed to get image resolution: $filepath");
        }

        return $result;
    }

    /**
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     * @see IiifServer\Controller\ImageController::_getWidthAndHeight()
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
                return [
                    'width' => $width,
                    'height' => $height,
                ];
            }
            unlink($tempPath);
        }
        // A normal path.
        elseif (file_exists($filepath)) {
            list($width, $height) = getimagesize($filepath);
            return [
                'width' => $width,
                'height' => $height,
            ];
        }

        return [
            'width' => null,
            'height' => null,
        ];
    }
}
