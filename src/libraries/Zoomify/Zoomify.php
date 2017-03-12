<?php
namespace Zoomify;

/**
 * Zoomify big images into tiles supported by many viewers.
 *
 * The Zoomify class is a port of the ZoomifyImage python script to a PHP class.
 * The original python script was written by Adam Smith, and was ported to PHP
 * (in the form of ZoomifyFileProcessor) by Wes Wright. The port to Imagick was
 * done by Daniel Berthereau for the Bibliothèque patrimoniale of Mines ParisTech.
 *
 * Copyright 2005 Adam Smith (asmith@agile-software.com)
 * Copyright Wes Wright (http://greengaloshes.cc)
 * Copyright Justin Henry (http://greengaloshes.cc)
 * Copyright 2014-2017 Daniel Berthereau (Daniel.github@Berthereau.net)
 *
 * Ported from Python to PHP by Wes Wright
 * Cleanup for Drupal by Karim Ratib (kratib@open-craft.com)
 * Cleanup for Omeka Classic by Daniel Berthereau (daniel.github@berthereau.net)
 * Conversion to ImageMagick by Daniel Berthereau
 * Integrated in Omeka S and support a specified destination directory.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * Provides an interface to perform tiling of images.
 */
class Zoomify
{

    /**
     * Store the config.
     *
     * @var array
     */
    protected $config;

    /**
     * The processor to use.
     *
     * @var string
     */
    protected $processor;

    /**
     * The path to the image.
     *
     * @var string
     */
    protected $filepath;

    /**
     * The path to the destination dir.
     *
     * @var string
     */
    protected $destinationDir;

    /**
     * If an existing destination should be removed.
     *
     * @var interger
     */
    protected $destinationRemove = false;

    /**
     * The file system mode of the directories.
     *
     * @var integer
     */
    protected $dirMode = 0755;

    /**
     * The size of tiles.
     *
     * @var interger
     */
    protected $tileSize = 256;

    /**
     * The overlap of tiles.
     *
     * @var integer
     */
    protected $tileOverlap = 0;

    /**
     * The format of tiles.
     *
     * @var string
     */
    protected $tileFormat = 'jpg';

    /**
     * The quality of the tile.
     *
     * @var integer
     */
    protected $tileQuality = 85;

    /**
     * Various metadata of the source and tiles.
     *
     * @array
     */
    protected $data = array();

    protected $_tileExt = 'jpg';
    protected $_imageFilename = '';
    protected $_originalWidth = 0;
    protected $_originalHeight = 0;
    protected $_originalFormat = 0;
    protected $_saveToLocation;
    protected $_scaleInfo = array();
    protected $_tileGroupMappings = array();
    protected $_numberOfTiles = 0;

    /**
     * Constructor.
     *
     * @param array $config
     * @throws \Exception
     */
    function __construct(array $config = array())
    {
        $this->config = $config;
        if (isset($config['processor'])) {
            $this->processor = $config['processor'];
        }

        // Check the processor.
        // Automatic.
        if (empty($this->processor)) {
            if (extension_loaded('imagick')) {
                $this->processor = 'Imagick';
            } elseif (extension_loaded('gd')) {
                $this->processor = 'GD';
            } elseif (!empty($this->convertPath)) {
                $this->processor = 'ImageMagick';
            } else {
                require_once 'ZoomifyImageMagick.php';
                $processor = new ZoomifyImageMagick();
                $convertPath = $processor->getConvertPath();
                if (!empty($convertPath)) {
                    $this->processor = 'ImageMagick';
                } else {
                    throw new \Exception ('Convert path is not available.');
                }
            }
        }
        // Imagick.
        elseif ($this->processor == 'Imagick') {
            if (!extension_loaded('imagick')) {
                throw new \Exception ('Imagick library is not available.');
            }
        }
        // GD.
        elseif ($this->processor == 'GD') {
            if (!extension_loaded('gd')) {
                throw new \Exception ('GD library is not available.');
            }
        }
        // CLI.
        elseif ($this->processor == 'ImageMagick') {
            if (empty($this->convertPath)) {
                require_once 'ZoomifyImageMagick.php';
                $processor = new ZoomifyImageMagick();
                $convertPath = $processor->getConvertPath();
                if (!empty($convertPath)) {
                    $this->processor = 'ImageMagick';
                } else {
                    throw new \Exception ('Convert path is not available.');
                }
            }
        }
        // Error.
        else {
            throw new \Exception ('No graphic library available.');
        }
    }

