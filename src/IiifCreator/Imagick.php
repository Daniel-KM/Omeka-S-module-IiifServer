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

namespace UniversalViewer\IiifCreator;

use \Exception;
use Zend\ServiceManager\ServiceLocatorInterface;
use Omeka\File\Manager as FileManager;
use UniversalViewer\AbstractIiifCreator;

/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package UniversalViewer
 */
class Imagick extends AbstractIiifCreator
{
    // List of managed IIIF media types.
    protected $_supportedFormats = array(
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/tiff' => 'TIFF',
        'image/gif' => 'GIF',
        'application/pdf' => 'PDF',
        'image/jp2' => 'JP2',
        'image/webp' => 'WEBP',
    );

    protected $fileManager;

    /**
     * Check for the php extension.
     *
     * @throws Exception
     */
    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;

        $t = $this->getTranslator();
        if (!extension_loaded('imagick')) {
            throw new Exception($t->translate('The transformation of images via ImageMagick requires the PHP extension "imagick".'));
        }

        $this->_supportedFormats = array_intersect($this->_supportedFormats, \Imagick::queryFormats());
    }

    /**
     * Transform an image into another image according to params.
     *
     * @internal The args are currently already checked in the controller.
     *
     * @param array $args List of arguments for the transformation.
     * @return string|null The filepath to the temp image if success.
     */
    public function transform(array $args = array())
    {
        if (empty($args)) {
            return;
        }

        $this->_args = $args;
        $args = &$this->_args;

        if (!$this->checkMediaType($args['source']['mime_type'])
                || !$this->checkMediaType($args['format']['feature'])
            ) {
            return;
        }

        $imagick = $this->_loadImageResource($args['source']['filepath']);
        if (empty($imagick)) {
            return;
        }

        // Get width and height if missing.
        if (empty($args['source']['width']) || empty($args['source']['height'])) {
            $args['source']['width'] = $imagick->getImageWidth();
            $args['source']['height'] = $imagick->getImageHeight();
        }

        $extraction = $this->_prepareExtraction();
        if (!$extraction) {
            $imagick->clear();
            return;
        }

        list(
            $sourceX,
            $sourceY,
            $sourceWidth,
            $sourceHeight,
            $destinationWidth,
            $destinationHeight) = $extraction;

        // The background is normally useless, but it's costless.
        $imagick->setBackgroundColor('black');
        $imagick->setImageBackgroundColor('black');
        $imagick->setImagePage($sourceWidth, $sourceHeight, 0, 0);
        $imagick->cropImage($sourceWidth, $sourceHeight, $sourceX, $sourceY);
        $imagick->thumbnailImage($destinationWidth, $destinationHeight);
        $imagick->setImagePage($destinationWidth, $destinationHeight, 0, 0);

        // Rotation.
        switch ($args['rotation']['feature']) {
            case 'noRotation':
                break;

            case 'rotationBy90s':
            case 'rotationArbitrary':
                $imagick->rotateimage('black', $args['rotation']['degrees']);
                break;

            default:
                $imagick->clear();
                return;
        }

        // Quality.
        switch ($args['quality']['feature']) {
            case 'default':
                break;

            case 'color':
                // No change, because only one image is managed.
                break;

            case 'gray':
                $imagick->transformImageColorspace(imagick::COLORSPACE_GRAY);
                break;

            case 'bitonal':
                $imagick->thresholdImage(0.77 * $imagick->getQuantum());
                break;

            default:
                $imagick->clear();
                return;
        }

        // Save resulted resource into the specified format.
        // TODO Use a true name to allow cache, or is it managed somewhere else?
        $destination = tempnam(sys_get_temp_dir(), 'uv_');

        $imagick->setImageFormat($this->_supportedFormats[$args['format']['feature']]);
        $result = $imagick->writeImage($this->_supportedFormats[$args['format']['feature']] . ':' . $destination);

        $imagick->clear();

        return $result ? $destination : null;
    }

    /**
     * GD uses multiple functions to load an image, so this one manages all.
     *
     * @param string $source Path of the managed image file
     * @return false|GD image ressource
     */
    protected function _loadImageResource($source)
    {
        if (empty($source)) {
            return false;
        }

        try {
            // The source can be a local file or an external one.
            $store = $this->fileManager->getStore();
            if (get_class($store) == 'LocalStore') {
                if (!is_readable($source)) {
                    return false;
                }
                $imagick = new \Imagick($source);
            }
            // When the storage is external, the file should be fetched before.
            else {
                $tempPath = tempnam(sys_get_temp_dir(), 'uv_');
                $result = copy($source, $tempPath);
                if (!$result) {
                    return false;
                }
                $imagick = new \Imagick($tempPath);
                unlink($tempPath);
            }
        } catch (Exception $e) {
            $logger = $this->getLogger();
            $t = $this->getTranslator();
            $logger->log(Logger::ERR, sprintf($t->translate("Imagick failed to open the file \"%s\". Details:\n%s"), $source, $e->getMessage()));
            return false;
        }

        return $imagick;
    }
}
