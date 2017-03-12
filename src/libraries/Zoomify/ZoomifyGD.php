<?php
namespace Zoomify;

/**
 * Copyright (C) 2005  Adam Smith  asmith@agile-software.com
 * Copyright (C) Wes Wright [http://greengaloshes.cc]
 * Copyright (C) Justin Henry [http://greengaloshes.cc]
 * Copyright (C) 2014-2017 Daniel Berthereau Daniel.github@Berthereau.net
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

class ZoomifyGD extends Zoomify
{

    /**
     * Constructor.
     *
     * @param array $config
     */
    function __construct(array $config = array())
    {
        $this->config = $config;
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
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
        return $this->zoomifyImage($filepath, $destinationDir);
    }

    /**
     * Starting with the original image, start processing each row.
     */
    protected function processImage()
    {
        // Start from the last scale (bigger image).
        $tier = (count($this->_scaleInfo) - 1);
        $row = 0;
        $ul_y = 0;
        $lr_y = 0;

        list($root, $ext) = $this->getRootAndDotExtension($this->_imageFilename);

        // Create a row from the original image and process it.
        $image = $this->openImage();
        while ($row * $this->tileSize < $this->_originalHeight) {
            $ul_y = $row * $this->tileSize;
            $lr_y = ($ul_y + $this->tileSize < $this->_originalHeight)
                ? $ul_y + $this->tileSize
                : $this->_originalHeight;
            $saveFilename = $root . '-' . $tier . '-' . $row . '.' . $ext;
            $imageRow = $this->imageCrop($image, 0, $ul_y, $this->_originalWidth, $lr_y);
            touch($saveFilename);
            imagejpeg($imageRow, $saveFilename, 100);
            imagedestroy($imageRow);
            $this->processRowImage($tier, $row);
            ++$row;
        }
        imagedestroy($image);
    }

    /**
     * For a row image, create and save tiles.
     */
    protected function processRowImage($tier = 0, $row = 0)
    {
        list($tierWidth, $tierHeight) = $this->_scaleInfo[$tier];
        $rowsForTier = floor($tierHeight / $this->tileSize);
        if ($tierHeight % $this->tileSize > 0) {
            ++$rowsForTier;
        }

        list($root, $ext) = $this->getRootAndDotExtension($this->_imageFilename);

        $imageRow = null;

        // Create row for the current tier.
        // First tier.
        if ($tier == count($this->_scaleInfo) - 1) {
            $firstTierRowFile = $root . '-' . $tier . '-' . $row . '.' . $ext;
            if (is_file($firstTierRowFile)) {
                $imageRow = imagecreatefromjpeg($firstTierRowFile);
            }
        }

        // Instead of use of original image, the image for the current tier is
        // rebuild from the previous tier's row (first and eventual second
        // rows). It allows a quicker resize.
        else {
            // Create an empty file in case where there are no first row file.
            $imageRow = imagecreatetruecolor($tierWidth, $this->tileSize);

            $t = $tier + 1;
            $r = $row * 2;

            $firstRowFile = $root . '-' . $t . '-' . $r . '.' . $ext;
            $firstRowWidth = 0;
            $firstRowHeight = 0;

            if (is_file($firstRowFile)) {
                // Take all the existing first row image and resize it to tier
                // width and image row half height.
                $firstRowImage = imagecreatefromjpeg($firstRowFile);
                $firstRowWidth = imagesx($firstRowImage);
                $firstRowHeight = imagesy($firstRowImage);
                $imageRowHalfHeight = floor($this->tileSize / 2);
                // Bug: Use $firstRowHeight instead of $imageRowHalfHeight.
                // See Drupal Zoomify module http://drupalcode.org/project/zoomify.git/blob_plain/e2f977ab4b153b4ce6d1a486a1fe80ecf9512559:/ZoomifyFileProcessor.php.
                imagecopyresampled($imageRow, $firstRowImage, 0, 0, 0, 0, $tierWidth, $firstRowHeight, $firstRowWidth, $firstRowHeight);
//                 unlink($firstRowFile);
            }

            ++$r;
            $secondRowFile =  $root . '-' . $t . '-' . $r . '.' . $ext;
            $secondRowWidth = 0;
            $secondRowHeight = 0;

            // There may not be a second row at the bottom of the image...
            // If any, copy this second row file at the bottom of the row image.
            if (is_file($secondRowFile)) {
                // As imageRow isn't empty, the second row file is resized, then
                // copied in the bottom of imageRow, then the second row file is
                // deleted.
                $secondRowImage = imagecreatefromjpeg($secondRowFile);
                $secondRowWidth = imagesx($secondRowImage);
                $secondRowHeight = imagesy($secondRowImage);
                $imageRowHalfHeight = floor($this->tileSize / 2);

                // As imageRow isn't empty, the second row file is resized, then
                // copied in the bottom of imageRow, then the second row file is
                // deleted.
                imagecopyresampled($imageRow, $secondRowImage, 0, $imageRowHalfHeight, 0, 0, $tierWidth, $secondRowHeight, $secondRowWidth, $secondRowHeight);
//                 unlink($secondRowFile);
            }

            // The last row may be less than $this->tileSize...
            $rowHeight = $firstRowHeight + $secondRowHeight;
            $tileHeight = $this->tileSize * 2;
            $tierHeightCheck = $firstRowHeight + $secondRowHeight;
            if ($tierHeightCheck < $tileHeight) {
                $imageRow = $this->imageCrop($imageRow, 0, 0, $tierWidth, $tierHeightCheck);
            }
        }

        // Create tiles for the current image row.
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
                // Set lower right cropping point.
                $lr_x = (($ul_x + $this->tileSize) < $imageWidth)
                    ? $ul_x + $this->tileSize
                    : $imageWidth;
                $lr_y = (($ul_y + $this->tileSize) < $imageHeight)
                    ? $ul_y + $this->tileSize
                    : $imageHeight;

                $tileFilename = $this->getFileReference($tier, $column, $row);

                $tileImage = $this->imageCrop($imageRow, $ul_x, $ul_y, $lr_x, $lr_y);
                $this->saveImageToFile($tileImage, $tileFilename);

                $this->_numberOfTiles++;

                // Set upper left cropping point.
                if ($lr_x == $imageWidth) {
                    $ul_x = 0;
                    $ul_y = $lr_y;
                    $column = 0;
                    #row += 1
                }
                else {
                    $ul_x = $lr_x;
                    ++$column;
                }
            }

            // Create a new sample for the current tier, then process next tiers
            // via a recursive call.
            if ($tier > 0) {
                $halfWidth = max(1, floor($imageWidth / 2));
                $halfHeight = max(1, floor($imageHeight / 2));
                // Warning: the name is the current tier, so the file for the
                //previous tier, if it exists, is removed.
                $rowFilename = $root . '-' . $tier . '-' . $row . '.' . $ext;

                $tempImage = imagecreatetruecolor($halfWidth, $halfHeight);
                imagecopyresampled($tempImage, $imageRow, 0, 0, 0, 0, $halfWidth, $halfHeight, $imageWidth, $imageHeight);
                touch($rowFilename);
                imagejpeg($tempImage, $rowFilename);
                imagedestroy($tempImage);
            }

            // http://greengaloshes.cc/2007/05/zoomifyimage-ported-to-php/#comment-451
            imagedestroy($imageRow);

            // Process next tiers via a recursive call.
            if ($tier > 0) {
                if ($row % 2 != 0) {
                    $this->processRowImage($tier - 1, floor(($row - 1) / 2));
                }
                elseif ($row == $rowsForTier - 1) {
                    $this->processRowImage($tier - 1, floor($row / 2));
                }
            }
        }
    }

    /**
     * Load the image data.
     *
     * @return ressource identifier of the image.
     */
    protected function openImage()
    {
        return $this->getImageFromFile($this->_imageFilename);
    }

    /**
     * Helper to get an image of different type (jpg, png or gif) from file.
     *
     * @param string $filepath
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
     * Save an image according to the main format.
     *
     * @param resource $image
     * @param string $filepath
     * @return void
     */
    protected function saveImageToFile($image, $filepath)
    {
        touch($filepath);
        switch (strtolower(pathinfo($filepath, PATHINFO_EXTENSION))) {
            case 'png':
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $filepath, $this->tileQuality);
                break;
            case 'gif':
                imagegif($image, $filepath, $this->tileQuality);
                break;
            case 'jpg':
            case 'jpe':
            case 'jpeg':
            default:
                imagejpeg($image, $filepath, $this->tileQuality);
                break;
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
}