    /**
     * Zoomify the specified image and store it in the destination dir.
     *
     * Check to be sure the file hasn't been converted already.
     *
     * @param string $filepath The path to the image.
     * @param string $destinationDir The directory where to store the tiles.
     * @return boolean
     */
    public function process($filepath, $destinationDir = '')
    {
        switch ($this->processor) {
            case 'Imagick':
                require_once 'ZoomifyImagick.php';
                $processor = new ZoomifyImagick($this->config);
                break;
            case 'GD':
                require_once 'ZoomifyGD.php';
                $processor = new ZoomifyGD($this->config);
                break;
            case 'ImageMagick':
                require_once 'ZoomifyImageMagick.php';
                $processor = new ZoomifyImageMagick($this->config);
                break;
            default:
                throw new \Exception ('No graphic library available.');
        }
        $result = $processor->process($filepath, $destinationDir);
        return $result;
    }


    /**
     * Zoomify the specified image and store it in the destination dir.
     *
     * Check to be sure the file hasn't been converted already.
     *
     * @param string $filepath The path to the image.
     * @param string $destinationDir The directory where to store the tiles.
     * @return void
     */
    protected function zoomifyImage($filepath, $destinationDir)
    {
        $this->_imageFilename = realpath($filepath);
        $this->filepath = realpath($filepath);
        $this->destinationDir = $destinationDir;
        $result = $this->createDataContainer();
        if (!$result) {
            trigger_error('Output directory already exists.', E_USER_WARNING);
            return;
        }
        $this->getImageMetadata();
        $this->processImage();
        $result = $this->saveXMLOutput();
        return $result;
    }

    /**
     * Given an image name, load it and extract metadata.
     *
     * @return void
     */
    protected function getImageMetadata()
    {
        list($this->_originalWidth, $this->_originalHeight, $this->_originalFormat) = getimagesize($this->_imageFilename);

        // Get scaling information.
        $width = $this->_originalWidth;
        $height = $this->_originalHeight;
        $width_height = array($width, $height);
        array_unshift($this->_scaleInfo, $width_height);
        while (($width > $this->tileSize) || ($height > $this->tileSize)) {
            $width = floor($width / 2);
            $height = floor($height / 2);
            $width_height = array($width, $height);
            array_unshift($this->_scaleInfo, $width_height);
        }

        // Tile and tile group information.
        $this->preProcess();
    }

    /**
     * Create a container (a folder) for tiles and tile metadata if not set.
     *
     * @return boolean
     */
    protected function createDataContainer()
    {
        if ($this->destinationDir) {
            $location = $this->destinationDir;
        }
        //Determine the path to the directory from the filepath.
        else {
            list($root, $ext) = $this->getRootAndDotExtension($this->_imageFilename);
            $directory = dirname($root);
            $filename = basename($root);
            $root = $filename . '_zdata';
            $location = $directory . DIRECTORY_SEPARATOR . $root;
            $this->destinationDir = $location;
        }

        $this->_saveToLocation = $location;

        // If the paths already exist, an image is being re-processed, clean up
        // for it.
        if ($this->destinationRemove) {
            if (is_dir($this->_saveToLocation)) {
                $result = $this->rmDir($this->_saveToLocation);
            }
        } elseif (is_dir($this->_saveToLocation)) {
            return false;
        }

        if (!is_dir($this->_saveToLocation)) {
            mkdir($this->_saveToLocation, $this->dirMode, true);
        }

        return true;
    }

    /**
     * Create a container for the next group of tiles within the data container.
     */
    protected function createTileContainer($tileContainerName = '')
    {
        $tileContainerPath = $this->_saveToLocation . DIRECTORY_SEPARATOR . $tileContainerName;
        if (!is_dir($tileContainerPath)) {
            mkdir($tileContainerPath);
        }
    }

