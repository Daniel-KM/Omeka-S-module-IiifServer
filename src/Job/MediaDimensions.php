<?php declare(strict_types=1);

namespace IiifServer\Job;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class MediaDimensions extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 25;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Logger
     */
    protected $logger;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\MediaDimension
     */
    protected $mediaDimension;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $mediaRepository;

    /**
     * @var string
     */
    protected $filter;

    /**
     * @var array
     */
    protected $imageTypes;

    /**
     * @var int
     */
    protected $totalSucceed;

    /**
     * @var int
     */
    protected $totalFailed;

    /**
     * @var int
     */
    protected $totalSkipped;

    /**
     * @var int
     */
    protected $totalMedias;

    /**
     * @var int
     */
    protected $totalProcessed;

    /**
     * @var int
     */
    protected $totalToProcess;

    public function perform(): void
    {
        /** @var \Omeka\Api\Manager $api */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $query = $this->getArg('query', []) ?? [];
        if (is_string($query)) {
            $sQuery = [];
            parse_str($query, $sQuery);
            $query = $sQuery ?: [];
        }

        $response = $api->search('items', $query);
        $this->totalToProcess = $response->getTotalResults();
        if (empty($this->totalToProcess)) {
            $this->logger->warn(new Message(
                'No item selected. You may check your query.' // @translate
            ));
            return;
        }

        $this->prepareSizer();

        $this->logger->info(new Message(
            'Starting bulk sizing for %1$d items (%2$s media).', // @translate
            $this->totalToProcess, $this->filter
        ));

        $offset = 0;
        $this->totalMedias = 0;
        $this->totalProcessed = 0;
        $this->totalSucceed = 0;
        $this->totalFailed = 0;
        $this->totalSkipped = 0;
        while (true) {
            /** @var \Omeka\Api\Representation\ItemRepresentation[] $items */
            $items = $api
                ->search('items', ['limit' => self::SQL_LIMIT, 'offset' => $offset] + $query)
                ->getContent();
            if (empty($items)) {
                break;
            }

            foreach ($items as $key => $item) {
                if ($this->shouldStop()) {
                    $this->logger->warn(new Message(
                        'The job "Media Dimensions" was stopped: %1$d/%2$d resources processed.', // @translate
                        $offset + $key, $this->totalToProcess
                    ));
                    break 2;
                }

                /** @var \Omeka\Api\Representation\MediaRepresentation $media */
                foreach ($item->media() as $media) {
                    $mainMediaType = strtok((string) $media->mediaType(), '/');
                    if (in_array($mainMediaType, ['image', 'audio', 'video'])) {
                        ++$this->totalMedias;
                        $this->prepareSize($media);
                    }
                    unset($media);
                }
                unset($item);

                ++$this->totalProcessed;
            }

            $this->entityManager->clear();
            $offset += self::SQL_LIMIT;
        }

        $this->logger->notice(new Message(
            'End of bulk sizing: %1$d/%2$d items processed, %3$d audio, video and images files sized, %4$d errors, %5$d skipped on a total of %6$d images.', // @translate
            $this->totalProcessed,
            $this->totalToProcess,
            $this->totalSucceed,
            $this->totalFailed,
            $this->totalSkipped,
            $this->totalMedias
        ));
    }

    protected function prepareSizer(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $this->mediaDimension = $services->get('ControllerPluginManager')->get('mediaDimension');
        // The api cannot update value "data", so use entity manager.
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->mediaRepository = $this->entityManager->getRepository(\Omeka\Entity\Media::class);

        $this->filter = $this->getArg('filter', 'all');
        if (!in_array($this->filter, ['all', 'sized', 'unsized'])) {
            $this->filter = 'all';
        }
        $this->imageTypes = array_keys($services->get('Config')['thumbnails']['types']);
        // Keep original first.
        array_unshift($this->imageTypes, 'original');
    }

    protected function prepareSize(MediaRepresentation $media): void
    {
        $mainMediaType = strtok((string) $media->mediaType(), '/');
        if (!in_array($mainMediaType, ['image', 'audio', 'video'])) {
            return;
        }

        // Keep possible data added by another module.
        $mediaData = $media->mediaData() ?: [];

        switch ($mainMediaType) {
            case 'audio':
            case 'video':
                if ($this->filter === 'sized') {
                    if (empty($mediaData['dimensions']['original']['duration'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                } elseif ($this->filter === 'unsized') {
                    if (!empty($mediaData['dimensions']['original']['duration'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                }
                break;
            case 'image':
            default:
                // Some images have no original.
                if ($this->filter === 'sized') {
                    if (empty($mediaData['dimensions']['large']['width'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                } elseif ($this->filter === 'unsized') {
                    if (!empty($mediaData['dimensions']['large']['width'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                }
                break;
        }

        $this->logger->info(new Message(
            'Media #%d: Sizing', // @translate
            $media->id()
        ));

        /** @var \Omeka\Entity\Media $mediaEntity */
        $mediaEntity = $this->mediaRepository->find($media->id());

        // Reset dimensions to make the sizer working.
        // TODO In rare cases, the original file is removed once the thumbnails are built.
        $mediaData['dimensions'] = [];
        $mediaEntity->setData($mediaData);

        $failedTypes = [];
        foreach ($mainMediaType === 'image' ? $this->imageTypes : ['original'] as $imageType) {
            $result = $this->mediaDimension->__invoke($media, $imageType);
            if (!array_filter($result)) {
                $failedTypes[] = $imageType;
            }
            $mediaData['dimensions'][$imageType] = $result;
        }
        if (count($failedTypes)) {
            $this->logger->err(new Message(
                'Media #%1$d: Error getting dimensions for types "%2$s".', // @translate
                $mediaEntity->getId(),
                implode('", "', $failedTypes)
            ));
            ++$this->totalFailed;
        }

        $mediaEntity->setData($mediaData);
        $this->entityManager->persist($mediaEntity);
        $this->entityManager->flush();
        unset($mediaEntity);

        ++$this->totalSucceed;
    }
}
