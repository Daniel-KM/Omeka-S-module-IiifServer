<?php
/**
 * Copyright (C) 2005  Adam Smith  asmith@agile-software.com
 *
 * Ported from Python to PHP by Wes Wright
 * Cleanup for Drupal by Karim Ratib (kratib@open-craft.com)
 * Cleanup for Omeka by Daniel Berthereau (daniel.github@berthereau.net)
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
 * Remove a list of files from filesystem.
 *
 * @return boolean
 */
function rm($fileglob)
{
    if (is_string($fileglob)) {
        if (is_file($fileglob)) {
            return unlink($fileglob);
        }
        elseif (is_dir($fileglob)) {
            return _rrmdir($fileglob);
        }
        else {
            $matching = glob($fileglob);
            if ($matching === false) {
                trigger_error(sprintf('No files match supplied glob %s', $fileglob), E_USER_WARNING);
                return false;
            }
            $rcs = array_map('rm', $matching);
            if (in_array(false, $rcs)) {
                return false;
            }
        }
    }
    elseif (is_array($fileglob)) {
        $rcs = array_map('rm', $fileglob);
        if (in_array(false, $rcs)) {
            return false;
        }
    }
    else {
        trigger_error('Param #1 must be filename or glob pattern, or array of filenames or glob patterns', E_USER_ERROR);
        return false;
    }
    return true;
}

/**
 * Removes directories recursively.
 *
 * @param string $dirPath Directory name.
 * @return boolean
 */
function _rrmdir($dirPath)
{
    $files = array_diff(scandir($dirPath), array('.', '..'));
    foreach ($files as $file) {
        $path = $dirPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            _rrmDir($path);
        }
        else {
            unlink($path);
        }
    }
    return rmdir($dirPath);
}

/**
 * ZoomifyFileProcessor class.
 */
class ZoomifyFileProcessor
{
    public $_debug = false;

    public $destinationDir = '';

    public $updatePerms = true;
    public $fileMode = 0644;
    public $dirMode = 0755;
    public $fileGroup = 'www-data';

    public $tileSize = 256;
    public $tileQuality = 85;

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
     * The method the client calls to generate zoomify metadata.
     */
    function ZoomifyProcess($image_name)
    {
        $this->_imageFilename = $image_name;
        $this->createDataContainer($image_name);
        $this->getImageMetadata();
        $this->processImage();
        $this->saveXMLOutput();
    }

    /**
     * Helper to get an image of different type (jpg, png or gif) from file.
     *
     * @return ressource identifier of the image.
     */
    function getImageFromFile($filename)
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
     * Load the image data.
     *
     * @return ressource identifier of the image.
     */
    function openImage()
    {
        if ($this->_debug) {
            print "openImage $this->_imageFilename<br />" . PHP_EOL;
        }
        return $this->getImageFromFile($this->_imageFilename);
    }

    /**
     * Get the name of the file the tile will be saved as.
     *
     * @return string
     */
    function getTileFileName($scaleNumber, $columnNumber, $rowNumber)
    {
        // return '%s-%s-%s.jpg' % (str(scaleNumber), str(columnNumber), str(rowNumber))
        return "$scaleNumber-$columnNumber-$rowNumber.$this->_tileExt";
    }

    /**
     * Return the name of the next tile group container.
     *
     * @return string
     */
    function getNewTileContainerName($tileGroupNumber = 0)
    {
        return 'TileGroup' . $tileGroupNumber;
    }