    /**
     * Plan for the arrangement of the tile groups.
     */
    protected function preProcess()
    {
        $tier = 0;
        $tileGroupNumber = 0;
        $numberOfTiles = 0;

        foreach ($this->_scaleInfo as $width_height) {
            list($width, $height) = $width_height;

            // Cycle through columns, then rows.
            $row = 0;
            $column = 0;
            $ul_x = 0;
            $ul_y = 0;
            $lr_x = 0;
            $lr_y = 0;
            while (!(($lr_x == $width) && ($lr_y == $height))) {
                $tileFilename = $this->getTileFilename($tier, $column, $row);
                $tileContainerName = $this->getNewTileContainerName($tileGroupNumber);

                if ($numberOfTiles == 0) {
                    $this->createTileContainer($tileContainerName);
                }
                elseif ($numberOfTiles % $this->tileSize == 0) {
                    ++$tileGroupNumber;
                    $tileContainerName = $this->getNewTileContainerName($tileGroupNumber);
                    $this->createTileContainer($tileContainerName);
                }
                $this->_tileGroupMappings[$tileFilename] = $tileContainerName;
                ++$numberOfTiles;

                // for the next tile, set lower right cropping point
                $lr_x = ($ul_x + $this->tileSize < $width) ? $ul_x + $this->tileSize : $width;
                $lr_y = ($ul_y + $this->tileSize < $height) ? $ul_y + $this->tileSize : $height;

                // for the next tile, set upper left cropping point
                if ($lr_x == $width) {
                    $ul_x = 0;
                    $ul_y = $lr_y;
                    $column = 0;
                    ++$row;
                }
                else {
                    $ul_x = $lr_x;
                    ++$column;
                }
            }
            ++$tier;
        }
    }

    /**
     * Explode a filepath in a root and an extension, i.e. "/path/file.ext" to
     * "/path/file" and ".ext".
     *
     * @return array
     */
    protected function getRootAndDotExtension($filepath)
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $root = $extension ? substr($filepath, 0, strrpos($filepath, '.')) : $filepath;
        return array($root, $extension);
    }

    /**
     * Get the name of the file for the tile.
     *
     * @return string
     */
    protected function getTileFilename($scaleNumber, $columnNumber, $rowNumber)
    {
        return (string) $scaleNumber . '-' . (string) $columnNumber . '-' . (string) $rowNumber . '.' . $this->_tileExt;
    }

    /**
     * Return the name of the next tile group container.
     *
     * @return string
     */
    protected function getNewTileContainerName($tileGroupNumber = 0)
    {
        return 'TileGroup' . (string) $tileGroupNumber;
    }

    /**
     * Get the full path of the file the tile will be saved as.
     *
     * @return string
     */
    protected function getFileReference($scaleNumber, $columnNumber, $rowNumber)
    {
        $tileFilename = $this->getTileFilename($scaleNumber, $columnNumber, $rowNumber);
        $tileContainerName = $this->getAssignedTileContainerName($tileFilename);
        return $this->_saveToLocation . DIRECTORY_SEPARATOR . $tileContainerName . DIRECTORY_SEPARATOR . $tileFilename;
    }

    /**
     * Return the name of the tile group for the indicated tile.
     *
     * @return string
     */
    protected function getAssignedTileContainerName($tileFilename)
    {
        if ($tileFilename) {
            if (isset($this->_tileGroupMappings) && $this->_tileGroupMappings) {
                if (isset($this->_tileGroupMappings[$tileFilename])) {
                    $containerName = $this->_tileGroupMappings[$tileFilename];
                    if ($containerName) {
                        return $containerName;
                    }
                }
            }
        }
        $containerName = $this->getNewTileContainerName();

        return $containerName;
    }

    /**
     * Save xml metadata about the tiles.
     *
     * @return boolean
     */
    protected function saveXMLOutput()
    {
        $xmlFile = fopen($this->_saveToLocation . DIRECTORY_SEPARATOR . 'ImageProperties.xml', 'w');
        if ($xmlFile === false) {
            return false;
        }
        fwrite($xmlFile, $this->getXMLOutput());
        $result = fclose($xmlFile);
        return $result;
    }

    /**
     * Create xml metadata about the tiles
     *
     * @return string
     */
    protected function getXMLOutput()
    {
        $xmlOutput = sprintf('<IMAGE_PROPERTIES WIDTH="%s" HEIGHT="%s" NUMTILES="%s" NUMIMAGES="1" TILESIZE="%s" VERSION="1.8" />',
            $this->_originalWidth, $this->_originalHeight, $this->_numberOfTiles, $this->tileSize) . PHP_EOL;
        return $xmlOutput;
    }

    /**
     * Remove a dir from filesystem.
     *
     * @param string $dirpath
     * @return boolean
     */
    protected function rmDir($dirPath)
    {
        $files = array_diff(scandir($dirPath), array('.', '..'));
        foreach ($files as $file) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->rmDir($path);
            }
            else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }
}
