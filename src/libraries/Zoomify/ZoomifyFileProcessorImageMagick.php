<?php
/**
 * Copyright (C) 2005  Adam Smith  asmith@agile-software.com
 *
 * Ported from Python to PHP by Wes Wright
 * Cleanup for Drupal by Karim Ratib (kratib@open-craft.com)
 * Cleanup for Omeka by Daniel Berthereau (daniel.github@berthereau.net)
 * Conversion to ImageMagick by Daniel Berthereau
 *
 * @internal
 * This adaptation of the original ZoomifyFileProcessor doesn't use ImageMagick
 * functions to create multiple tiles automagically. The process is stricly the
 * same than the original, so it can be improved.
 * @todo Use functions allowing to create multiple tiles in one time.
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
 * ZoomifyFileProcessor class.
 */
class ZoomifyFileProcessor
{
    public $_debug = false;

    public $fileMode = 0644;
    public $dirMode = 0755;
    public $fileGroup = 'www-data';

    public $tileSize = 256;
    public $qualitySetting = 80;

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
    public function ZoomifyProcess($image_name)
    {
        $this->_imageFilename = realpath($image_name);
        $this->_createDataContainer();
        $this->_getImageMetadata();
        $this->_processImage();
        $this->_saveXMLOutput();
    }

    /**
     * Explode a filepath in a root and an extension, i.e. "/path/file.ext" to
     * "/path/file" and ".ext".
     *
     * @return array
     */
    protected function _getRootAndDotExtension($filepath)
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
    protected function _getTileFilename($scaleNumber, $columnNumber, $rowNumber)
    {
        return (string)$scaleNumber . '-' . (string)$columnNumber . '-' . (string)$rowNumber . '.' . $this->_tileExt;
    }

    /**
     * Return the name of the next tile group container.
     *
     * @return string
     */
    protected function _getNewTileContainerName($tileGroupNumber = 0)
    {
        return 'TileGroup' . (string)$tileGroupNumber;
    }

    /**
     * Get the full path of the file the tile will be saved as.
     *
     * @return string
     */
    protected function _getFileReference($scaleNumber, $columnNumber, $rowNumber)
    {
        $tileFilename = $this->_getTileFilename($scaleNumber, $columnNumber, $rowNumber);
        $tileContainerName = $this->_getAssignedTileContainerName($tileFilename);
        return $this->_saveToLocation . DIRECTORY_SEPARATOR . $tileContainerName . DIRECTORY_SEPARATOR . $tileFilename;
    }

    /**
     * Return the name of the tile group for the indicated tile.
     *
     * @return string
     */
    protected function _getAssignedTileContainerName($tileFilename)
    {
        if ($tileFilename) {
            // print "getAssignedTileContainerName tileFilename $tileFilename exists<br />" . PHP_EOL;
            // if (isset($this->_tileGroupMappings)) {
            //     print "getAssignedTileContainerName this->_tileGroupMappings defined<br />" . PHP_EOL;
            // }
            // if ($this->_tileGroupMappings) {
            //     print "getAssignedTileContainerName this->_tileGroupMappings is true" . PHP_EOL;
            // }
            if (isset($this->_tileGroupMappings) && $this->_tileGroupMappings) {
                if (isset($this->_tileGroupMappings[$tileFilename])) {
                    $containerName = $this->_tileGroupMappings[$tileFilename];
                    if ($containerName) {
                        // print "getAssignedTileContainerName returning containerName " . $containerName ."<br />" . PHP_EOL;
                        return $containerName;
                    }
                }
            }
        }
        $containerName = $this->_getNewTileContainerName();
        if ($this->_debug) {
            print "getAssignedTileContainerName returning getNewTileContainerName " . $containerName . "<br />" . PHP_EOL;
        }

        return $containerName;
    }

