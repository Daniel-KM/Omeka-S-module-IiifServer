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
     * @todo Store dimensions in the data of the media. Or use numeric properties (with units).
     *
     * @param \Omeka\Api\Representation\MediaRepresentation|string $media Can be an
     * media, an url or a filepath.
     * @throws RuntimeException
     * @return array|null Associative array of width, height, and/or duration of
     * the media, else null.
     */
    public function __invoke($media)
    {
        try {
            return $this->mediaDimensionPlugin->__invoke($media);
        } catch (RuntimeException $e) {
            throw new Exception\RuntimeException($e->getMessage());
        }
    }
}
