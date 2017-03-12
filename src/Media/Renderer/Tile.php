<?php
namespace IiifServer\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Omeka\Stdlib\Message;
use Zend\View\Renderer\PhpRenderer;
use IiifServer\Mvc\Controller\Plugin\TileInfo;

class Tile implements RendererInterface
{

    /**
     * @var string
     */
    protected $tileDir;

    public function __construct($tileDir)
    {
        $this->tileDir = $tileDir;
    }

    /**
     * Render a tiled image.
     *
     * @param PhpRenderer $view,
     * @param MediaRepresentation $media
     * @param array $options These options are managed:
     *   - mode: set the rendering mode: "native" to the tiles type (default),
     *   "iiif" or "iiif-full". Native modes are quicker, but don’t benefit
     *   of the features and of the interoperability of the IIIF.
     *   - data: data are set from the tiles of the specified media, but they
     *   can be completed or overridden. See the OpenSeadragon docs (example:
     *   "debugMode" => true).
     * @return string
     */
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $tileInfo = new TileInfo();
        $tileInfo = $tileInfo($media);
        if (empty($tileInfo)) {
            return new Message('No tile or no properties for media #%d.', // @translate
                $media->id());
        }

        $mode = !empty($options['mode']) ? $options['mode'] : 'native';
        $prefixId = 'osd';
        switch ($mode) {
            case 'iiif':
                $prefixId = 'iiif';
                $data = $this->getDataIiif($media, $view, $tileInfo);
                break;
            case 'iiif-full':
                $prefixId = 'iiif';
                $data = $this->getDataIiifFull($media, $view, $tileInfo);
                break;
            case 'native':
                switch ($tileInfo['tile_type']) {
                    case 'deepzoom':
                        $data = $this->getDataDeepzoom($media, $view, $tileInfo);
                        break;
                    case 'zoomify':
                        $data = $this->getDataZoomify($media, $view, $tileInfo);
                        break;
                    case 'iiif':
                        $prefixId = 'iiif';
                        $data = $this->getDataIiif($media, $view, $tileInfo);
                        break;
                    case 'iiif-full':
                        $prefixId = 'iiif';
                        $data = $this->getDataIiifFull($media, $view, $tileInfo);
                        break;
                }
                break;
        }

        if (empty($data)) {
            return new Message('Invalid data for media #%d.', // @translate
                $media->id());
        }

        $data['prefixUrl'] = $view->assetUrl('js/openseadragon/images/', 'Omeka');

        if (isset($options['data'])) {
            $data = array_merge($data, $options['data']);
        }

        $args = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $view->headScript()->appendFile($view->assetUrl('js/openseadragon/openseadragon.min.js', 'Omeka'));

        $noscript = 'OpenSeadragon is not available unless JavaScript is enabled.'; // @translate
        $image = <<<OUTPUT
<div class="openseadragon" id="{$prefixId}-{$media->id()}" style="height: 800px;"></div>
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
     * @param array $tileInfo
     * @return array
     */
    protected function getDataDeepzoom(MediaRepresentation $media, PhpRenderer $view, array $tileInfo)
    {
        $url = $view->serverUrl()
            . $view->basePath('files' . '/' . $this->tileDir . '/' . $tileInfo['relativepath']);
        $args = [];
        $args['id'] = 'osd-' . $media->id();
        $args['prefixUrl'] = '';
        $args['tileSources'] = [$url];
        return $args;
    }

    /**
     * Get rendering data from a zoomify format.
     *
     * @param MediaRepresentation $media
     * @param PhpRenderer $view
     * @param array $tileInfo
     * @return array
     */
    protected function getDataZoomify(MediaRepresentation $media, PhpRenderer $view, array $tileInfo)
    {
        // NOTE OpenSeadragon doesn't support short syntax (url only) currently.
        $url = $view->serverUrl()
            . $view->basePath('files' . '/' . $this->tileDir . '/' . dirname($tileInfo['relativepath'])) . '/';
        $tileSource = [];
        $tileSource['type'] = 'zoomifytileservice';
        $tileSource['width'] = $tileInfo['source']['width'];
        $tileSource['height'] = $tileInfo['source']['height'];
        $tileSource['tilesUrl'] = $url;
        $args = [];
        $args['id'] = 'osd-' . $media->id();
        $args['prefixUrl'] = '';
        $args['tileSources'] = [$tileSource];
        return $args;
    }

    /**
     * Get rendering data for IIIF.
     *
     * @param MediaRepresentation $media
     * @param PhpRenderer $view
     * @param array $tileInfo
     * @return array|null
     */
    protected function getDataIiif(MediaRepresentation $media, PhpRenderer $view, array $tileInfo)
    {
        $url = $view->url(
            'iiifserver_image_info',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );
        $args = [];
        $args['id'] = 'iiif-' . $media->id();
        $args['prefixUrl'] = '';
        $args['tileSources'] = $url;
        return $args;

    }

    /**
     * Get rendering data for IIIF (full data).
     *
     * @param MediaRepresentation $media
     * @param PhpRenderer $view
     * @param array $tileInfo
     * @return array|null
     */
    protected function getDataIiifFull(MediaRepresentation $media, PhpRenderer $view, array $tileInfo)
    {
        $squaleFactors = $this->getSqualeFactors(
            $tileInfo['source']['width'],
            $tileInfo['source']['height'],
            $tileInfo['size']
        );
        if (empty($squaleFactors)) {
            return;
        }

        $url = $view->url(
            'iiifserver_image',
            ['id' => $media->id()],
            ['force_canonical' => true]
        );

        $tile = [];
        $tile['width'] = $tileInfo['size'];
        $tile['scaleFactors'] = $squaleFactors;

        $data = [];
        $data['width'] = $tileInfo['source']['width'];
        $data['height'] = $tileInfo['source']['height'];
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
     * Get the squale factors of an tiled image for iiif.
     *
     * @param integer $width
     * @param integer $height
     * @param integer $tileSize
     * @return array|null
     */
    protected function getSqualeFactors($width, $height, $tileSize)
    {
        $squaleFactors = [];
        $maxSize = max($width, $height);
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
