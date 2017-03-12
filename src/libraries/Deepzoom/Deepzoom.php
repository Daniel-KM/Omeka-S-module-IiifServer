<?php

namespace Deepzoom;

/**
 * Support the use of the Deepzom processor classes.
 *
 * Both tools do the about same thing - that is, they convert images into a
 * format that can be used by the "OpenSeaDragon" image viewer and any IIIF
 * viewer.
 *
 * The process is a mix of the laravel plugin [Deepzoom](https://github.com/jeremytubbs/deepzoom)
 * of Jeremy Tubbs, the standalone open zoom builder [Deepzoom.php](https://github.com/nfabre/deepzoom.php)
 * of Nicolas Fabre, the [script](http://omarriott.com/aux/leaflet-js-non-geographical-imagery/)
 * of Olivier Mariott, and the Zoomify converter (see the integrated library).
 * See respective copyright and license (MIT and GNU/GPL) in the above pages.
 */

class Deepzoom
{

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
    protected $tileOverlap = 1;

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

    /**
     * Constructor.
     *
     * @param array $config
     * @throws \Exception
     */
    function __construct(array $config = array())
    {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Check the processor.
        // Automatic.
        if (empty($this->processor)) {
            if (extension_loaded('imagick')) {
                $this->processor = 'imagick';
            } elseif (extension_loaded('gd')) {
                $this->processor = 'gd';
            } else {
                throw new \Exception ('No graphic library available.');
            }
        }
        // Imagick.
        elseif ($this->processor == 'imagick') {
            if (!extension_loaded('imagick')) {
                throw new \Exception ('Imagick library is not available.');
            }
        }
        // GD.
        elseif ($this->processor == 'gd') {
            if (!extension_loaded('gd')) {
                throw new \Exception ('GD library is not available.');
            }
        }
        // CLI.
        elseif (strpos($this->processor, 'convert') === false) {
            throw new \Exception ('No graphic library available.');
        }
    }

    /**
     * Deepzoom the specified image and it them in the destination dir.
     *
     * Check to be sure the file hasn't been converted already.
     *
     * @param string $filepath The path to the image.
     * @param string $destinationDir The directory where to store the tiles.
     * @return boolean
     */
    public function process($filepath, $destinationDir = '')
    {
        $this->filepath = realpath($filepath);
        $this->destinationDir = $destinationDir;

        $this->getImageMetadata();
        $result = $this->createDataContainer();
        if (!$result) {
            throw new \Exception ('Output directory already exists.');
        }
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
        list($this->data['width'], $this->data['height'], $this->data['format']) = getimagesize($this->filepath);
    }

    /**
     * Create a container (a folder) for tiles and tile metadata if not set.
     */
    protected function createDataContainer()
    {
        if ($this->destinationDir) {
            $locationDir = $this->destinationDir;
            $filename = basename($this->filepath);
        }
        //Determine the path to the directory from the filepath.
        else {
            list($root, $ext) = $this->getRootAndDotExtension($this->filepath);
            $locationDir = dirname($root);
            $filename = basename($root);
        }

        $this->data['baseDir'] = $locationDir;
        $basepath = $locationDir . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);
        $this->data['dzi'] = $basepath . '.dzi';
        $this->data['tileDir'] = $basepath . '_files';

        // If the paths already exist, an image is being re-processed, clean up
        // for it.
        if ($this->destinationRemove) {
            if (file_exists($this->data['dzi'])) {
                $result = unlink($this->data['dzi']);
            }
            if (is_dir($this->data['tileDir'])) {
                $result = $this->rmDir($this->data['tileDir']);
            }
        } elseif (file_exists($this->data['dzi']) || is_dir($this->data['tileDir'])) {
            return false;
        }

        mkdir($this->data['tileDir'], $this->dirMode, true);

