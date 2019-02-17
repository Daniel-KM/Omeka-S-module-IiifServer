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

use IiifServer\Mvc\Controller\Plugin\TileInfo;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFileFactory;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get a IIIF info.json for a file.
 */
class IiifInfo extends AbstractHelper
{
    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * Full path to the files.
     *
     * @var string
     */
    protected $basePath;

    public function __construct(TempFileFactory $tempFileFactory, $basePath)
    {
        $this->tempFileFactory = $tempFileFactory;
        $this->basePath = $basePath;
    }

    /**
     * Get the IIIF info for the specified record.
     *
     * @todo Replace all data by standard classes.
     *
     * @see https://iiif.io/api/image/2.1
     *
     * @param MediaRepresentation|null $media
     * @return Object|null
     */
    public function __invoke(MediaRepresentation $media)
    {
        if (strpos($media->mediaType(), 'image/') === 0) {
            $view = $this->getView();
            $sizes = [];
            $availableTypes = ['medium', 'large', 'original'];
            foreach ($availableTypes as $imageType) {
                $imageSize = $view->imageSize($media, $imageType) ?: ['width' => null, 'height' => null];
                $size = [];
                $size['width'] = $imageSize['width'];
                $size['height'] = $imageSize['height'];
                $size = (object) $size;
                $sizes[] = $size;
            }

            $imageType = 'original';
            $imageSize = $view->imageSize($media, $imageType);
            list($width, $height) = $imageSize ? array_values($imageSize) : [null, null];
            $imageUrl = $this->view->url(
                'iiifserver_image',
                ['id' => $media->id()],
                ['force_canonical' => true]
            );
            $imageUrl = $this->view->iiifForceBaseUrlIfRequired($imageUrl);

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
            $profileDetails = [];
            $profileDetails['format'] = ['image/jpeg'];
            $profileDetails['qualities'] = ['default'];
            $profileDetails['supports'] = ['sizeByWhListed'];
            $profileDetails = (object) $profileDetails;
            $profile[] = $profileDetails;
            */

            // Exemple of service, useless currently.
            /*
            $service = [];
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
            $fileUrl = $this->view->iiifForceBaseUrlIfRequired($fileUrl);
            $info['@id'] = $fileUrl;
            // See MediaController::contextAction()
            $info['protocol'] = 'http://wellcomelibrary.org/ld/ixif';
        }

        // Give possibility to customize the info.json.
        // TODO Manifest (info) should be a true object, with many sub-objects.
        $manifest = &$info;
        $resource = $media;
        $type = 'file';
        $triggerHelper = $this->view->plugin('trigger');
        $params = compact('manifest', 'resource', 'type');
        $params = $triggerHelper('iiifserver.manifest', $params, true);
        $info = $params['manifest'];

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
        $total = (int) ceil($maxSize / $tileSize);
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
}
