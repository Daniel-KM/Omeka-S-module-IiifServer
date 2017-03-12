<?php
namespace IiifServer\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\Manager as FileManager;
use Omeka\Media\Renderer\RendererInterface;
use Omeka\Stdlib\Message;
use Zend\View\Renderer\PhpRenderer;

class Tile implements RendererInterface
{

    /**
     * Extension added to a folder name to store data and tiles for Zoomify.
     *
     * @var string
     */
    const FOLDER_EXTENSION_ZOOMIFY = '_zdata';

    /**
     * @var FileManager
     */
    protected $fileManager;

    /**
     * @var string
     */
    protected $tileDir;

    /**
     * @var string
     */
    protected $fullTileDir;

    public function __construct(FileManager $fileManager, $tileDir)
    {
        $this->fileManager = $fileManager;
        $this->tileDir = $tileDir;
    }

    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $this->fullTileDir = OMEKA_PATH
            . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . $this->tileDir;

        $basepath = $this->fullTileDir . DIRECTORY_SEPARATOR;

        $relativePath = $media->storageId() . '.dzi';
        if (file_exists($basepath . $relativePath)) {
            $data = $this->getDataDeepzoom($media, $view, $relativePath);
        } else {
            $relativePath = $media->storageId() . '.js';
            if (file_exists($basepath . $relativePath)) {
                $data = $this->getDataDeepzoom($media, $view, $relativePath);
            } else {
                $relativePath = $media->storageId() . self::FOLDER_EXTENSION_ZOOMIFY
                    . DIRECTORY_SEPARATOR . 'ImageProperties.xml';
                if (file_exists($basepath . $relativePath)) {
                    $data = $this->getDataZoomify($media, $view, $relativePath);
                } else {
                    return new Message('No tile or no properties for media #%d.', // @translate
                        $media->id());
                }
            }
        }

        if (empty($data)) {
            return new Message('Invalid data for media #%d.', // @translate
                $media->id());
        }

        $data['prefixUrl'] = $view->assetUrl('js/openseadragon/images/', 'Omeka');
        $args = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $view->headScript()->appendFile($view->assetUrl('js/openseadragon/openseadragon.min.js', 'Omeka'));

        $noscript = 'OpenSeadragon is not available unless JavaScript is enabled.'; // @translate
        $image = <<<OUTPUT
<div class="openseadragon" id="iiif-{$media->id()}" style="height: 800px;"></div>
<script type="text/javascript">
    var viewer = OpenSeadragon({$args});
</script>
<noscript>
    <p>{$noscript}</p>
    <img src="{$media->thumbnailUrl('large')}" height="800px" />
</noscript>
OUTPUT;
        return $image;
    }

    /**
     * Get rendering data from a dzi format.
     *
     * @param MediaRepresentation $media
     * @param PhpRenderer $view
     * @param string $relativePath
     * @return array|null
     */
    protected function getDataDeepzoom(MediaRepresentation $media, PhpRenderer $view, $relativePath)
    {
        $url = $view->serverUrl() . $view->basePath('files' . '/' . $this->tileDir . '/' . $relativePath);
        $args = [];
        $args['id'] = 'iiif-' . $media->id();
        $args['prefixUrl'] = '';
        $args['tileSources'] = [$url];
        return $args;
    }

    /**
     * Get rendering data from a zoomify format.
     *
     * @param MediaRepresentation $media
     * @param PhpRenderer $view
     * @param string $relativePath
     * @return array|null
     */
    protected function getDataZoomify(MediaRepresentation $media, PhpRenderer $view, $relativePath)
    {
        $path = $this->fullTileDir . DIRECTORY_SEPARATOR . $relativePath;
        $tileProperties = $this->getTileProperties($path);
        if (empty($tileProperties)) {
            return;
        }

        $squaleFactors = $this->getSqualeFactors($tileProperties);
        if (empty($squaleFactors)) {
            return;
        }

        $url = $view->url(
            'iiifserver_image',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );

        $tile = [];
        $tile['width'] = $tileProperties['size'];
        $tile['scaleFactors'] = $squaleFactors;

        $data = [];
        $data['width'] = $tileProperties['source']['width'];
        $data['height'] = $tileProperties['source']['height'];
        $data['tiles'][] = $tile;

        $tileSource = [];
        $tileSource['@context'] = 'http://iiif.io/api/image/2/context.json';
        $tileSource['@id'] = $url;
        $tileSource['protocol'] = 'http://iiif.io/api/image';
        $tileSource['profile'] = 'http://iiif.io/api/image/2/level2.json';
        $tileSource += $data;

        $args = [];
        $args['id'] = 'iiif-' . $media->id();
        $args['prefixUrl'] = '';
        $args['tileSources'] = [$tileSource];

        return $args;
    }

    /**
     * Return the properties from a Zoomify tile file.
     *
     * @see IiifServer\View\Helper\IiifManifest::getTileProperties()
     *
     * @param string $xmlpath
     * @return array|null
     */
    protected function getTileProperties($xmlpath)
    {
        $properties = simplexml_load_file($xmlpath, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_PARSEHUGE);
        if ($properties === false) {
            return;
        }
        $properties = $properties->attributes();
        $properties = reset($properties);

        // Normalize the properties.
        $result = [];
        $result['size'] = (integer) $properties['TILESIZE'];
        $result['total'] = (integer) $properties['NUMTILES'];
        $result['source']['width'] = (integer) $properties['WIDTH'];
        $result['source']['height'] = (integer) $properties['HEIGHT'];
        return $result;
    }

    /**
     * Get the squale factors for a Zoomify tile file.
     *
     * @param array $tileProperties
     * @return array|null
     */
    protected function getSqualeFactors($tileProperties)
    {
        $squaleFactors = [];
        $maxSize = max($tileProperties['source']['width'], $tileProperties['source']['height']);
        $tileSize = $tileProperties['size'];
        $total = (integer) ceil($maxSize / $tileSize);
        $factor = 1;
        while ($factor / 2 <= $total) {
            $squaleFactors[] = $factor;
            $factor = $factor * 2;
        }
        if (count($squaleFactors) <= 1) {
            return;
        }
        return $squaleFactors;
    }
}