    /**
     * Given an image name, load it and extract metadata.
     */
    protected function _getImageMetadata()
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
        $this->_preProcess();
    }

    /**
     * Create a container (a folder) for tiles and tile metadata.
     */
    protected function _createDataContainer()
    {
        list($root, $ext) = $this->_getRootAndDotExtension($this->_imageFilename);
        $directory = dirname($root);
        $filename = basename($root);
        $root = $filename . '_zdata';

        $this->_saveToLocation = $directory . DIRECTORY_SEPARATOR . $root;

        // If the paths already exist, an image is being re-processed, clean up
        // for it.
        if (is_dir($this->_saveToLocation)) {
            $rm_err = $this->_rmDir($this->_saveToLocation);
        }
        mkdir($this->_saveToLocation);
        @chmod($this->_saveToLocation, $this->dirMode);
        @chgrp($this->_saveToLocation, $this->fileGroup);
    }

    /**
     * Create a container for the next group of tiles within the data container.
     */
    protected function _createTileContainer($tileContainerName = '')
    {
        $tileContainerPath = $this->_saveToLocation . DIRECTORY_SEPARATOR . $tileContainerName;

        if (!is_dir($tileContainerPath)) {
            mkdir($tileContainerPath);
            @chmod($tileContainerPath, $this->dirMode);
            @chgrp($tileContainerPath, $this->fileGroup);
        }
    }

    /**
     * Plan for the arrangement of the tile groups.
     */
    protected function _preProcess()
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
                $tileFilename = $this->_getTileFilename($tier, $column, $row);
                $tileContainerName = $this->_getNewTileContainerName($tileGroupNumber);

                if ($numberOfTiles == 0) {
                    $this->_createTileContainer($tileContainerName);
                }
                elseif ($numberOfTiles % $this->tileSize == 0) {
                    $tileGroupNumber++;
                    $tileContainerName = $this->_getNewTileContainerName($tileGroupNumber);
                    $this->_createTileContainer($tileContainerName);

                    if ($this->_debug) {
                        print 'new tile group ' . $tileGroupNumber . ' tileContainerName=' . $tileContainerName ."<br />" . PHP_EOL;
                    }
                }
                $this->_tileGroupMappings[$tileFilename] = $tileContainerName;
                $numberOfTiles++;

                // for the next tile, set lower right cropping point
                $lr_x = ($ul_x + $this->tileSize < $width) ? $ul_x + $this->tileSize : $width;
                $lr_y = ($ul_y + $this->tileSize < $height) ? $ul_y + $this->tileSize : $height;

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
    protected function _processImage()
    {
        // Start from the last scale (bigger image).
        $tier = (count($this->_scaleInfo) - 1);
        $row = 0;
        $ul_y = 0;
        $lr_y = 0;

        list($root, $ext) = $this->_getRootAndDotExtension($this->_imageFilename);

        if ($this->_debug) {
            print "processImage root=$root ext=$ext<br />" . PHP_EOL;
        }
        // Create a row from the original image and process it.
        while ($row * $this->tileSize < $this->_originalHeight) {
            $ul_y = $row * $this->tileSize;
            $lr_y = ($ul_y + $this->tileSize < $this->_originalHeight) ? $ul_y + $this->tileSize : $this->_originalHeight;
            $width = $this->_originalWidth;
            $height = abs($lr_y - $ul_y);
            // print "line " . __LINE__ . " calling crop<br />" . PHP_EOL;
            # imageRow = image.crop([0, ul_y, $this->_originalWidth, lr_y])
            // $imageRow = imageCrop($image, 0, $ul_y, $this->originalWidth, $lr_y);
            $saveFilename = $root . $tier . '-' . $row . '.' . $ext;
            $imageRow = new Imagick();
            $imageRow->readImage($this->_imageFilename);
            $imageRow->cropImage($width, $height, 0, $ul_y);
            $imageRow->writeImage($saveFilename);
            $imageRow->destroy();
            @chmod($saveFilename, $this->fileMode);
            @chgrp($saveFilename, $this->fileGroup);
            if ($this->_debug) {
                print "processImage root=$root tier=$tier row=$row saveFilename=$saveFilename<br />" . PHP_EOL;
            }

            $this->_processRowImage($tier, $row);
            $row++;
        }
    }

    /**
     * For a row image, create and save tiles.
     */
    protected function _processRowImage($tier = 0, $row = 0)
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

        list($root, $ext) = $this->_getRootAndDotExtension($this->_imageFilename);

        $imageRow = null;

        // Create row for the current tier.
        // First tier.
        if ($tier == count($this->_scaleInfo) - 1) {
            $firstTierRowFile = $root . $tier . '-' . $row . '.' . $ext;
            if ($this->_debug) {
                print "firstTierRowFile=$firstTierRowFile<br />" . PHP_EOL;
            }
            if (is_file($firstTierRowFile)) {
                //$imageRow = imagecreatefromjpeg($firstTierRowFile);
                $imageRow = new Imagick();
                $imageRow->readImage($firstTierRowFile);
                if ($this->_debug) {
                    print "firstTierRowFile exists<br />" . PHP_EOL;
                }
            }
        }

        // Instead of use of original image, the image for the current tier is
        // rebuild from the previous tier's row (first and eventual second
        // rows). It allows a quicker resize.
        // TODO Use an automagic tiling and check if it's quicker.
        else {
            // Create an empty file in case where there are no first or second
            // row file.
            // $imageRow = imagecreatetruecolor($tierWidth, $this->tileSize);
            $imageRow = new Imagick();
            $imageRow->newImage($tierWidth, $this->tileSize, 'none', $this->_tileExt);

            $t = $tier + 1;
            $r = $row + $row;

            $firstRowFile = $root . $t . '-' . $r . '.' . $ext;
            $firstRowWidth = 0;
            $firstRowHeight = 0;
            if ($this->_debug) {
                print "create this row from previous tier's rows tier=$tier row=$row firstRowFile=$firstRowFile<br />" . PHP_EOL;
            }
            if ($this->_debug) {
                print "imageRow tierWidth=$tierWidth tierHeight= $this->tileSize<br />" . PHP_EOL;
            }

            if (is_file($firstRowFile)) {
                # print firstRowFile + ' exists, try to open...'
                // imagecopy(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int src_w, int src_h )
                // imagecopy($imageRow, $firstRowImage, 0, 0, 0, 0, $firstRowWidth, $firstRowHeight);
                // imagecopyresized(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h)
                // imagecopyresized($imageRow, $firstRowImage, 0, 0, 0, 0, $tierWidth, $firstRowHeight, $firstRowWidth, $firstRowHeight);

                // Take all the existing first row image and resize it to tier
                // width and image row half height.
                $firstRowImage = new Imagick();
                $firstRowImage->readImage($firstRowFile);
                $firstRowWidth = $firstRowImage->getImageWidth();
                $firstRowHeight = $firstRowImage->getImageHeight();
                $firstRowImage->resizeImage($tierWidth, $firstRowHeight, Imagick::FILTER_LANCZOS, 1, false);
                $imageRow->compositeImage($firstRowImage, $firstRowImage->getImageCompose(), 0, 0);
                $firstRowImage->destroy();
                unlink($firstRowFile);
                if ($this->_debug) {
                    print "imageRow imagecopyresized tierWidth=$tierWidth imageRowHalfHeight=" . floor($this->tileSize / 2) . " firstRowWidth=$firstRowWidth firstRowHeight=$firstRowHeight<br />" . PHP_EOL;
                }
            }

            $r++;
            $secondRowFile = $root . $t . '-' . $r . '.' . $ext;
            $secondRowWidth = 0;
            $secondRowHeight = 0;
            if ($this->_debug) {
                print "create this row from previous tier's rows tier=$tier row=$row secondRowFile=$secondRowFile<br />" . PHP_EOL;
            }
            // There may not be a second row at the bottom of the image...
            // If any, copy this second row file at the bottom of the row image.
            if (is_file($secondRowFile)) {
                if ($this->_debug) {
                    print $secondRowFile . " exists, try to open...<br />" . PHP_EOL;
                }
                // imagecopy(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int src_w, int src_h )
                // imagecopy($imageRow, $secondRowImage, 0, $firstRowWidth, 0, 0, $firstRowWidth, $firstRowHeight);
                // imagecopyresampled(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h)
                // imagecopyresampled($imageRow, $secondRowImage, 0, $imageRowHalfHeight, 0, 0, $tierWidth, $secondRowHeight, $secondRowWidth, $secondRowHeight);

                // As imageRow isn't empty, the second row file is resized, then
                // copied in the bottom of imageRow, then the second row file is
                // deleted.
                $imageRowHalfHeight = floor($this->tileSize / 2);
                $secondRowImage = new Imagick();
                $secondRowImage->readImage($secondRowFile);
                $secondRowWidth = $secondRowImage->getImageWidth();
                $secondRowHeight = $secondRowImage->getImageHeight();
                $secondRowImage->resizeImage($tierWidth, $secondRowHeight, Imagick::FILTER_LANCZOS, 1, false);
                $imageRow->compositeImage($secondRowImage, $secondRowImage->getImageCompose(), 0, $imageRowHalfHeight);
                $secondRowImage->destroy();
                unlink($secondRowFile);
                if ($this->_debug) {
                    print "imageRow imagecopyresized tierWidth=$tierWidth imageRowHalfHeight= $imageRowHalfHeight firstRowWidth=$firstRowWidth firstRowHeight=$firstRowHeight<br />" . PHP_EOL;
                }
            }

            // The last row may be less than $this->tileSize...
            $rowHeight = $firstRowHeight + $secondRowHeight;
            $tileHeight = $this->tileSize * 2;
            if (($firstRowHeight + $secondRowHeight) < $this->tileSize * 2) {
                if ($this->_debug) {
                    print "line " . __LINE__ . " calling crop rowHeight=$rowHeight tileHeight=$tileHeight<br />" . PHP_EOL;
                }
                # imageRow = imageRow.crop((0, 0, tierWidth, (firstRowHeight + secondRowHeight)))
                // $imageRow = imageCrop($imageRow, 0, 0, $tierWidth, $firstRowHeight + $secondRowHeight);
                $imageRow->cropImage($tierWidth, $firstRowHeight + $secondRowHeight, 0, 0);
            }
        }

        // Create tiles for the current image row.
        if ($imageRow) {
            // Cycle through columns, then rows.
            $column = 0;
            $imageWidth = $imageRow->getImageWidth();
            $imageHeight = $imageRow->getImageHeight();
            $ul_x = 0;
            $ul_y = 0;
            $lr_x = 0;
            $lr_y = 0;
            // TODO Use an automatic tiling.
            while (!(($lr_x == $imageWidth) && ($lr_y == $imageHeight))) {
                if ($this->_debug) {
                    print "ul_x=$ul_x lr_x=$lr_x ul_y=$ul_y lr_y=$lr_y imageWidth=$imageWidth imageHeight=$imageHeight<br />" . PHP_EOL;
                }
                // Set lower right cropping point.
                $lr_x = (($ul_x + $this->tileSize) < $imageWidth) ? $ul_x + $this->tileSize : $imageWidth;
                $lr_y = (($ul_y + $this->tileSize) < $imageHeight) ? $ul_y + $this->tileSize : $imageHeight;
                $width = abs($lr_x - $ul_x);
                $height = abs($lr_y - $ul_y);

                # tierLabel = len($this->_scaleInfo) - tier
                if ($this->_debug) {
                    print "line " . __LINE__ . " calling crop<br />" . PHP_EOL;
                }
                $tileFilename = $this->_getFileReference($tier, $column, $row);
                // $this->saveTile(imageCrop($imageRow, $ul_x, $ul_y, $lr_x, $lr_y), $tier, $column, $row);

                $tileImage = clone $imageRow;
                // Clean the canvas.
                $tileImage->setImagePage(0, 0, 0, 0);
                $tileImage->cropImage($width, $height, $ul_x, $ul_y);
                $tileImage->setImageFormat($this->_tileExt);
                $tileImage->setImageCompression(Imagick::COMPRESSION_JPEG);
                $tileImage->setImageCompressionQuality($this->qualitySetting);
                $tileImage->writeImage($tileFilename);
                $tileImage->destroy();
                @chmod($tileFilename, $this->fileMode);
                @chgrp($tileFilename, $this->fileGroup);
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

            // Create a new sample for the current tier, then process next tiers
            // via a recursive call.
            if ($tier > 0) {
                $halfWidth = max(1, floor($imageWidth / 2));
                $halfHeight = max(1, floor($imageHeight / 2));
                $rowFilename = $root . $tier . '-' . $row . '.' . $ext;
                # print 'resize as ' + str(imageWidth/2) + ' by ' + str(imageHeight/2) + ' (or ' + str(halfWidth) + ' x ' + str(halfHeight) + ')'
                # tempImage = imageRow.resize((imageWidth / 2, imageHeight / 2), PIL.Image.ANTIALIAS)
                # tempImage = imageRow.resize((halfWidth, halfHeight), PIL.Image.ANTIALIAS)
                // $tempImage = imagecreatetruecolor($halfWidth, $halfHeight);
                // imagecopyresampled(resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h)
                // imagecopyresampled($tempImage, $imageRow, 0, 0, 0, 0, $halfWidth, $halfHeight, $imageWidth, $imageHeight);
                # tempImage.save(root + str(tier) + '-' + str(row) + ext)
                $tempImage = clone $imageRow;
                $tempImage->resizeImage($halfWidth, $halfHeight, Imagick::FILTER_LANCZOS, 1, false);
                $tempImage->writeImage($rowFilename);
                $tempImage->destroy();
                @chmod($rowFilename, $this->fileMode);
                @chgrp($rowFilename, $this->fileGroup);
            }

            // http://greengaloshes.cc/2007/05/zoomifyimage-ported-to-php/#comment-451
            $imageRow->destroy();

            // Process next tiers via a recursive call.
            if ($tier > 0) {
                if ($this->_debug) {
                    print "processRowImage final checks for tier $tier row=$row rowsForTier=$rowsForTier<br />" . PHP_EOL;
                }
                if ($row % 2 != 0) {
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row mod 2 check before<br />" . PHP_EOL;
                    }
                    // $this->processRowImage($tier = $tier - 1, $row = ($row - 1) / 2);
                    $this->_processRowImage($tier - 1, floor(($row - 1) / 2));
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row mod 2 check after<br />" . PHP_EOL;
                    }
                }
                elseif ($row == $rowsForTier - 1) {
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row rowsForTier=$rowsForTier check before<br />" . PHP_EOL;
                    }
                    // $this->processRowImage($tier = $tier - 1, $row = $row / 2);
                    $this->_processRowImage($tier - 1, floor($row / 2));
                    if ($this->_debug) {
                        print "processRowImage final checks tier=$tier row=$row rowsForTier=$rowsForTier check after<br />" . PHP_EOL;
                    }
                }
            }
        }
    }

    /**
     * Save xml metadata about the tiles.
     *
     * @return void
     */
    protected function _saveXMLOutput()
    {
        $xmlFile = fopen($this->_saveToLocation . DIRECTORY_SEPARATOR . 'ImageProperties.xml', 'w');
        fwrite($xmlFile, $this->_getXMLOutput());
        fclose($xmlFile);
        @chmod($this->_saveToLocation . DIRECTORY_SEPARATOR . 'ImageProperties.xml', $this->fileMode);
        @chgrp($this->_saveToLocation . DIRECTORY_SEPARATOR . 'ImageProperties.xml', $this->fileGroup);
    }

    /**
     * Create xml metadata about the tiles
     *
     * @return string
     */
    protected function _getXMLOutput()
    {
        $xmlOutput = '<IMAGE_PROPERTIES WIDTH="' . $this->_originalWidth . '" HEIGHT="' . $this->_originalHeight . '" NUMTILES="' . $this->_numberOfTiles . '" NUMIMAGES="1" VERSION="1.8" TILESIZE="' . $this->tileSize . '" />';
        return $xmlOutput;
    }

    /**
     * Remove a dir from filesystem.
     *
     * @param string $dirpath
     * @return boolean
     */
    protected function _rmDir($dirPath)
    {
        $files = array_diff(scandir($dirPath), array('.', '..'));
        foreach ($files as $file) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->_rmDir($path);
            }
            else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }
}
