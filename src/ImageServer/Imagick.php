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

namespace IiifServer\ImageServer;

use IiifServer\AbstractImageServer;
use Omeka\File\Store\StoreInterface;
use Omeka\File\TempFileFactory;
use Zend\Log\Logger;

/**
 * Helper to create an image from another one with IIIF arguments.
 *
 * @package IiifServer
 */
class Imagick extends AbstractImageServer
{
    // List of managed IIIF media types.
    protected $_supportedFormats = [
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/tiff' => 'TIFF',
        'image/gif' => 'GIF',
        'application/pdf' => 'PDF',
        'image/jp2' => 'JP2',
        'image/webp' => 'WEBP',
    ];

    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * Check for the php extension.
     *
     * @param TempFileFactory $tempFileFactory
     * @param StoreInterface $store
     * @throws \Exception
     */
    public function __construct(TempFileFactory $tempFileFactory, $store)
    {
        $t = $this->getTranslator();
        if (!extension_loaded('imagick')) {
            throw new \Exception($t->translate('The transformation of images via ImageMagick requires the PHP extension "imagick".'));
        }

        $this->tempFileFactory = $tempFileFactory;
        $this->store = $store;
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
    public function transform(array $args = [])
    {
        if (empty($args)) {
            return;
        }

        $this->_args = $args;
        $args = &$this->_args;

        if (!$this->checkMediaType($args['source']['media_type'])
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

        // Region + Size.
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

        // Mirror.
        switch ($args['mirror']['feature']) {
            case 'mirror':
            case 'horizontal':
                $imagick->flopImage();
                break;

            case 'vertical':
                $imagick->flipImage();
                break;

            case 'both':
                $imagick->flopImage();
                $imagick->flipImage();
                break;

            case 'default':
                // Nothing to do.
                break;

            default:
                $imagick->clear();
                return;
        }

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
                $imagick->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
                break;

            case 'bitonal':
                $imagick->thresholdImage(0.77 * $imagick->getQuantum());
                break;

            default:
                $imagick->clear();
                return;
        }

        // Save resulted resource into the specified format.
        $extension = strtolower($this->_supportedFormats[$args['format']['feature']]);
        $tempFile = $this->tempFileFactory->build();
        $destination = $tempFile->getTempPath() . '.' . $extension;
        $tempFile->delete();

        $imagick->setImageFormat($this->_supportedFormats[$args['format']['feature']]);
        $result = $imagick->writeImage($this->_supportedFormats[$args['format']['feature']] . ':' . $destination);

        $imagick->clear();

        return $result ? $destination : null;
    }

    /**
     * Load an image from anywhere.
     *
     * @param string $source Path of the managed image file
     * @return Imagick|false
     */
    protected function _loadImageResource($source)
    {
        if (empty($source)) {
            return false;
        }

        try {
            // A check is added if the file is local: the source can be a local file
            // or an external one (Amazon S3…).
            switch (get_class($this->store)) {
                case \Omeka\File\Store\Local::class:
                    if (!is_readable($source)) {
                        return false;
                    }
                    $imagick = new \Imagick($source);
                    break;

                // When the storage is external, the file is fetched before.
                default:
                    $tempFile = $this->tempFileFactory->build();
                    $tempPath = $tempFile->getTempPath();
                    $tempFile->delete();
                    $result = copy($source, $tempPath);
                    if (!$result) {
                        return false;
                    }
                    $imagick = new \Imagick($tempPath);
                    unlink($tempPath);
                    break;
            }
        } catch (\Exception $e) {
            $logger = $this->getLogger();
            $t = $this->getTranslator();
            $logger->log(Logger::ERR, sprintf($t->translate("Imagick failed to open the file \"%s\". Details:\n%s"), $source, $e->getMessage()));
            return false;
        }

        return $imagick;
    }
}
