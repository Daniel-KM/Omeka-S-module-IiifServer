<?php
/**
 * Class Name: zoomify
 *
 * @author: Justin Henry [http://greengaloshes.cc]
 * Cleanup for Omeka Classic by Daniel Berthereau (daniel.github@berthereau.net)
 * Integrated in Omeka S and support a specified destination directory.
 *
 * Purpose: This class contains methods to support the use of the
 * ZoomifyFileProcessor class.  The ZoomifyFileProcessor class is a port of the
 * ZoomifyImage python script to a PHP class.  The original python script was
 * written by Adam Smith, and was ported to PHP (in the form of
 * ZoomifyFileProcessor) by Wes Wright. The port to ImageMagick was done by
 * Daniel Berthereau.
 *
 * Both tools do the about same thing - that is, they convert images into a
 * format that can be used by the "zoomify" image viewer and any compatible
 * viewer such OpenLayers.
 *
 * This class provides an interface for performing "batch" conversions using the
 * ZoomifyFileProcessor class. It also provides methods for inspecting resulting
 * processed images.
 */

// Imagick manages more formats than GD.
if (extension_loaded('imagick')) {
    require_once 'ZoomifyProcessorImagick.php';
} elseif (extension_loaded('gd')) {
    require_once 'ZoomifyProcessorGD.php';
} else {
    throw new Exception ('No graphic library available.');
}

class Zoomify
{
    // The choice is automatic.
    public $processor = '';
    public $imagePath = '';
    public $destinationDir = '';
    public $destinationRemove = true;

    public $_debug = false;
    public $fileMode = 0644;
    public $dirMode = 0755;
    public $fileGroup = 'www-data';
    public $updatePerms = true;

    /**
     * Constructor.
     *
     * @param string $imagePath The path to a directory of images used only to
     * process all images in a directory vith processImages().
     * @return void
     */
    function __construct($imagePath = '')
    {
        $this->imagePath = $imagePath;
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
    public function zoomifyImage($filepath, $destinationDir = '')
    {
        if (!$destinationDir) {
            $properties = $destinationDir . DIRECTORY_SEPARATOR . 'ImageProperties.xml';
            if (file_exists($properties)) {
                return;
            }
        }

        $converter = new ZoomifyFileProcessor();
        $converter->_debug = $this->_debug;
        $converter->destinationDir = $destinationDir;
        $converter->destinationRemove = $this->destinationRemove;
        $converter->updatePerms = $this->updatePerms;
        $converter->fileMode = $this->fileMode;
        $converter->dirMode = $this->dirMode;
        $converter->fileGroup = $this->fileGroup;
        $converter->ZoomifyProcess($filepath);
    }

    /**
     * Run the zoomify converter on the specified file.
     *
     * Check to be sure the file hasn't been converted already.
     * Set the perms appropriately.
     *
     * @return void
     */
    public function zoomifyObject($filename, $path)
    {
        $trimmedFilename = $this->stripExtension($filename);
        if (!file_exists($path . $trimmedFilename)) {
            $converter = new ZoomifyFileProcessor();
            $converter->_debug = $this->_debug;
            $converter->destinationDir = $destinationDir;
            $converter->destinationRemove = $this->destinationRemove;
            $converter->updatePerms = $this->updatePerms;
            $converter->fileMode = $this->fileMode;
            $converter->dirMode = $this->dirMode;
            $converter->fileGroup = $this->fileGroup;

            $file_to_process = $path . $filename;
            // echo "Processing " . $file_to_process . "...<br />";
            $converter->ZoomifyProcess($file_to_process);
        }
        else {
            // echo "Skipping " . $path . $filename . "... (" . $path . $trimmedFilename . " exists)<br />";
        }
    }

    /**
     * Process the specified directory.
     *
     * @return void
     */
    public function processImages()
    {
        $objects = $this->getImageList($this->imagePath);

        foreach ($objects as $object) {
            $this->zoomifyObject($object, $this->imagePath);
        }
    }

    /**
     * Prints list of html links to a zoomified image.
     *
     * @param string $dir Path to a directory.
     * @return string|boolean
     */
    public function listZoomifiedImages($dir)
    {
        $dh = @opendir($dir);
        if (!$dh) {
            return false;
        }

        while (false !== ($filename = readdir($dh))) {
            if ($filename != '.' && $filename != '..'
                && (is_dir($dir . $filename . DIRECTORY_SEPARATOR))
            ) {
                echo '<a href="viewer.php?file=' . $filename . '&path="' . $dir . '">' . $filename . '</a><br />' . PHP_EOL;
            }
        }

        closedir($dh);
    }

    /**
     * Returns an array with every file in the directory that is not a dir.
     *
     * @param string $dir Path to a directory.
     * @return array|boolean
     */
    protected function getImageList($dir)
    {
        $dh = @opendir($dir);
        if (!$dh) {
            return false;
        }

        while (false !== ($filename = readdir($dh))) {
            if ($filename != '.' && $filename != '..'
                && !is_dir($dir . $filename . DIRECTORY_SEPARATOR)
            ) {
                $filelist[] = $filename;
            }
        }

        closedir($dh);

        sort($filelist);

        return $filelist;
    }

    /**
     * Returns an array containing each entry in the directory.
     *
     * @param string $dir Path to a directory.
     * @return array|boolean
     */
    protected function getDirList($dir)
    {
        $dh = @opendir($dir);
        if (!$dh) {
            return false;
        }

        while (false !== ($filename = readdir($dh))) {
            if ($filename != '.' && $filename != '..') {
                $filelist[] = $filename;
            }
        }

        closedir($dh);

        sort($filelist);

        return $filelist;
    }

    /**
     * Strips the extension off of the filename, i.e. file.ext -> file
     *
     * @return string
     */
    protected function stripExtension($filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION)
            ? substr($filename, 0, strrpos($filename, '.'))
            : $filename;
    }
}