        return  true;
    }

    /**
     * Create a container for the next group of tiles within the data container.
     *
     * @return void
     */
    protected function createTileContainer($tileContainerName)
    {
        $tileContainerPath = $this->data['tileDir'] . DIRECTORY_SEPARATOR . $tileContainerName;
        if (!is_dir($tileContainerPath)) {
            mkdir($tileContainerPath, $this->dirMode);
        }
    }

    /**
     * Starting with the original image, start processing each level.
     *
     * @return void
     */
    protected function processImage()
    {
        switch ($this->processor) {
            case 'imagick':
                $image = new \Imagick();
                $image->readImage($this->filepath);
                $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
                $image->stripImage();
                break;
            case 'gd':
                break;
        }

        $width = $this->data['width'];
        $height = $this->data['height'];

        $maxDimension = max(array($width, $height));
        $numLevels = $this->getNumLevels($maxDimension);

        foreach(range($numLevels - 1, 0) as $level) {
            $this->createTileContainer($level);
            $scale = $this->getScaleForLevel($numLevels, $level);
            $dimension = $this->getDimensionForLevel($width, $height, $scale);
            $this->createLevelTiles($image, $level, $dimension['width'], $dimension['height']);
        }

        switch ($this->processor) {
            case 'imagick':
                $image->destroy();
                break;
            case 'gd':
                break;
        }
    }

    /**
     * Get the number of levels in the pyramid.
     *
     * @param $maxDimension
     * @return int
     */
    public function getNumLevels($maxDimension)
    {
        return (integer) ceil(log($maxDimension, 2)) + 1;
    }

    /**
     * Get the number of tiles according to the tile size.
     *
     * @param $width
     * @param $height
     * @return array
     */
    public function getNumTiles($width, $height)
    {
        $columns = (int)ceil(floatval($width) / $this->tileSize);
        $rows = (int)ceil(floatval($height) / $this->tileSize);
        return array('columns' => $columns, 'rows' => $rows);
    }

    /**
     * Get the scale for the specified level, according to the number of levels.
     *
     * @param $numberLevels
     * @param $level
     * @return number
     */
    protected  function getScaleForLevel($numberLevels, $level)
    {
        $maxLevel = $numberLevels - 1;
        return pow(0.5, $maxLevel - $level);
    }

    /**
     * Get the dimension for the specified size and scale.
     *
     * @param $width
     * @param $height
     * @param $scale
     * @return array
     */
    public function getDimensionForLevel($width, $height, $scale)
    {
        $width = (integer) ceil($width * $scale);
        $height = (integer) ceil($height * $scale);
        return array('width' => $width, 'height' => $height);
    }

    /**
     * Process a level of tiles.
     *
     * @param $image
     * @param $level
     * @param $width
     * @param $height
     * @return void
     */
    public function createLevelTiles($image, $level, $width, $height)
    {
        // Create new image at scaled dimensions.
        switch ($this->processor) {
            case 'imagick':
                $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, false);
                break;
            case 'gd':
                break;
        }

        $basepath = $this->data['tileDir'] . DIRECTORY_SEPARATOR . $level;

        // Get column and row count for level.
        $tiles = $this->getNumTiles($width, $height);

        foreach (range(0, $tiles['columns'] - 1) as $column) {
            foreach (range(0, $tiles['rows'] - 1) as $row) {
                $filename = $column . '_' . $row . '.' . $this->tileFormat;
                $filepath = $basepath . DIRECTORY_SEPARATOR . $filename;
                $bounds = $this->getTileBounds($level, $column, $row, $width, $height);

                switch ($this->processor) {
                    case 'imagick':
                        $tileImage = clone $image;
                        // Clean the canvas and optimize for web.
                        $tileImage->setImagePage(0, 0, 0, 0);
                        $tileImage->cropImage($bounds['width'], $bounds['height'], $bounds['x'], $bounds['y']);
                        $tileImage->setImageFormat($this->tileFormat);
                        if ($this->tileFormat == 'jpg') {
                            $tileImage->setImageCompression(\Imagick::COMPRESSION_JPEG);
                        }
                        $tileImage->setImageCompressionQuality($this->tileQuality);
                        $tileImage->writeImage($filepath);
                        $tileImage->destroy();
                        break;
                    case 'gd':
                        break;
                }
            }
        }
    }

    /**
     * Get the tile bounds position.
     *
     * @param $column
     * @param $row
     * @return array
     */
    public function getTileBoundsPosition($column, $row)
    {
        $offsetX = $column == 0 ? 0 : $this->tileOverlap;
        $offsetY = $row == 0 ? 0 : $this->tileOverlap;
        $x = ($column * $this->tileSize) - $offsetX;
        $y = ($row * $this->tileSize) - $offsetY;

        return array('x' => $x, 'y' => $y);
    }

    /**
     * Get the tile bounds.
     *
     * @param $level
     * @param $column
     * @param $row
     * @param $w
     * @param $h
     * @return array
     */
    public function getTileBounds($level, $column, $row, $w, $h)
    {
        $position = $this->getTileBoundsPosition($column, $row);

        $width = $this->tileSize + ($column == 0 ? 1 : 2) * $this->tileOverlap;
        $height = $this->tileSize + ($row == 0 ? 1 : 2) * $this->tileOverlap;
        $newWidth = min($width, $w - $position['x']);
        $newHeight = min($height, $h - $position['y']);

        return array_merge($position, array('width' => $newWidth,'height' => $newHeight));
    }

    /**
     * Save xml metadata about the tiles.
     *
     * @return boolean
     */
    protected function saveXMLOutput()
    {
        $xmlFile = fopen($this->data['dzi'], 'w');
        if ($xmlFile === false) {
            return false;
        }
        fwrite($xmlFile, $this->getXMLOutput());
        return fclose($xmlFile);
    }

    /**
     * Create xml metadata about the tiles
     *
     * @return string
     */
    protected function getXMLOutput()
    {
        $xmlOutput = sprintf('<?xml version="1.0" encoding="UTF-8"?>
<Image xmlns="http://schemas.microsoft.com/deepzoom/2008" Format="%s" Overlap="%s" TileSize="%s">
    <Size Height="%s" Width="%s" />
</Image>',
            $this->tileFormat, $this->tileOverlap, $this->tileSize, $this->data['height'], $this->data['width']) .PHP_EOL;
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

    /**
     * Load the image data.
     *
     * @return ressource identifier of the image.
     */
    protected function openImage()
    {
        return $this->getImageFromFile($this->filepath);
    }

    /**
     * Helper to get an image of different type (jpg, png or gif) from file.
     *
     * @return ressource identifier of the image.
     */
    protected function getImageFromFile($filename)
    {
        switch (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            case 'png':
                return imagecreatefrompng($filename);
            case 'gif':
                return imagecreatefromgif($filename);
            case 'jpg':
            case 'jpe':
            case 'jpeg':
            default:
                return imagecreatefromjpeg($filename);
        }
    }

    /**
     * Crop an image to a size.
     *
     * @return ressource identifier of the image.
     */
    protected function imageCrop($image, $left, $upper, $right, $lower)
    {
        $w = abs($right - $left);
        $h = abs($lower - $upper);
        $crop = imagecreatetruecolor($w, $h);
        imagecopy($crop, $image, 0, 0, $left, $upper, $w, $h);
        return $crop;
    }

    /**
     * Save the cropped region.
     */
    protected function saveTile($image, $scaleNumber, $column, $row)
    {
        $tile_file = $this->getFileReference($scaleNumber, $column, $row);
        touch($tile_file);
        if ($this->updatePerms) {
            @chmod($tile_file, $this->fileMode);
            @chgrp($tile_file, $this->fileGroup);
        }
        imagejpeg($image, $tile_file, $this->tileQuality);
        if ($this->_debug) {
            print "Saving to tile_file $tile_file<br />" . PHP_EOL;
        }
    }
}
