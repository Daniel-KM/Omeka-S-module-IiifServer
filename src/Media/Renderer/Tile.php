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

    public function __construct(FileManager $fileManager, $tileDir)
    {
        $this->fileManager = $fileManager;
        $this->tileDir = $tileDir;
    }

    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $basepath = $this->tileDir . DIRECTORY_SEPARATOR;
        $checkpath = $basepath . $media->storageId() . '.js';
        if (file_exists($checkpath)) {
            $data = $this->getDataJsonp($checkpath);
        } else {
            $checkpath = $basepath . $media->storageId() . '.dzi';
            if (file_exists($checkpath)) {
                $data = $this->getDataDzi($checkpath, $format);
            } else {
                $checkpath = $basepath . $media->storageId() . self::FOLDER_EXTENSION_ZOOMIFY
                    . DIRECTORY_SEPARATOR . 'ImageProperties.xml';
                if (file_exists($checkpath)) {
                    $data = $this->getDataZoomify($checkpath);
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

        $view->headScript()->appendFile($view->assetUrl('js/openseadragon/openseadragon.min.js', 'Omeka'));

        $prefixUrl = $view->assetUrl('js/openseadragon/images/', 'Omeka');
        $url = $view->url(
            'iiifserver_image',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );

        $tileSource = [];
        $tileSource['@context'] = 'http://iiif.io/api/image/2/context.json';
        $tileSource['@id'] = $url;
        $tileSource['protocol'] = 'http://iiif.io/api/image';
        $tileSource['profile'] = 'http://iiif.io/api/image/2/level2.json';
        $tileSource += $data;

        $args = [];
        $args['id'] = 'iiif-' . $media->id();
        $args['prefixUrl'] = $prefixUrl;
        $args['tileSources'] = [$tileSource];

        $image =
        '<div class="openseadragon" id="iiif-' . $media->id() . '"></div>
            <script type="text/javascript">
                var viewer = OpenSeadragon(' . json_encode($args) . ');
            </script>'
        ;
        return $image;
    }

    /**
     * Get rendering data from a jsonp format.
     *
     * @param string path
     * @return array|null
     */
    protected function getDataJsonp($path)
    {
    }

    /**
     * Get rendering data from a dzi format.
     *
     * @param string path
     * @return array|null
     */
    protected function getDataDzi($path)
    {
    }

    /**
     * Get rendering data from a zoomify format.
     *
     * @param string $path
     * @return array|null
     */
    protected function getDataZoomify($path)
    {
        $tileProperties = $this->getTileProperties($path);
        if (empty($tileProperties)) {
            return;
        }

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

        $tile = [];
        $tile['width'] = $tileSize;
        $tile['scaleFactors'] = $squaleFactors;

        $data = [];
        $data['width'] = $tileProperties['source']['width'];
        $data['height'] = $tileProperties['source']['height'];
        $data['tiles'][] = $tile;
        return $data;
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
}
