<?php
/**
 * Class Name: zoomify
 *
 * @author: Justin Henry [http://greengaloshes.cc]
 * Cleanup for Omeka by Daniel Berthereau (daniel.github@berthereau.net)
 *
 * Purpose: This class contains methods to support the use of the
 * ZoomifyFileProcessor class.  The ZoomifyFileProcessor class is a port of the
 * ZoomifyImage python script to a PHP class.  The original python script was
 * written by Adam Smith, and was ported to PHP (in the form of
 * ZoomifyFileProcessor) by Wes Wright.
 *
 * Both tools do the about same thing - that is, they convert images into a
 * format that can be used by the "zoomify" image viewer.
 *
 * This class provides an interface for performing "batch" conversions using the
 * ZoomifyFileProcessor class. It also provides methods for inspecting resulting
 * processed images.
 */

// ImageMagick is quicker than GD.
if (class_exists('Imagick')) {
    require_once 'ZoomifyFileProcessorImageMagick.php';
}
else {
    require_once 'ZoomifyFileProcessor.php';
}

class zoomify
{
    public $_debug = false;
    public $fileMode = 0644;
    public $dirMode = 0755;
    public $fileGroup = 'www-data';

    public $imagePath = '';

    /**
     * Constructor
     * Initialize process, set class vars
     *
     * @return void
     */
    function __construct($imagepath)
    {
        $this->imagePath = $imagepath;
        $this->fileMode = $this->fileMode;
        $this->dirMode = $this->dirMode;
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
        $converter = new ZoomifyFileProcessor();
        $converter->_debug = $this->_debug;
        $converter->fileMode = $this->fileMode;
        $converter->dirMode = $this->dirMode;
        $converter->fileGroup = $this->fileGroup;

        $trimmedFilename = $this->stripExtension($filename);

        if (!file_exists($path . $trimmedFilename)) {
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
     * @param string @dir
     *   path to a directory.
     *
     * @return string|boolean
     */
    public function listZoomifiedImages($dir)
    {
        $dh = @opendir($dir);
        if ($dh) {
            while (false !== ($filename = readdir($dh))) {
                if (($filename != '.')
                        && ($filename != '..')
                        && (is_dir($dir . $filename . DIRECTORY_SEPARATOR))
                    ) {
                    echo '<a href="viewer.php?file=' . $filename . '&path="' . $dir . '">' . $filename . '</a><br />' . PHP_EOL;
                }
            }

        }
        else {
            return false;
        }
    }

    /**
     * Returns an array with every file in the directory that is not a dir.
     *
     * @param string @dir
     *   path to a directory.
     *
     * @return array|boolean
     */
    protected function getImageList($dir)
    {
        $dh = @opendir($dir);
        if ($dh) {
            while (false !== ($filename = readdir($dh))) {
                if (($filename != '.')
                        && ($filename != '..')
                        && (!is_dir($dir . $filename . DIRECTORY_SEPARATOR))
                    ) {
                    $filelist[] = $filename;
                }
            }

            sort($filelist);

            return $filelist;
        }
        else {
            return false;
        }

    }

    /**
     * Returns an array containing each entry in the directory.
     *
     * @param string @dir
     *   path to a directory.
     *
     * @return array|boolean
     */
    protected function getDirList($dir)
    {
        $dh = @opendir($dir);
        if ($dh) {
            while (false !== ($filename = readdir($dh))) {
                if (($filename != '.')
                        && ($filename != '..')
                    ) {
                    $filelist[] = $filename;
                }
            }

            sort($filelist);

            return $filelist;
        }
        else {
            return false;
        }
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
