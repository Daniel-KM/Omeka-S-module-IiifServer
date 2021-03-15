<?php declare(strict_types=1);

/*
 * Copyright 2020 Daniel Berthereau
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

namespace IiifServer\Iiif;

trait TraitIiifType
{
    /**
     * The rendering types are defined by the resource class and media type.
     *
     * Note: the resource class of the item is not used.
     *
     * @todo Remove this unused variable.
     * @todo Rendering type It's not clealy specified, except in the context. All dctype or not? Other than dctype? Default?
     *
     * Important: iiif use "Sound", not "Audio".
     *
     * @link https://iiif.io/api/image/3/context.json
     * @link https://iiif.io/api/presentation/3.0/#type
     */
    protected $renderingTypes = [
        'dctype:Dataset' => 'Dataset',
        'dctype:StillImage' => 'Image',
        'dctype:MovingImage' => 'Video',
        'dctype:Sound' => 'Sound',
        'dctype:Text' => 'Text',
        // TODO This is not specified in the context.
        'dctype:PhysicalObject' => 'Model',
    ];

    protected $mediaTypeTypes = [
        // 'application',
        'audio' => 'Sound',
        // 'example',
        // 'font',
        'image' => 'Image',
        // 'message',
        // 'model',
        // 'multipart',
        'text' => 'Text',
        'video' => 'Video',
    ];

    /**
     * Some common media-types.
     *
     * @var array
     */
    protected $mediaTypes = [
        // @see \Omeka\Form\SettingForm::MEDIA_TYPE_WHITELIST
        'application/msword' => 'Text',
        'application/ogg' => 'Video',
        'application/pdf' => 'Text',
        'application/rtf' => 'Text',
        'application/vnd.ms-access' => 'Dataset',
        'application/vnd.ms-excel' => 'Dataset',
        'application/vnd.ms-powerpoint' => 'Text',
        'application/vnd.ms-project' => 'Dataset',
        'application/vnd.ms-write' => 'Text',
        'application/vnd.oasis.opendocument.chart' => 'Image',
        'application/vnd.oasis.opendocument.database' => 'Dataset',
        'application/vnd.oasis.opendocument.formula' => 'Text',
        'application/vnd.oasis.opendocument.graphics' => 'Image',
        'application/vnd.oasis.opendocument.presentation' => 'Text',
        'application/vnd.oasis.opendocument.spreadsheet' => 'Dataset',
        'application/vnd.oasis.opendocument.text' => 'Text',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Text',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'Text',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Dataset',
        'application/x-gzip' => null,
        'application/x-ms-wmp' => null,
        'application/x-msdownload' => null,
        'application/x-shockwave-flash' => null,
        'application/x-tar' => null,
        'application/zip' => null,
        'application/xml' => 'Text',
        // @see \Next\File\TempFile::xmlMediaTypes
        'application/vnd.recordare.musicxml' => 'Text',
        'application/vnd.mei+xml' => 'Text',
        'application/vnd.pdf2xml+xml' => 'Text',
    ];

    /**
     * Some labels for common formats.
     *
     * @var array
     */
    protected $mediaLabels = [
        // @see \Omeka\Form\SettingForm::MEDIA_TYPE_WHITELIST
        'application/msword' => 'Document Word',
        'application/ogg' => 'Sound OGG',
        'application/pdf' => 'Document PDF',
        'application/rtf' => 'Document RTF',
        'application/vnd.ms-access' => 'Database Access',
        'application/vnd.ms-excel' => 'Spreadsheet Excel',
        'application/vnd.ms-powerpoint' => 'Presentation Powerpoint',
        'application/vnd.ms-project' => 'Microsoft Project',
        'application/vnd.ms-write' => 'Document Write',
        'application/vnd.oasis.opendocument.chart' => 'Chart OpenDocument',
        'application/vnd.oasis.opendocument.database' => 'Database OpenDocument',
        'application/vnd.oasis.opendocument.formula' => 'Formula OpenDocument',
        'application/vnd.oasis.opendocument.graphics' => 'Graphics OpenDocument',
        'application/vnd.oasis.opendocument.presentation' => 'Presentation OpenDocument',
        'application/vnd.oasis.opendocument.spreadsheet' => 'Spreadsheet OpenDocument',
        'application/vnd.oasis.opendocument.text' => 'Document OpenDocument',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Document Word',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'Presentation Powerpoint',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Spreadsheet Excel',
        'application/x-gzip' => 'Achive Zip',
        'application/x-ms-wmp' => 'Video Windows',
        'application/x-msdownload' => 'File Windows',
        'application/x-shockwave-flash' => 'Flash',
        'application/x-tar' => 'Archive Tar',
        'application/zip' => 'Archive Zip',
        'application/xml' => 'XML',
        // @see \Next\File\TempFile::xmlMediaTypes
        'application/vnd.recordare.musicxml' => 'MusicXML',
        'application/vnd.mei+xml' => 'Music MEI',
        'application/vnd.pdf2xml+xml' => 'Document PDF',
    ];

    protected $rendererTypes = [
        'file' => null,
        'oembed' => 'Text',
        'youtube' => 'Video',
        'html' => 'Text',
        'iiif' => 'Image',
        'tile' => 'Image',
    ];

    protected function initIiifType()
    {
        if ($this->resource->ingester() == 'iiif') {
            $mediaData = $this->resource->mediaData();
            if (isset($mediaData['type'])) {
                $this->type = $mediaData['type'];
                return $this->type;
            }
            if (isset($mediaData['@type'])) {
                $this->type = $mediaData['@type'];
                return $this->type;
            }
            if (isset($mediaData['format'])) {
                $mediaType = $mediaData['format'];
            }
        }

        if (empty($mediaType)) {
            $mediaType = $this->resource->mediaType();
        }

        if ($mediaType) {
            $mediaTypeType = strtok($mediaType, '/');
            if (isset($this->mediaTypeTypes[$mediaTypeType])) {
                $this->type = $this->mediaTypeTypes[$mediaTypeType];
                return $this->type;
            }
        }

        // Managed some other common media types.
        if (isset($this->mediaTypes[$mediaType])) {
            $this->type = $this->mediaTypes[$mediaType];
            return $this->type;
        }

        $renderer = $this->resource->renderer();
        if (isset($this->rendererTypes[$renderer])) {
            $this->type = $this->rendererTypes[$renderer];
            return $this->type;
        }

        /* These cases are normally managed by the media type above.
        // $extension = $this->resource->extension();
        if ($renderer === 'file') {
            // See \Omeka\Media\Renderer::render()
            // $fileRenderers = $this->resource->getServiceLocator()->get('Config')['file_renderers'] + ['factories' => []];
            /** @var \Omeka\Media\FileRenderer\Manager $fileRendererManager
            // $fileRendererManager = $this->resource->getServiceLocator()->get('Omeka\Media\FileRenderer\Manager');
        }
        */

        return null;
    }
}
