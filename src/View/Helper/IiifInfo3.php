<?php declare(strict_types=1);

/*
 * Copyright 2020-2023 Daniel Berthereau
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

use IiifServer\Iiif\ImageService3;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\TempFileFactory;

/**
 * Helper to get a IIIF info.json for a file.
 */
class IiifInfo3 extends AbstractHelper
{
    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * Full path to the files.
     *
     * @var string
     */
    protected $basePath;

    public function __construct(TempFileFactory $tempFileFactory, $basePath)
    {
        $this->tempFileFactory = $tempFileFactory;
        $this->basePath = $basePath;
    }

    /**
     * Get the IIIF info for the specified record.
     *
     * @link https://iiif.io/api/image/3.0
     *
     * @throws \IiifServer\Iiif\Exception\RuntimeException
     */
    public function __invoke(MediaRepresentation $media): ImageService3
    {
        $info = new ImageService3($media, ['version' => '3']);

        // Give possibility to customize the manifest.
        $resource = $media;
        $format = 'info';
        $type = 'image';
        $params = compact('format', 'info', 'resource', 'type');
        $this->view->plugin('trigger')->__invoke('iiifserver.manifest', $params, true);
        $info->isValid(true);
        return $info;
    }
}
