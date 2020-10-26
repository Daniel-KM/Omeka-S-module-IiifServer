<?php declare(strict_types=1);

/*
 * Copyright 2015-2020 Daniel Berthereau
 * Copyright 2016-2017 BibLibre
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

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

class IiifCanvas2 extends AbstractHelper
{
    /**
     * @var string
     */
    protected $_baseUrl;

    /**
     * Get the IIIF canvas for the specified resource.
     *
     * @todo Factorize with IiifManifest2.
     *
     * @param MediaRepresentation $resource
     * @param int $index Used to set the standard name of the image.
     * @return Object|null
     */
    public function __invoke(MediaRepresentation $resource, $index)
    {
        $view = $this->getView();

        // The base url for some other ids to quick process.
        $this->_baseUrl = $view->iiifUrl($resource, 'iiifserver/uri', '2', [
            'type' => 'annotation-page',
            'name' => '',
        ]);
        $this->_baseUrl = mb_substr($this->_baseUrl, 0, mb_strpos($this->_baseUrl, '/annotation-page'));

        $canvas = [];

        $titleFile = $resource->displayTitle();
        $canvasUrl = $this->_baseUrl . '/canvas/p' . $index;

        $canvas['@id'] = $canvasUrl;
        $canvas['@type'] = 'sc:Canvas';
        $canvas['label'] = $titleFile ?: '[' . $index . ']';

        // Thumbnail of the current file.
        $canvas['thumbnail'] = $this->_iiifThumbnail($resource);

        // Size of canvas should be the double of small images (< 1200 px), but
        // only when more than one image is used by a canvas.
        $imageSize = $view->imageSize($resource, 'original');
        list($width, $height) = $imageSize ? array_values($imageSize) : [null, null];
        $canvas['width'] = $width;
        $canvas['height'] = $height;

        $image = $this->_iiifImage($resource, $index, $canvasUrl, $width, $height);

        $images = [];
        $images[] = $image;
        $canvas['images'] = $images;

        $metadata = $this->iiifMetadata($resource);
        if ($metadata) {
            $canvas['metadata'] = $metadata;
        }

        return (object) $canvas;
    }

    /**
     * Prepare the metadata of a resource.
     *
     * @todo Factorize IiifCanvas2, IiifCollection2, TraitDescriptive and IiifManifest2.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function iiifMetadata(AbstractResourceEntityRepresentation $resource)
    {
        $jsonLdType = $resource->getResourceJsonLdType();
        $map = [
            'o:ItemSet' => [
                'whitelist' => 'iiifserver_manifest_properties_collection_whitelist',
                'blacklist' => 'iiifserver_manifest_properties_collection_blacklist',
            ],
            'o:Item' => [
                'whitelist' => 'iiifserver_manifest_properties_item_whitelist',
                'blacklist' => 'iiifserver_manifest_properties_item_blacklist',
            ],
            'o:Media' => [
                'whitelist' => 'iiifserver_manifest_properties_media_whitelist',
                'blacklist' => 'iiifserver_manifest_properties_media_blacklist',
            ],
        ];
        if (!isset($map[$jsonLdType])) {
            return [];
        }

        $settingHelper = $this->view->getHelperPluginManager()->get('setting');

        $whitelist = $settingHelper($map[$jsonLdType]['whitelist'], []);
        if ($whitelist === ['none']) {
            return [];
        }

        $values = $whitelist
            ? array_intersect_key($resource->values(), array_flip($whitelist))
            : $resource->values();

        $blacklist = $settingHelper($map[$jsonLdType]['blacklist'], []);
        if ($blacklist) {
            $values = array_diff_key($values, array_flip($blacklist));
        }
        if (empty($values)) {
            return [];
        }

        // TODO Remove automatically special properties, and only for values that are used (check complex conditions…).

        return $this->view->setting('iiifserver_manifest_html_descriptive')
            ? $this->valuesAsHtml($values)
            : $this->valuesAsPlainText($values);
    }

    /**
     * List values as plain text descriptive metadata.
     *
     * @param \Omeka\Api\Representation\ValueRepresentation[] $values
     * @return array
     */
    protected function valuesAsPlainText(array $values)
    {
        $metadata = [];
        $publicResourceUrl = $this->view->plugin('publicResourceUrl');
        foreach ($values as $propertyData) {
            $valueMetadata = [];
            $valueMetadata['label'] = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $valueValues = array_filter(array_map(function ($v) use ($publicResourceUrl) {
                return strpos($v->type(), 'resource') === 0 && $vr = $v->valueResource()
                    ? $publicResourceUrl($vr, true)
                    : (string) $v;
            }, $propertyData['values']), 'strlen');
            $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
            $metadata[] = (object) $valueMetadata;
        }
        return $metadata;
    }

    /**
     * List values as descriptive metadata, with links for resources and uris.
     *
     * @param \Omeka\Api\Representation\ValueRepresentation[] $values
     * @return array
     */
    protected function valuesAsHtml(array $values)
    {
        $metadata = [];
        $publicResourceUrl = $this->view->plugin('publicResourceUrl');
        foreach ($values as $propertyData) {
            $valueMetadata = [];
            $valueMetadata['label'] = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $valueValues = array_filter(array_map(function ($v) use ($publicResourceUrl) {
                if (strpos($v->type(), 'resource') === 0 && $vr = $v->valueResource()) {
                    return '<a class="resource-link" href="' . $publicResourceUrl($vr, true) . '">'
                        . '<span class="resource-name">' . $vr->displayTitle() . '</span>'
                        . '</a>';
                }
                return $v->asHtml();
            }, $propertyData['values']), 'strlen');
            $valueMetadata['value'] = count($valueValues) <= 1 ? reset($valueValues) : $valueValues;
            $metadata[] = (object) $valueMetadata;
        }
        return $metadata;
    }

    /**
     * Create an IIIF thumbnail object from an Omeka file.
     *
     * @param MediaRepresentation $media
     * @return \stdClass|null
     */
    protected function _iiifThumbnail(MediaRepresentation $media)
    {
        $view = $this->getView();

        $imageSize = $view->imageSize($media, 'square');
        if (empty($imageSize)) {
            return;
        }
        list($width, $height) = array_values($imageSize);

        $thumbnail = [];

        $imageUrl = $view->iiifImageUrl($media, 'imageserver/media', '2', [
            'region' => 'full',
            'size' => $width . ',' . $height,
            'rotation' => 0,
            'quality' => 'default',
            'format' => 'jpg',
        ]);
        $thumbnail['@id'] = $imageUrl;

        $thumbnailService = [];
        $thumbnailService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $thumbnailServiceUrl = $view->iiifImageUrl($media, 'imageserver/id', '2');
        $thumbnailService['@id'] = $thumbnailServiceUrl;
        $thumbnailService['profile'] = 'http://iiif.io/api/image/2/level2.json';
        $thumbnailService = (object) $thumbnailService;

        $thumbnail['service'] = $thumbnailService;
        $thumbnail = (object) $thumbnail;

        return $thumbnail;
    }

    /**
     * Create an IIIF image object from an Omeka file.
     *
     * @todo Use the IiifInfo (short version of info.json of the image).
     *
     * @param MediaRepresentation $media
     * @param int $index Used to set the standard name of the image.
     * @param string $canvasUrl Used to set the value for "on".
     * @param int $width If not set, will be calculated.
     * @param int $height If not set, will be calculated.
     * @return \stdClass|null
     */
    protected function _iiifImage(MediaRepresentation $media, $index, $canvasUrl, $width = null, $height = null)
    {
        $view = $this->getView();

        if (empty($width) || empty($height)) {
            $imageSize = $view->imageSize($media, 'original');
            list($width, $height) = $imageSize ? array_values($imageSize) : [null, null];
        }

        $image = [];
        $image['@id'] = $this->_baseUrl . '/annotation/p' . sprintf('%04d', $index) . '-image';
        $image['@type'] = 'oa:Annotation';
        $image['motivation'] = "sc:painting";

        // There is only one image (parallel is not managed currently).
        $imageResource = [];

        // According to https://iiif.io/api/presentation/2.1/#image-resources,
        // "the URL may be the complete URL to a particular size of the image
        // content", so the large one here, and it's always a jpeg.
        $imageSize = $view->imageSize($media, 'large');
        list($widthLarge, $heightLarge) = $imageSize ? array_values($imageSize) : [null, null];
        $imageUrl = $view->iiifImageUrl($media, 'imageserver/media', '2', [
            'region' => 'full',
            'size' => $widthLarge . ',' . $heightLarge,
            'rotation' => 0,
            'quality' => 'default',
            'format' => 'jpg',
        ]);

        $imageResource['@id'] = $imageUrl;
        $imageResource['@type'] = 'dctypes:Image';
        $imageResource['format'] = 'image/jpeg';
        $imageResource['width'] = $width;
        $imageResource['height'] = $height;

        $imageUrlService = $view->iiifImageUrl($media, 'imageserver/id', '2');
        $imageResourceService = [];
        $imageResourceService['@context'] = 'http://iiif.io/api/image/2/context.json';
        $imageResourceService['@id'] = $imageUrlService;
        $imageResourceService['profile'] = 'http://iiif.io/api/image/2/level2.json';

        // TODO Use the trait TileInfo of module ImageServer.
        $viewHelpers = $view->getHelperPluginManager();
        if ($viewHelpers->has('tileInfo')) {
            $tilingData = $view->tileInfo($media);
            $iiifTileInfo = $tilingData ? $this->iiifTileInfo($tilingData) : null;
            if ($iiifTileInfo) {
                $tiles = [];
                $tiles[] = $iiifTileInfo;
                $imageResourceService['tiles'] = $tiles;
                $imageResourceService['width'] = $width;
                $imageResourceService['height'] = $height;
            }
        }

        $imageResourceService = (object) $imageResourceService;
        $imageResource['service'] = $imageResourceService;
        $imageResource = (object) $imageResource;

        $image['resource'] = $imageResource;
        $image['on'] = $canvasUrl;

        return (object) $image;
    }

    /**
     * Create the data for a IIIF tile object.
     *
     * @param array $tileInfo
     * @return array|null
     */
    protected function iiifTileInfo($tileInfo)
    {
        $tile = [];

        $squaleFactors = [];
        $maxSize = max($tileInfo['source']['width'], $tileInfo['source']['height']);
        $tileSize = $tileInfo['size'];
        $total = (int) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $squaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($squaleFactors) <= 1) {
            return null;
        }

        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;
        return $tile;
    }
}
