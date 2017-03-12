<?php

namespace Deepzoom;

/**
 * Support the use of the Deepzom processor classes.
 *
 * Both tools do the about same thing - that is, they convert images into a
 * format that can be used by the "OpenSeadragon" image viewer and any IIIF
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
     * The path to command line ImageMagick convert when the processor is "cli".
     *
     * @var string
     */
    protected $convertPath;

    /**
     * The strategy to use by php to process a command ("exec" or "proc_open").
     *
     * @var string
     */
    protected $executeStrategy;

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
                $this->processor = 'Imagick';
            } elseif (extension_loaded('gd')) {
                $this->processor = 'GD';
            } elseif (!empty($this->convertPath)) {
                $this->processor = 'ImageMagick';
            } else {
                $this->convertPath = $this->getConvertPath();
                if (!empty($this->convertPath)) {
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
                $this->convertPath = $this->getConvertPath();
                if (empty($this->convertPath)) {
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
            case 'Imagick':
                $image = new \Imagick();
                $image->readImage($this->filepath);
                $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
                $image->stripImage();
                break;
            case 'GD':
                $image = $this->openImage();
                $this->data['image'] = $image;
                break;
            case 'ImageMagick':
                $image = $this->filepath;
                break;
        }

        $width = $this->data['width'];
        $height = $this->data['height'];

        $maxDimension = max(array($width, $height));
        $numLevels = $this->getNumLevels($maxDimension);

        foreach(range($numLevels - 1, 0) as $level) {
            $this->createTileContainer($level);
            $scale = $this->getScaleForLevel($numLevels, $level);
            $dimension = $this->getDimensionForScale($width, $height, $scale);
            $this->createLevelTiles($image, $level, $dimension['width'], $dimension['height']);
        }

        switch ($this->processor) {
            case 'Imagick':
                $image->destroy();
                break;
            case 'GD':
                imagedestroy($image);
                break;
            case 'ImageMagick':
                // Nothing to do.
                break;
        }
    }

    /**
     * Get the number of levels in the pyramid.
     *
     * @param $maxDimension
     * @return int
     */
    protected function getNumLevels($maxDimension)
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
    protected function getNumTiles($width, $height)
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
    protected function getDimensionForScale($width, $height, $scale)
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
    protected function createLevelTiles($image, $level, $width, $height)
    {
        // Create new image at scaled dimensions.
        switch ($this->processor) {
            case 'Imagick':
                $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, false);
                break;
            case 'GD':
                $imageLevel = $this->imageResize($width, $height);
                break;
            case 'ImageMagick':
                $resize = array();
                $resize['width'] = $width;
                $resize['height'] = $height;
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
                    case 'Imagick':
                        $tileImage = clone $image;
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
                    case 'GD':
                        $ul_x = $bounds['x'];
                        $ul_y = $bounds['y'];
                        $lr_x = $bounds['x'] + $bounds['width'];
                        $lr_y = $bounds['y'] + $bounds['height'];
                        $tileImage = $this->imageCrop($imageLevel, $ul_x, $ul_y, $lr_x, $lr_y);
                        touch($filepath);
                        imagejpeg($tileImage, $filepath, $this->tileQuality);
                        imagedestroy($tileImage);
                        break;
                    case 'ImageMagick':
                        $this->imageResizeCrop($image, $filepath, $resize, $bounds);
                        break;
                }
            }
        }

        switch ($this->processor) {
            case 'Imagick':
                // Nothing to do.
                break;
            case 'GD':
                imagedestroy($imageLevel);
                break;
            case 'ImageMagick':
                // Nothing to do.
                break;
        }
    }

    /**
     * Get the tile bounds position.
     *
     * @param $column
     * @param $row
     * @return array
     */
    protected function getTileBoundsPosition($column, $row)
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
    protected function getTileBounds($level, $column, $row, $w, $h)
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
    protected function getImageFromFile($filepath)
    {
        switch (strtolower(pathinfo($filepath, PATHINFO_EXTENSION))) {
            case 'png':
                return imagecreatefrompng($filepath);
            case 'gif':
                return imagecreatefromgif($filepath);
            case 'jpg':
            case 'jpe':
            case 'jpeg':
            default:
                return imagecreatefromjpeg($filepath);
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
     * Resize the GD main image.
     *
     * @param integer $width
     * @param integer $height
     * @return resource
     */
    protected function imageResize($width, $height)
    {
        $tempImage = imagecreatetruecolor($width, $height);
        $result = imagecopyresampled(
            $tempImage, $this->data['image'],
            0, 0, 0, 0,
            $width, $height, $this->data['width'], $this->data['height']);

        if ($result === false) {
            imagedestroy($tempImage);
            throw new \Exception('Cannot resize image with GD.');
        }

        return $tempImage;
    }

    /**
     * Resize and crop an image via convert.
     *
     * @internal For resize, the size is forced (option "!").
     *
     * @param string $source
     * @param string $destination
     * @param array $resize
     * @param array $crop
     * @return boolean
     */
    protected function imageResizeCrop($source, $destination, $resize = array(), $crop = array())
    {
        $params = array();
        $params[] = '+repage';
        $params[] = '-alpha remove';
        if ($resize) {
            $params[] = '-thumbnail ' . escapeshellarg(sprintf('%sx%s!', $resize['width'], $resize['height']));
        }
        if ($crop) {
            $params[] = '-crop ' . escapeshellarg(sprintf('%dx%d+%d+%d', $crop['width'], $crop['height'], $crop['x'], $crop['y']));
        }
        $params[] = '-quality ' . $this->tileQuality;

        $command = sprintf(
            '%s %s %s %s',
            $this->convertPath,
            escapeshellarg($source . '[0]'),
            implode(' ', $params),
            escapeshellarg($destination)
        );

        $result = $this->execute($command);
        return $result !== false;
    }

    /**
     * Helper to get the command line to convert.
     *
     * @return string|null
     */
    protected function getConvertPath()
    {
        $command = 'whereis -b convert';
        $result = $this->execute($command);
        if (empty($result)) {
            return;
        }
        strtok($result, ' ');
        return strtok(' ');
    }

    /**
     * Execute a command.
     *
     * Expects arguments to be properly escaped.
     *
     * @see Omeka\Service\Cli
     *
     * @param string $command An executable command
     * @return string|false The command's standard output or false on error
     */
    protected function execute($command)
    {
        switch ($this->executeStrategy) {
            case 'proc_open':
                $output = $this->procOpen($command);
                break;
            case 'exec':
            default:
                $output = $this->exec($command);
                break;
        }

        return $output;
    }

    /**
     * Execute command using PHP's exec function.
     *
     * @see http://php.net/manual/en/function.exec.php
     * @param string $command
     * @return string|false
     */
    protected function exec($command)
    {
        exec($command, $output, $exitCode);
        if (0 !== $exitCode) {
            return false;
        }
        return implode(PHP_EOL, $output);
    }

    /**
     * Execute command using PHP's proc_open function.
     *
     * For servers that allow proc_open. Logs standard error.
     *
     * @see http://php.net/manual/en/function.proc-open.php
     * @param string $command
     * @return string|false
     */
    protected static function procOpen($command)
    {
        // Using proc_open() instead of exec() solves a problem where exec('convert')
        // fails with a "Permission Denied" error because the current working
        // directory cannot be set properly via exec().  Note that exec() works
        // fine when executing in the web environment but fails in CLI.
        $descriptorSpec = array(
            0 => array("pipe", "r"), //STDIN
            1 => array("pipe", "w"), //STDOUT
            2 => array("pipe", "w"), //STDERR
        );
        $proc = proc_open($command, $descriptorSpec, $pipes, getcwd());
        if (!is_resource($proc)) {
            return false;
        }
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $exitCode = proc_close($proc);
        if (0 !== $exitCode) {
            return false;
        }
        return trim($output);
    }
}
