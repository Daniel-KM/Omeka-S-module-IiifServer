<?php declare(strict_types=1);

/*
 * Copyright 2015-2023 Daniel Berthereau
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

namespace IiifServer\View\Helper;

use ImageServer\Mvc\Controller\Plugin\TileInfo;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\MediaRepresentation;

class IiifTileInfo extends AbstractHelper
{
    /**
     * @var \ImageServer\Mvc\Controller\Plugin\TileInfo|null
     */
    protected $tileInfo;

    public function __construct(?TileInfo $tileInfo)
    {
        $this->tileInfo = $tileInfo;
    }

    /**
     * Retrieve iiif tiling data about the tiling of an image.
     *
     * Use media data (managed by module ImageServer) or tile info (plugin from
     * module ImageServer).
     *
     * Unlike tileMediaInfo, a check is done against the file when data are
     * missing in case of old media are not yet prepared.
     *
     * @uses \ImageServer\Mvc\Controller\Plugin\TileInfo
     * @see \ImageServer\Mvc\Controller\Plugin\TileMediaInfo
     */
    public function __invoke(MediaRepresentation $media, ?string $format = null): ?array
    {
        // Process like tileMediaInfo, but the data are retrieved when missing.
        $tileData = $media->mediaData();
        if (empty($tileData['tile'])) {
            if (empty($this->tileInfo)) {
                return null;
            }
            $tilingData = $this->tileInfo->__invoke($media, $format);
        } else {
            $tilingData = $format
                ? $tileData['tile'][$format] ?? null
                : reset($tileData['tile']);
        }

        return $tilingData
            ? $this->iiifTileInfo($tilingData)
            : null;
    }

    /**
     * Create the data for a IIIF tile object.
     */
    protected function iiifTileInfo(array $tileInfo): ?array
    {
        $tile = [];

        $scaleFactors = [];
        $maxSize = max($tileInfo['source']['width'], $tileInfo['source']['height']);
        $tileSize = $tileInfo['size'];
        $total = (int) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $scaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($scaleFactors) <= 1) {
            return null;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $scaleFactors;
        return $tile;
    }
}
