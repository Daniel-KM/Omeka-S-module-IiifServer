<?php

/*
 * Copyright 2015-2017 Daniel Berthereau
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

namespace IiifServer\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class TileServer extends AbstractPlugin
{

    /**
     * Base url of tiles.
     *
     * @var string
     */
    protected $tileBaseUrl;

    /**
     * Retrieve tiles for an image, if any, according to the required transform.
     *
     * @internal Because the position of the requested region may be anything
     * (it depends of the client), until four images may be needed to build the
     * resulting image. It's always quicker to reassemble them rather than
     * extracting the part from the full image, specially with big ones.
     * Nevertheless, OpenSeaDragon tries to ask 0-based tiles, so only this case
     * is managed currently.
     * @todo For non standard requests, the tiled images may be used to rebuild
     * a fullsize image that is larger the Omeka derivatives. In that case,
     * multiple tiles should be joined.
     *
     * @param array $tileInfo
     * @param array $transform
     * @return array|null
     */
    public function __invoke(array $tileInfo, array $transform)
    {
        if (empty($tileInfo)) {
            return;
        }

        // Quick check of supported transformation of tiles.
        if (!in_array($transform['region']['feature'], ['regionByPx', 'full'])
            || !in_array($transform['size']['feature'], ['sizeByW', 'sizeByH', 'sizeByWh', 'sizeByWhListed', 'full'])
        ) {
            return;
        }

        $controller = $this->getController();
        $settings = $controller->settings();
        $viewHelpers = $controller->viewHelpers();
        $serverUrl = $viewHelpers->get('ServerUrl');
        $basePath = $viewHelpers->get('BasePath');
        $tileDir = $settings->get('iiifserver_image_tile_dir');

        // A full url avoids some complexity when Omeka is not the root of the
        // server.
        $this->tileBaseUrl = $serverUrl() . $basePath('files' . '/' . $tileDir);

        switch ($tileInfo['tile_type']) {
            case 'deepzoom':
                $tile = $this->serveTilesDeepzoom($tileInfo, $transform);
                return $tile;
            case 'zoomify':
                $tile = $this->serveTilesZoomify($tileInfo, $transform);
                return $tile;
        }
    }

    /**
     * Retrieve the data for a transformation.
     *
     * @internal The format of Zoomify is very different from the DeepZoom one,
     * so some checks and computs are needed.
     *
     * @param array $tileInfo
     * @param array $transform
     * @return array|null
     */
    protected function serveTilesDeepzoom($tileInfo, $transform)
    {
    }

    /**
     * Retrieve the data for a transformation.
     *
     * @internal The format of Zoomify is very different from the DeepZoom one,
     * so some checks and computs are needed.
     *
     * @param array $tileInfo
     * @param array $transform
     * @return array|null
     */
    protected function serveTilesZoomify($tileInfo, $transform)
    {
        $data = $this->getLevelAndPosition($tileInfo, $transform['source'], $transform['region'], $transform['size']);
        if (is_null($data)) {
            return;
        }

        $imageSize = [
            'width' => $transform['source']['width'],
            'height' => $transform['source']['height'],
        ];
        $tileGroup = $this->getTileGroup($imageSize, $data);
        if (is_null($tileGroup)) {
            return;
        }

        // To manage Windows, the same path cannot be used for url and local.
        $relativeUrl = sprintf('/TileGroup%d/%d-%d-%d.jpg',
            $tileGroup, $data['level'], $data['x'] , $data['y']);
        $relativePath = sprintf('%sTileGroup%d%s%d-%d-%d.jpg',
            DIRECTORY_SEPARATOR, $tileGroup,
            DIRECTORY_SEPARATOR, $data['level'], $data['x'] , $data['y']);

        $baseUrl = $this->tileBaseUrl . $tileInfo['baseurl'];
        $dirpath = dirname($tileInfo['path']);

        // The image url is used when there is no transformation.
        $imageUrl = $baseUrl . $relativeUrl;
        $imagePath = $dirpath . $relativePath;
        $derivativeType = 'tile';
        list($tileWidth, $tileHeight) = array_values($this->getWidthAndHeight($imagePath));

        $result = [
            'fileurl' => $imageUrl,
            'filepath' => $imagePath,
            'derivativeType' => $derivativeType,
            'mime_type' => 'image/jpeg',
            'width' => $tileWidth,
            'height' => $tileHeight,
        ];
        return $result;
    }

    /**
     * Get the level and the position of the cell from the source and region.
     *
     * @param array $tileInfo
     * @param array $source
     * @param array $region
     * @param array $size
     * @return array|null
     */
    protected function getLevelAndPosition($tileInfo, $source, $region, $size)
    {
        // Check if the tile may be cropped.
        $isFirstColumn = $region['x'] == 0;
        $isFirstRow = $region['y'] == 0;
        $isFirstCell = $isFirstColumn && $isFirstRow;
        $isLastColumn = $source['width'] == ($region['x'] + $region['width']);
        $isLastRow = $source['height'] == ($region['y'] + $region['height']);
        $isLastCell = $isLastColumn && $isLastRow;

        // TODO A bigger size can be requested directly, and, in that case,
        // multiple tiles should be joined.
        $cellSize = $tileInfo['size'];

        // Manage the base level.
        if ($isFirstCell && $isLastCell) {
            $level = 0;
            $cellX = 0;
            $cellY = 0;
        }

        // Else normal region.
        else {
            // Determine the position of the cell from the source and the
            // region.
            switch ($size['feature']) {
                case 'sizeByW':
                    if ($isLastColumn) {
                        // Normal row. The last cell is an exception.
                        if (!$isLastCell) {
                            // Use row, because Zoomify tiles are square.
                            $count = (integer) ceil(max($source['width'], $source['height']) / $region['height']);
                            $cellX = $region['x'] / $region['height'];
                            $cellY = $region['y'] / $region['height'];
                        }
                    }
                    // Normal column and normal region.
                    else {
                        $count = (integer) ceil(max($source['width'], $source['height']) / $region['width']);
                        $cellX = $region['x'] / $region['width'];
                        $cellY = $region['y'] / $region['width'];
                    }
                    break;

                case 'sizeByH':
                    if ($isLastRow) {
                        // Normal column. The last cell is an exception.
                        if (!$isLastCell) {
                            // Use column, because Zoomify tiles are square.
                            $count = (integer) ceil(max($source['width'], $source['height']) / $region['width']);
                            $cellX = $region['x'] / $region['width'];
                            $cellY = $region['y'] / $region['width'];
                        }
                    }
                    // Normal row and normal region.
                    else {
                        $count = (integer) ceil(max($source['width'], $source['height']) / $region['height']);
                        $cellX = $region['x'] / $region['height'];
                        $cellY = $region['y'] / $region['height'];
                    }
                    break;

                case 'sizeByWh':
                case 'sizeByWhListed':
                    // TODO To improve.
                    if ($isLastColumn) {
                        // Normal row. The last cell is an exception.
                        if (!$isLastCell) {
                            // Use row, because Zoomify tiles are square.
                            $count = (integer) ceil(max($source['width'], $source['height']) / $region['height']);
                            $cellX = $region['x'] / $region['width'];
                            $cellY = $region['y'] / $region['height'];
                        }
                    }
                    // Normal column and normal region.
                    else {
                        $count = (integer) ceil(max($source['width'], $source['height']) / $region['width']);
                        $cellX = $region['x'] / $region['width'];
                        $cellY = $region['y'] / $region['height'];
                    }
                    break;

                case 'full':
                    // TODO To be checked.
                    // Normalize the size, but they can be cropped.
                    $size['width'] = $region['width'];
                    $size['height'] = $region['height'];
                    $count = (integer) ceil(max($source['width'], $source['height']) / $region['width']);
                    $cellX = $region['x'] / $region['width'];
                    $cellY = $region['y'] / $region['height'];
                    break;

                default:
                    return;
            }

            // Get the list of squale factors.
            $squaleFactors = [];
            $maxSize = max($source['width'], $source['height']);
            $total = (integer) ceil($maxSize / $tileInfo['size']);
            $factor = 1;
            // If level is set, count is not set and useless.
            $level = isset($level) ? $level : 0;
            $count = isset($count) ? $count : 0;
            while ($factor / 2 < $total) {
                // This allows to determine the level for normal regions.
                if ($factor < $count) {
                    $level++;
                }
                $squaleFactors[] = $factor;
                $factor = $factor * 2;
            }

            // Process the last cell, an exception because it may be cropped.
            if ($isLastCell) {
                // TODO Quick check if the last cell is a standard one (not cropped)?
                // Because the default size of the region lacks, it is simpler
                // to check if an image of the zoomed file is the same using the
                // tile size from properties, for each possible factor.
                $reversedSqualeFactors = array_reverse($squaleFactors);
                $isLevelFound = false;
                foreach ($reversedSqualeFactors as $level => $reversedFactor) {
                    $tileFactor = $reversedFactor * $tileInfo['size'];
                    $countX = (integer) ceil($source['width'] / $tileFactor);
                    $countY = (integer) ceil($source['height'] / $tileFactor);
                    $lastRegionWidth = $source['width'] - (($countX -1) * $tileFactor);
                    $lastRegionHeight = $source['height'] - (($countY - 1) * $tileFactor);
                    $lastRegionX = $source['width'] - $lastRegionWidth;
                    $lastRegionY = $source['height'] - $lastRegionHeight;
                    if ($region['x'] == $lastRegionX
                        && $region['y'] == $lastRegionY
                        && $region['width'] == $lastRegionWidth
                        && $region['height'] == $lastRegionHeight
                    ) {
                        // Level is found.
                        $isLevelFound = true;
                        // Cells are 0-based.
                        $cellX = $countX - 1;
                        $cellY = $countY - 1;
                        break;
                    }
                }
                if (!$isLevelFound) {
                    return;
                }
            }
        }

        // TODO Check if the cell size is the required one (always true for image tiled here).

        return [
            'level' => $level,
            'x' => $cellX,
            'y' => $cellY,
            'size' => $cellSize,
        ];
    }

    /**
     * Return the tile group of a tile from level, position and size.
     *
     * @link https://github.com/openlayers/ol3/blob/master/src/ol/source/zoomifysource.js
     *
     * @param array $image
     * @param array $tile
     * @return integer|null
     */
    protected function getTileGroup($image, $tile)
    {
        if (empty($image) || empty($tile)) {
            return;
        }

        $tierSizeCalculation = 'default';
        // $tierSizeCalculation = 'truncated';

        $tierSizeInTiles = array();

        switch ($tierSizeCalculation) {
            case 'default':
                $tileSize = $tile['size'];
                while ($image['width'] > $tileSize || $image['height'] > $tileSize) {
                    $tierSizeInTiles[] = array(
                        ceil($image['width'] / $tileSize),
                        ceil($image['height'] / $tileSize),
                    );
                    $tileSize += $tileSize;
                }
                break;

            case 'truncated':
                $width = $image['width'];
                $height = $image['height'];
                while ($width > $tile['size'] || $height > $tile['size']) {
                    $tierSizeInTiles[] = array(
                        ceil($width / $tile['size']),
                        ceil($height / $tile['size']),
                    );
                    $width >>= 1;
                    $height >>= 1;
                }
                break;

            default:
                return;
        }

        $tierSizeInTiles[] = array(1, 1);
        $tierSizeInTiles = array_reverse($tierSizeInTiles);

        $resolutions = array(1);
        $tileCountUpToTier = array(0);
        for ($i = 1, $ii = count($tierSizeInTiles); $i < $ii; $i++) {
            $resolutions[] = 1 << $i;
            $tileCountUpToTier[] =
                $tierSizeInTiles[$i - 1][0] * $tierSizeInTiles[$i - 1][1]
                + $tileCountUpToTier[$i - 1];
        }

        $tileIndex = $tile['x']
            + $tile['y'] * $tierSizeInTiles[$tile['level']][0]
            + $tileCountUpToTier[$tile['level']];
        $tileGroup = ($tileIndex / $tile['size']) ?: 0;
        return (integer) $tileGroup;
    }

    /**
     * Helper to get width and height of an image.
     *
     * @see IiifServer\View\Helper\IiifInfo::getWidthAndHeight()
     *
     * @param string $filepath This should be an image (no check here).
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     */
    protected function getWidthAndHeight($filepath)
    {
        if (file_exists($filepath)) {
            list($width, $height, $type, $attr) = getimagesize($filepath);
            return [
                'width' => $width,
                'height' => $height,
            ];
        }

        return [
            'width' => null,
            'height' => null,
        ];
    }
}
