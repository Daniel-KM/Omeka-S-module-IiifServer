<?php
namespace Zoomify;

/**
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
 *
 * @internal
 * This adaptation of the original ZoomifyFileProcessor doesn't use ImageMagick
 * functions to create multiple tiles automagically. The process is stricly the
 * same than the original, so it can be improved.
 * @todo Use functions allowing to create multiple tiles in one time.
 */

class ZoomifyImageMagick extends Zoomify
{

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
        while ($row * $this->tileSize < $this->_originalHeight) {
            $ul_y = $row * $this->tileSize;
            $lr_y = ($ul_y + $this->tileSize < $this->_originalHeight)
                ? $ul_y + $this->tileSize
                : $this->_originalHeight;
            $saveFilename = $root . '-' . $tier . '-' . $row . '.' . $ext;
            $width = $this->_originalWidth;
            $height = abs($lr_y - $ul_y);
            $crop = array();
            $crop['width'] = $width;
            $crop['height'] = $height;
            $crop['x'] = 0;
            $crop['y'] = $ul_y;
            $result = $this->imageResizeCrop($this->_imageFilename, $saveFilename, array(), $crop);

            $this->processRowImage($tier, $row);
            ++$row;
        }
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
        $imageRowSize = array();

        // Create row for the current tier.
        // First tier.
        if ($tier == count($this->_scaleInfo) - 1) {
            $firstTierRowFile = $root . '-' . $tier . '-' . $row . '.' . $ext;
            if (is_file($firstTierRowFile)) {
                $imageRow = ' -format ' . $this->_tileExt;
                $imageRow .= ' -quality ' . $this->tileQuality;
                $imageRow .= " $firstTierRowFile";
                list($imageRowSize['width'], $imageRowSize['height']) = getimagesize($firstTierRowFile);
            }
        }

        // Instead of use of original image, the image for the current tier is
        // rebuild from the previous tier's row (first and eventual second
        // rows). It allows a quicker resize.
        // TODO Use an automagic tiling and check if it's quicker.
        else {
            // Create an empty file in case where there are no first row file.
            $imageRow = ' -format ' . $this->_tileExt;
            $imageRow .= ' -quality ' . $this->tileQuality;
            $imageRow .= ' -size ' . escapeshellarg(sprintf('%dx%d', $tierWidth, $this->tileSize));
            $imageRowSize = array();
            $imageRowSize['width'] = $tierWidth;
            $imageRowSize['height'] = $this->tileSize;

            $t = $tier + 1;
            $r = $row * 2;

            $firstRowFile = $root . '-' . $t . '-' . $r . '.' . $ext;
            $firstRowWidth = 0;
            $firstRowHeight = 0;

            $isThereFirstRow = is_file($firstRowFile);

            if (is_file($firstRowFile)) {
                // Take all the existing first row image and resize it to tier
                // width and image row half height.
                list($firstRowWidth, $firstRowHeight) = getimagesize($firstRowFile);
                $imageRow .= ' \( '
                    . $firstRowFile
                    . ' -resize ' . escapeshellarg(sprintf('%dx%d!', $tierWidth, $firstRowHeight))
                    . ' \)';
                // unlink($firstRowFile);
            }

            ++$r;
            $secondRowFile = $root . '-' . $t . '-' . $r . '.' . $ext;
            $secondRowWidth = 0;
            $secondRowHeight = 0;

            // There may not be a second row at the bottom of the image...
            // If any, copy this second row file at the bottom of the row image.
            if (is_file($secondRowFile)) {
                // As imageRow isn't empty, the second row file is resized, then
                // copied in the bottom of imageRow, then the second row file is
                // deleted.
                $imageRowHalfHeight = floor($this->tileSize / 2);
                list($secondRowWidth, $secondRowHeight) = getimagesize($secondRowFile);
                $imageRow .= ' \( '
                    . $secondRowFile
                    . ' -resize ' . escapeshellarg(sprintf('%dx%d!', $tierWidth, $secondRowHeight))
                    . ' \)'
                    . ' -append';
                // unlink($secondRowFile);
            }

            // The last row may be less than $this->tileSize...
            $rowHeight = $firstRowHeight + $secondRowHeight;
            $tileHeight = $this->tileSize * 2;
            $tierHeightCheck = $firstRowHeight + $secondRowHeight;
            if ($tierHeightCheck < $tileHeight) {
                $imageRow .= ' -crop ' . escapeshellarg(sprintf('%dx%d', $tierWidth, $tierHeightCheck));
            }

            if (!$isThereFirstRow) {
                $imageRow = null;
            }
        }

        // Create tiles for the current image row.
        if ($imageRow) {
            // Cycle through columns, then rows.
            $column = 0;
            $imageWidth = $imageRowSize['width'];
            $imageHeight = isset($tierHeightCheck) ? $tierHeightCheck : $imageRowSize['height'];
            $ul_x = 0;
            $ul_y = 0;
            $lr_x = 0;
            $lr_y = 0;
            // TODO Use an automatic tiling.
            while (!(($lr_x == $imageWidth) && ($lr_y == $imageHeight))) {
                // Set lower right cropping point.
                $lr_x = (($ul_x + $this->tileSize) < $imageWidth)
                    ? $ul_x + $this->tileSize
                    : $imageWidth;
                $lr_y = (($ul_y + $this->tileSize) < $imageHeight)
                    ? $ul_y + $this->tileSize
                    : $imageHeight;
                $width = abs($lr_x - $ul_x);
                $height = abs($lr_y - $ul_y);

                $tileFilename = $this->getFileReference($tier, $column, $row);
                $command = $imageRow
                    . ' -page 0x0+0+0 '
                    . ' -crop ' . escapeshellarg(sprintf('%dx%d+%d+%d', $width, $height, $ul_x, $ul_y));

                $command = $this->convertPath
                    . ' ' . $command
                    . ' ' . escapeshellarg($tileFilename);
                $result = $this->execute($command);
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

                $command = $imageRow;
                $command .= ' +repage -flatten'
                    . ' -resize ' . escapeshellarg(sprintf('%sx%s!', $halfWidth, $halfHeight));
                $command = $this->convertPath
                    . ' ' . $command
                    . ' ' . escapeshellarg($rowFilename);
                $result = $this->execute($command);
            }

            if ($isThereRow) {
//                 @unlink($firstRowFile);
//                 @unlink($secondRowFile);
            }

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
     * Resize and crop an image via convert.
     *
     * @internal For resize, the size is forced (option "!").
     *
     * @param string $source
     * @param string $destination
     * @param array $resize Array with width and height.
     * @param array $crop Array with width, height, upper left x and y.
     * @return boolean
     */
    protected function imageResizeCrop($source, $destination, $resize = array(), $crop = array())
    {
        $params = array();
        // Clean the canvas.
        $params[] = '+repage';
        $params[] = '-flatten';
        $params[] = '-alpha remove';
        if ($resize) {
            $params[] = '-thumbnail ' . escapeshellarg(sprintf('%sx%s!', $resize['width'], $resize['height']));
        }
        if ($crop) {
            $params[] = '-crop ' . escapeshellarg(sprintf('%dx%d+%d+%d', $crop['width'], $crop['height'], $crop['x'], $crop['y']));
        }
        $params[] = '-quality ' . $this->tileQuality;

        $result = $this->convert($source, $destination, $params);
        return $result;
    }

    /**
     * Helper to process a convert command.
     *
     * @param string $source
     * @param string $destination
     * @param array $params
     * @return boolean
     */
    protected function convert($source, $destination, $params)
    {
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
    public function getConvertPath()
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
