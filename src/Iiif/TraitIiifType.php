<?php declare(strict_types=1);

/*
 * Copyright 2020-2024 Daniel Berthereau
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
     * Warning: Iiif uses "Sound", not "Audio".
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
        'dctype:Model' => 'Model',
    ];

    protected $mainMediaTypes = [
        // 'application',
        'audio' => 'Sound',
        // 'example',
        // 'font',
        'image' => 'Image',
        // 'message',
        'model' => 'Model',
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
        'application/vnd.oasis.opendocument.presentation-flat-xml' => 'Text',
        'application/vnd.oasis.opendocument.spreadsheet' => 'Dataset',
        'application/vnd.oasis.opendocument.spreadsheet-flat-xml' => 'Dataset',
        'application/vnd.oasis.opendocument.text' => 'Text',
        'application/vnd.oasis.opendocument.text-flat-xml' => 'Text',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Text',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'Text',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Dataset',

        'application/xhtml+xml' => 'Text',
        'application/xml' => 'Dataset',
        'text/html' => 'Text',
        'text/xml' => 'Dataset',

        'application/x-gzip' => null,
        'application/x-ms-wmp' => null,
        'application/x-msdownload' => null,
        'application/x-shockwave-flash' => null,
        'application/x-tar' => null,
        'application/zip' => null,

        'image/svg+xml' => 'Image',

        // Common in library and culture world.
        'application/vnd.alto+xml' => 'Dataset', // Deprecated in 2017.
        'application/alto+xml' => 'Dataset',
        'application/vnd.bnf.refnum+xml' => 'Dataset',
        'application/vnd.iccu.mag+xml' => 'Dataset',
        'application/vnd.marc21+xml' => 'Dataset', // Deprecated in 2011.
        'application/marcxml+xml' => 'Dataset',
        'application/vnd.mets+xml' => 'Dataset', // Deprecated in 2011.
        'application/mets+xml' => 'Dataset',
        'application/vnd.mods+xml' => 'Dataset', // Deprecated in 2011.
        'application/mods+xml' => 'Dataset',
        'application/vnd.mei+xml' => 'Dataset',
        'application/vnd.recordare.musicxml' => 'Dataset',
        'application/vnd.recordare.musicxml+xml' => 'Dataset',
        'application/vnd.openarchives.oai-pmh+xml' => 'Dataset',
        'application/vnd.tei+xml' => 'Dataset', // Deprecated in 2011.
        'application/tei+xml' => 'Dataset',

        'application/vnd.threejs+json' => 'Model',
        'model/vnd.collada+xml' => 'Model',

        'application/atom+xml' => 'Dataset',
        'application/rss+xml' => 'Dataset',

        // Used in IiifSearch.
        'application/vnd.pdf2xml+xml' => 'Dataset',

        // Omeka should support itself.
        'text/vnd.omeka+xml' => 'Dataset',
    ];

    /**
     * Some labels for common formats.
     *
     * @var array
     */
    protected $mediaLabels = [
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
        'application/vnd.oasis.opendocument.presentation-flat-xml' => 'Presentation OpenDocument',
        'application/vnd.oasis.opendocument.spreadsheet' => 'Spreadsheet OpenDocument',
        'application/vnd.oasis.opendocument.spreadsheet-flat-xml' => 'Spreadsheet OpenDocument',
        'application/vnd.oasis.opendocument.text' => 'Document OpenDocument',
        'application/vnd.oasis.opendocument.text-flat-xml' => 'Document OpenDocument',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Document Word',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'Presentation Powerpoint',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Spreadsheet Excel',

        'application/xhtml+xml' => 'Web page',
        'application/xml' => 'XML',
        'text/html' => 'Web page',
        'text/xml' => 'XML',

        'application/x-gzip' => 'Achive Zip',
        'application/x-ms-wmp' => 'Video Windows',
        'application/x-msdownload' => 'File Windows',
        'application/x-shockwave-flash' => 'Flash',
        'application/x-tar' => 'Archive Tar',
        'application/zip' => 'Archive Zip',

        'image/svg+xml' => 'Image SVG',

        'application/vnd.alto+xml' => 'ALTO XML', // Deprecated in 2017.
        'application/alto+xml' => 'ALTO XML',
        'application/vnd.bnf.refnum+xml' => 'BnF RefNum XML',
        'application/vnd.iccu.mag+xml' => 'MAG XML',
        'application/vnd.marc21+xml' => 'MARC21', // Deprecated in 2011.
        'application/marcxml+xml' => 'MARC XML',
        'application/vnd.mets+xml' => 'METS XML', // Deprecated in 2011.
        'application/mets+xml' => 'METS XML',
        'application/vnd.mods+xml' => 'MODS XML', // Deprecated in 2011.
        'application/mods+xml' => 'MODS XML',
        'application/vnd.mei+xml' => 'Music MEI',
        'application/vnd.recordare.musicxml' => 'MusicXML',
        'application/vnd.recordare.musicxml+xml' => 'MusicXML',
        'application/vnd.openarchives.oai-pmh+xml' => 'OAI-PMH',
        'application/vnd.tei+xml' => 'TEI XML', // Deprecated in 2011.
        'application/tei+xml' => 'TEI XML',

        'application/vnd.threejs+json' => 'Model ThreeJS',
        'model/vnd.collada+xml' => 'Model Collada',

        'application/atom+xml' => 'Atom feed',
        'application/rss+xml' => 'RSS feed',

        'application/vnd.pdf2xml+xml' => 'Document PDF/XML',

        'text/vnd.omeka+xml' => 'Omeka Resource',
    ];

    protected $rendererTypes = [
        'file' => null,
        'oembed' => 'Text',
        'youtube' => 'Video',
        'html' => 'Text',
        'iiif' => 'Image',
        'tile' => 'Image',
    ];

    protected $iiifImageServiceTypes = [
        'ImageService1',
        'ImageService2',
        'ImageService3',
    ];

    /**
     * Contains protocols and profiles.
     *
     * @var array
     */
    protected $iiifProfileToTypes = [
        'http://iiif.io/api/image' => 'Image',
        // The context does not exsit for 1, but improve compatibility.
        'http://iiif.io/api/image/1/context.json' => 'Image',
        'http://iiif.io/api/image/2/context.json' => 'Image',
        'http://iiif.io/api/image/3/context.json' => 'Image',
    ];

    protected function initIiifType(): self
    {
        $this->type = null;

        if ($this->resource->ingester() === 'iiif') {
            $mediaData = $this->resource->mediaData();
            if (isset($mediaData['type'])) {
                if (in_array($mediaData['type'], $this->iiifImageServiceTypes)) {
                    $this->type = 'Image';
                    return $this;
                }
                $this->type = $mediaData['type'];
                return $this;
            }
            if (isset($mediaData['@type'])) {
                $this->type = $mediaData['@type'];
                if (in_array($mediaData['@type'], $this->iiifImageServiceTypes)) {
                    $this->type = 'Image';
                    return $this;
                }
                $this->type = $mediaData['@type'];
                return $this;
            }
            if (isset($mediaData['protocol']) && isset($this->iiifProfileToTypes[$mediaData['protocol']])) {
                $this->type = $this->iiifProfileToTypes[$mediaData['protocol']];
                return $this;
            }
            if (isset($mediaData['@context'])) {
                if (is_array($mediaData['@context'])) {
                    $intersect = array_intersect_key($this->iiifProfileToTypes, array_flip($mediaData['@context']));
                    if ($intersect) {
                        $this->type = reset($intersect);
                        return $this;
                    }
                } elseif (isset($this->iiifProfileToTypes[$mediaData['@context']])) {
                    $this->type = $this->iiifProfileToTypes[$mediaData['@context']];
                    return $this;
                }
            }
            if (isset($mediaData['format'])) {
                $mediaType = $mediaData['format'];
            }
        }

        if (empty($mediaType)) {
            $mediaType = $this->resource->mediaType();
            if (empty($mediaType)) {
                return $this;
            }
        }

        // Managed some common media types.
        if (isset($this->mediaTypes[$mediaType])) {
            $this->type = $this->mediaTypes[$mediaType];
            return $this;
        }

        // TODO Improve detection of type "Model".
        if ($mediaType === 'text/plain' || $mediaType === 'application/json') {
            $extension = strtolower(pathinfo((string) $this->resource->source(), PATHINFO_EXTENSION));
            // TODO Convert old "text/plain" into "application/json" or "model/gltf+json".
            if ($extension === 'json' || $extension === 'gltf') {
                $this->type = 'Model';
                return $this;
            }
        }

        if ($mediaType === 'application/octet-stream') {
            $extension = strtolower(pathinfo((string) $this->resource->source(), PATHINFO_EXTENSION));
            if ($extension === 'glb') {
                $this->type = 'Model';
                return $this;
            }
        }

        $mainMediaType = strtok($mediaType, '/');
        if (isset($this->mainMediaTypes[$mainMediaType])) {
            $this->type = $this->mainMediaTypes[$mainMediaType];
            return $this;
        }

        $renderer = $this->resource->renderer();
        if (isset($this->rendererTypes[$renderer])) {
            $this->type = $this->rendererTypes[$renderer];
            return $this;
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

        return $this;
    }
}
