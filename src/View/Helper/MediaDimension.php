<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use IiifServer\Mvc\Controller\Plugin\MediaDimension as MediaDimensionPlugin;
use Laminas\View\Exception;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Mvc\Exception\RuntimeException;

class MediaDimension extends AbstractHelper
{
    /**
     * @var MediaDimensionPlugin
     */
    protected $mediaDimensionPlugin;

    /**
     * @param MediaDimensionPlugin $mediaDimensionPlugin
     */
    public function __construct(MediaDimensionPlugin $mediaDimensionPlugin)
    {
        $this->mediaDimensionPlugin = $mediaDimensionPlugin;
    }

    /**
     * Get an array of the width, height, and/or duration of a media or file.
     *
     * Unlike the controller plugin, the possibility to force extraction of
     * dimensions in disabled, because it is useless in a view.
     *
     * @param MediaRepresentation|Media|string $media Can be a media, an url or a filepath.
     * @param string $type "original", "large", "medium", "square", or any other
     * subdirectory of the directory "files".
     * @return array Associative array of width, height, and/or duration of the
     * media. A dimension may be null. All dimensions are null for media that
     * are not an image, an audio or a video.
     * @throws RuntimeException
     */
    public function __invoke($media, string $type = 'original'): array
    {
        try {
            return $this->mediaDimensionPlugin->__invoke($media);
        } catch (RuntimeException $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
    }
}
