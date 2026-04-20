<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\MediaRepresentation;

class IsIiifMedia extends AbstractPlugin
{
    /**
     * @var array<string, string[]>
     */
    protected array $mediaIngesters;

    public function __construct(array $mediaIngesters)
    {
        $this->mediaIngesters = $mediaIngesters;
    }

    /**
     * Check if a media uses an ingester registered as IIIF.
     *
     * The list of IIIF ingesters is declared by modules via the merged config
     * key `iiifserver.media_ingesters`, grouped by type (image, audio, video,
     * presentation). A null $type matches any registered IIIF ingester,
     * whatever its type.
     *
     * Example config contributed by module IiifRemoteImage:
     *   'iiifserver' => ['media_ingesters' => ['image' => ['iiif-remote-image']]]
     */
    public function __invoke(MediaRepresentation $media, ?string $type = null): bool
    {
        $ingester = $media->ingester();
        if ($type === null) {
            foreach ($this->mediaIngesters as $list) {
                if (in_array($ingester, $list, true)) {
                    return true;
                }
            }
            return false;
        }
        return in_array($ingester, $this->mediaIngesters[$type] ?? [], true);
    }
}