    /**
     * Plan for the arrangement of the tile groups.
     */
    function preProcess()
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
            while (! (($lr_x == $width) && ($lr_y == $height))) {
                $tileFileName = $this->getTileFileName($tier, $column, $row);
                $tileContainerName = $this->getNewTileContainerName($tileGroupNumber);

                if ($numberOfTiles == 0) {
                    $this->createTileContainer($tileContainerName);
                }
                elseif ($numberOfTiles % $this->tileSize == 0) {
                    $tileGroupNumber++;
                    $tileContainerName = $this->getNewTileContainerName($tileGroupNumber);
                    $this->createTileContainer($tileContainerName);

                    if ($this->_debug) {
                        print 'new tile group ' . $tileGroupNumber . ' tileContainerName=' . $tileContainerName ."<br />" . PHP_EOL;
                    }
                }
                $this->_tileGroupMappings[$tileFileName] = $tileContainerName;
                $numberOfTiles++;

                // for the next tile, set lower right cropping point
                if ($ul_x + $this->tileSize < $width) {
                    $lr_x = $ul_x + $this->tileSize;
                }
                else {
                    $lr_x = $width;
                }

                if ($ul_y + $this->tileSize < $height) {
                    $lr_y = $ul_y + $this->tileSize;
                }
                else {
                    $lr_y = $height;
                }

                // for the next tile, set upper left cropping point
                if ($lr_x == $width) {
                    $ul_x = 0;
                    $ul_y = $lr_y;
                    $column = 0;
                    $row++;
                }
                else {
                    $ul_x = $lr_x;
                    $column++;
                }
            }
            $tier++;
        }
    }

    /**
     * Starting with the original image, start processing each row.
     */
    function processImage()
    {
        $tier = (count($this->_scaleInfo) - 1);
        $row = 0;
        list($ul_y, $lr_y) = array(0, 0);

        list($root, $ext) = $this->getRootAndDotExtension($this->_imageFilename);

        if ($this->_debug) {
            print "processImage root=$root ext=$ext<br />" . PHP_EOL;
        }
        $image = $this->openImage();
        while ($row * $this->tileSize < $this->_originalHeight) {
            $ul_y = $row * $this->tileSize;
            if ($ul_y + $this->tileSize < $this->_originalHeight) {
                $lr_y = $ul_y + $this->tileSize;
            }
            else {
                $lr_y = $this->_originalHeight;
            }
            // print "line " . __LINE__ . " calling crop<br />" . PHP_EOL;
            # imageRow = image.crop([0, ul_y, $this->_originalWidth, lr_y])
            $imageRow = $this->imageCrop($image, 0, $ul_y, $this->_originalWidth, $lr_y);
            $saveFilename = $root . $tier . '-' . $row . '.' . $ext;
            if ($this->_debug) {
                print "processImage root=$root tier=$tier row=$row saveFilename=$saveFilename<br />" . PHP_EOL;
            }
            touch($saveFilename);
            if ($this->updatePerms) {
                @chmod($saveFilename, $this->fileMode);
                @chgrp ($saveFilename, $this->fileGroup);
            }
            imagejpeg($imageRow, $saveFilename, 100);
            imagedestroy($imageRow);
            $this->processRowImage($tier, $row);
            $row++;
        }
        imagedestroy($image);
    }

    /**
     * For an image, create and save tiles.
     */
    function processRowImage($tier = 0, $row = 0)
    {
        # print '*** processing tier: ' + str(tier) + ' row: ' + str(row)

        list($tierWidth, $tierHeight) = $this->_scaleInfo[$tier];
        if ($this->_debug) {
            print "tier $tier width $tierWidth height $tierHeight<br />" . PHP_EOL;
        }
        $rowsForTier = floor($tierHeight / $this->tileSize);
        if ($tierHeight % $this->tileSize > 0) {
            $rowsForTier++;
        }

        list($root, $ext) = $this->getRootAndDotExtension($this->_imageFilename);

        $imageRow = null;

        if ($tier == count($this->_scaleInfo) - 1) {
            $firstTierRowFile = $root . $tier . '-' . $row . '.' . $ext;
            if ($this->_debug) {
                print "firstTierRowFile=$firstTierRowFile<br />" . PHP_EOL;
            }
            if (is_file($firstTierRowFile)) {
                $imageRow = imagecreatefromjpeg($firstTierRowFile);
                if ($this->_debug) {
                    print "firstTierRowFile exists<br />" . PHP_EOL;
                }
            }
        }
        else {
            // Create this row from previous tier's rows.
            $imageRow = imagecreatetruecolor($tierWidth, $this->tileSize);
            $t = $tier + 1;
            $r = $row + $row;
            $firstRowFile = $root . $t . '-' . $r . '.' . $ext;
            if ($this->_debug) {
                print "create this row from previous tier's rows tier=$tier row=$row firstRowFile=$firstRowFile<br />" . PHP_EOL;
            }
            if ($this->_debug) {
                print "imageRow tierWidth=$tierWidth tierHeight= $this->tileSize<br />" . PHP_EOL;
            }
            $firstRowWidth = 0;
            $firstRowHeight = 0;
            $secondRowWidth = 0;
            $secondRowHeight = 0;

            if (is_file($firstRowFile)) {
                # print firstRowFile + ' exists, try to open...'
                $firstRowImage = imagecreatefromjpeg($firstRowFile);
                $firstRowWidth = imagesx($firstRowImage);
                $firstRowHeight = imagesy($firstRowImage);
                $imageRowHalfHeight = floor($this->tileSize / 2);
                // imagecopy(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int src_w, int src_h )
                // imagecopy($imageRow, $firstRowImage, 0, 0, 0, 0, $firstRowWidth, $firstRowHeight);
                if ($this->_debug) {
                    print "imageRow imagecopyresized tierWidth=$tierWidth imageRowHalfHeight= $imageRowHalfHeight firstRowWidth=$firstRowWidth firstRowHeight=$firstRowHeight<br />" . PHP_EOL;
                }
                // Bug: Use $firstRowHeight instead of $imageRowHalfHeight.
                // See Drupal Zoomify module http://drupalcode.org/project/zoomify.git/blob_plain/e2f977ab4b153b4ce6d1a486a1fe80ecf9512559:/ZoomifyFileProcessor.php.
                // imagecopyresized(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h)
                imagecopyresized($imageRow, $firstRowImage, 0, 0, 0, 0, $tierWidth, $firstRowHeight, $firstRowWidth, $firstRowHeight);
                unlink($firstRowFile);
            }

            $r++;
            $secondRowFile =  $root . $t . '-' . $r . '.' . $ext;
            if ($this->_debug) {
                print "create this row from previous tier's rows tier=$tier row=$row secondRowFile=$secondRowFile<br />" . PHP_EOL;
            }
            // There may not be a second row at the bottom of the image...
            if (is_file($secondRowFile)) {
                if ($this->_debug) {
                    print $secondRowFile . " exists, try to open...<br />" . PHP_EOL;
                }
                $secondRowImage = imagecreatefromjpeg($secondRowFile);
                $secondRowWidth = imagesx($secondRowImage);
                $secondRowHeight = imagesy($secondRowImage);
                $imageRowHalfHeight = floor($this->tileSize / 2);
                if ($this->_debug) {
                    print "imageRow imagecopyresized tierWidth=$tierWidth imageRowHalfHeight=$imageRowHalfHeight firstRowWidth=$firstRowWidth firstRowHeight=$firstRowHeight<br />" . PHP_EOL;
                }
                // imagecopy(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int src_w, int src_h )
                // imagecopy($imageRow, $secondRowImage, 0, $firstRowWidth, 0, 0, $firstRowWidth, $firstRowHeight);
                // imagecopyresampled(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h)
                imagecopyresampled($imageRow, $secondRowImage, 0, $imageRowHalfHeight, 0, 0, $tierWidth, $secondRowHeight, $secondRowWidth, $secondRowHeight);
                unlink($secondRowFile);
            }

            // The last row may be less than $this->tileSize...
            $rowHeight = $firstRowHeight + $secondRowHeight;
            $tileHeight = $this->tileSize * 2;
            if (($firstRowHeight + $secondRowHeight) < $this->tileSize * 2) {
                if ($this->_debug) {
                    print "line " . __LINE__ . " calling crop rowHeight=$rowHeight tileHeight=$tileHeight<br />" . PHP_EOL;
                }
                # imageRow = imageRow.crop((0, 0, tierWidth, (firstRowHeight + secondRowHeight)))
                $imageRow = $this->imageCrop($imageRow, 0, 0, $tierWidth, $firstRowHeight + $secondRowHeight);
            }
        }

        if ($imageRow) {
            // Cycle through columns, then rows.
            $column = 0;
            $imageWidth = imagesx($imageRow);
            $imageHeight = imagesy($imageRow);
            $ul_x = 0;
            $ul_y = 0;
            $lr_x = 0;
            $lr_y = 0;
            while (!(($lr_x == $imageWidth) && ($lr_y == $imageHeight))) {
                if ($this->_debug) {
                    print "ul_x=$ul_x lr_x=$lr_x ul_y=$ul_y lr_y=$lr_y imageWidth=$imageWidth imageHeight=$imageHeight<br />" . PHP_EOL;
                }
                // Set lower right cropping point.
                if (($ul_x + $this->tileSize) < $imageWidth) {
                    $lr_x = $ul_x + $this->tileSize;
                }
                else {
                    $lr_x = $imageWidth;
                }

                if (($ul_y + $this->tileSize) < $imageHeight) {
                    $lr_y = $ul_y + $this->tileSize;
                }
                else {
                    $lr_y = $imageHeight;
                }

                # tierLabel = len($this->_scaleInfo) - tier
                if ($this->_debug) {
                    print "line " . __LINE__ . " calling crop<br />" . PHP_EOL;
                }
                $this->saveTile($this->imageCrop($imageRow, $ul_x, $ul_y, $lr_x, $lr_y), $tier, $column, $row);
                $this->_numberOfTiles++;
                if ($this->_debug) {
                    print "created tile: numberOfTiles= $this->_numberOfTiles tier column row =($tier,$column,$row)<br />" . PHP_EOL;
                }

                // Set upper left cropping point.
                if ($lr_x == $imageWidth) {
                    $ul_x = 0;
                    $ul_y = $lr_y;
                    $column = 0;
                    #row += 1
                }
                else {
                    $ul_x = $lr_x;
                    $column++;
                }
            }

            if ($tier > 0) {
                $halfWidth = max(1, floor($imageWidth / 2));
                $halfHeight = max(1, floor($imageHeight / 2));
                $rowFileName = $root . $tier . '-' . $row . '.' . $ext;
                # print 'resize as ' + str(imageWidth/2) + ' by ' + str(imageHeight/2) + ' (or ' + str(halfWidth) + ' x ' + str(halfHeight) + ')'
                # tempImage = imageRow.resize((imageWidth / 2, imageHeight / 2), PIL.Image.ANTIALIAS)
                # tempImage = imageRow.resize((halfWidth, halfHeight), PIL.Image.ANTIALIAS)
                $tempImage = imagecreatetruecolor($halfWidth, $halfHeight);
                // imagecopyresampled(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h)
                imagecopyresampled($tempImage, $imageRow, 0, 0, 0, 0, $halfWidth, $halfHeight, $imageWidth, $imageHeight);
                # tempImage.save(root + str(tier) + '-' + str(row) + ext)
                touch($rowFileName);
                imagejpeg($tempImage, $rowFileName);
                if ($this->updatePerms) {
                    @chmod($rowFileName, $this->fileMode);
                    @chgrp($rowFileName, $this->fileGroup);
                }
                imagedestroy($tempImage);
                # print 'saved row file: ' + root + str(tier) + '-' + str(row) + ext
                # tempImage = None
                # rowImage = None
            }

            imagedestroy($imageRow); // http://greengaloshes.cc/2007/05/zoomifyimage-ported-to-php/#comment-451
            if ($tier > 0) {
                if ($this->_debug) {
                    print "processRowImage final checks for tier $tier row=$row rowsForTier=$rowsForTier<br />" . PHP_EOL;
                }
                if ($row % 2 != 0) {
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row mod 2 check before<br />" . PHP_EOL;
                    }
                    // $this->processRowImage($tier = $tier - 1, $row = ($row - 1) / 2);
                    $this->processRowImage($tier - 1, floor(($row - 1) / 2));
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row mod 2 check after<br />" . PHP_EOL;
                    }
                }
                elseif ($row == $rowsForTier - 1) {
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row rowsForTier=$rowsForTier check before<br />" . PHP_EOL;
                    }
                    // $this->processRowImage($tier = $tier - 1, $row = $row / 2);
                    $this->processRowImage($tier - 1, floor($row / 2));
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row rowsForTier=$rowsForTier check after<br />" . PHP_EOL;
                    }
                }
            }
        }
    }

    /**
     * Crop an image to a size.
     *
     * @return ressource identifier of the image.
     */
    function imageCrop($image, $left, $upper, $right, $lower)
    {
        // $x = imagesx($image);
        // $y = imagesy($image);
        // if ($this->_debug) {
        //     print "imageCrop x=$x y=$y left=$left upper=$upper right=$right lower=$lower<br />" . PHP_EOL;
        // }
        $w = abs($right - $left);
        $h = abs($lower - $upper);
        $crop = imagecreatetruecolor($w, $h);
        imagecopy($crop, $image, 0, 0, $left, $upper, $w, $h);
        return $crop;
    }

    /**
     * Return the name of the tile group for the indicated tile.
     *
     * @return string
     */
    function getAssignedTileContainerName($tileFileName)
    {
        if ($tileFileName) {
            // print "getAssignedTileContainerName tileFileName $tileFileName exists<br />" . PHP_EOL;
            // if (isset($this->_tileGroupMappings)) {
            //     print "getAssignedTileContainerName this->_tileGroupMappings defined<br />" . PHP_EOL;
            // }
            // if ($this->_tileGroupMappings) {
            //     print "getAssignedTileContainerName this->_tileGroupMappings is true" . PHP_EOL;
            // }
            if (isset($this->_tileGroupMappings) && $this->_tileGroupMappings) {
                if (isset($this->_tileGroupMappings[$tileFileName])) {
                    $containerName = $this->_tileGroupMappings[$tileFileName];
                    if ($containerName) {
                        // print "getAssignedTileContainerName returning containerName " . $containerName ."<br />" . PHP_EOL;
                        return $containerName;
                    }
                }
            }
        }
        $containerName = $this->getNewTileContainerName();
        if ($this->_debug) {
            print "getAssignedTileContainerName returning getNewTileContainerName " . $containerName . "<br />" . PHP_EOL;
        }

        return $containerName;
    }


    /**
     * Explode a filepath in a root and an extension, i.e. "/path/file.ext" to
     * "/path/file" and ".ext".
     *
     * @return array
     */
    function getRootAndDotExtension($filepath)
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $root = $extension ? substr($filepath, 0, strrpos($filepath, '.')) : $filepath;
        return array($root, $extension);
    }

    /**
     * Given an image name, load it and extract metadata.
     */
    function getImageMetadata()
    {
        list($this->_originalWidth, $this->_originalHeight, $this->_originalFormat) = getimagesize($this->_imageFilename);

        // Get scaling information.
        $width = $this->_originalWidth;
        $height = $this->_originalHeight;
        if ($this->_debug) {
            print "getImageMetadata for file $this->_imageFilename originalWidth=$width originalHeight=$height tilesize=$this->tileSize<br />" . PHP_EOL;
        }
        $width_height = array($width, $height);
        array_unshift($this->_scaleInfo, $width_height);
        while (($width > $this->tileSize) || ($height > $this->tileSize)) {
            $width = floor($width / 2);
            $height = floor($height / 2);
            $width_height = array($width, $height);
            array_unshift($this->_scaleInfo, $width_height);
            if ($this->_debug) {
                print "getImageMetadata newWidth=$width newHeight=$height<br />" . PHP_EOL;
            }
        }

        // Tile and tile group information.
        $this->preProcess();
    }

    /**
     * Create a container for the next group of tiles within the data container.
     */
    function createTileContainer($tileContainerName = '')
    {
        $tileContainerPath = $this->_saveToLocation . DIRECTORY_SEPARATOR . $tileContainerName;

        if (!is_dir($tileContainerPath)) {
            // echo "Trying to make $tileContainerPath<br />" . PHP_EOL;
            mkdir($tileContainerPath);
            if ($this->updatePerms) {
                @chmod($tileContainerPath, $this->dirMode);
                @chgrp($tileContainerPath, $this->fileGroup);
            }
        }
    }

    /**
     * Create a container (a folder) for tiles and tile metadata if not set.
     */
    function createDataContainer($imageName)
    {
        if ($this->destinationDir) {
            $location = $this->destinationDir;
        }
        //Determine the path to the directory from the filepath.
        else {
            list($root, $ext) = $this->getRootAndDotExtension($imageName);
            $directory = dirname($root);
            $filename = basename($root);
            $root = $filename . '_zdata';
            $location = $directory . DIRECTORY_SEPARATOR . $root;
        }

        $this->_saveToLocation = $location;

        // If the paths already exist, an image is being re-processed, clean up
        // for it.
        if (is_dir($this->_saveToLocation)) {
            $rm_err = rm($this->_saveToLocation);
        }
        mkdir($this->_saveToLocation);
        if ($this->updatePerms) {
            @chmod($this->_saveToLocation, $this->dirMode);
            @chgrp($this->_saveToLocation, $this->fileGroup);
        }
    }

    /**
     * Get the full path of the file the tile will be saved as.
     *
     * @return string
     */
    function getFileReference($scaleNumber, $columnNumber, $rowNumber)
    {
        $tileFileName = $this->getTileFileName($scaleNumber, $columnNumber, $rowNumber);
        $tileContainerName = $this->getAssignedTileContainerName($tileFileName);
        return $this->_saveToLocation . DIRECTORY_SEPARATOR . $tileContainerName . DIRECTORY_SEPARATOR . $tileFileName;
    }

    /**
     * Get the number of tiles generated.
     *
     * @return integer
     */
    function getNumberOfTiles()
    {
        # return len(os.listdir($this->_tileContainerPath))
        return $this->_numberOfTiles;
    }

    /**
     * Create xml metadata about the tiles
     *
     * @return string
     */
    function getXMLOutput()
    {
        $numberOfTiles = $this->getNumberOfTiles();
        $xmlOutput = '<IMAGE_PROPERTIES WIDTH="' . $this->_originalWidth . '" HEIGHT="' . $this->_originalHeight . '" NUMTILES="' . $numberOfTiles . '" NUMIMAGES="1" VERSION="1.8" TILESIZE="' . $this->tileSize . '" />' . PHP_EOL;
        return $xmlOutput;
    }

    /**
     * Save xml metadata about the tiles.
     */
    function saveXMLOutput()
    {
        $xmlFile = fopen($this->_saveToLocation . DIRECTORY_SEPARATOR . 'ImageProperties.xml', 'w');
        fwrite($xmlFile, $this->getXMLOutput());
        fclose($xmlFile);
        if ($this->updatePerms) {
            @chmod($this->_saveToLocation . DIRECTORY_SEPARATOR . 'ImageProperties.xml', $this->fileMode);
            @chgrp($this->_saveToLocation . DIRECTORY_SEPARATOR . 'ImageProperties.xml', $this->fileGroup);
        }
    }

    /**
     * Save the cropped region.
     */
    function saveTile($image, $scaleNumber, $column, $row)
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
