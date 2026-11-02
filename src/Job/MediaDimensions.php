<?php declare(strict_types=1);

namespace IiifServer\Job;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Job\AbstractJob;

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
        $this->logger = $services->get('Omeka\Logger');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('iiifserver/media-dimensions/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);
        $api = $services->get('Omeka\ApiManager');

        $query = $this->getArg('query') ?: [];
        if (is_string($query)) {
            $sQuery = [];
            parse_str($query, $sQuery);
            $query = $sQuery ?: [];
        }

        $scope = (array) $this->getArg('scope', ['items', 'digital_objects']);
        $scope = array_values(array_intersect($scope, ['items', 'digital_objects']));
        if (!$scope) {
            $this->logger->warn(
                'No scope selected (items / digital objects). Nothing to do.' // @translate
            );
            return;
        }
        $doItems = in_array('items', $scope, true);
        $doDigitalObjects = in_array('digital_objects', $scope, true);

        $this->prepareSizer();

        $this->totalToProcess = 0;
        $this->totalMedias = 0;
        $this->totalProcessed = 0;
        $this->totalSucceed = 0;
        $this->totalFailed = 0;
        $this->totalSkipped = 0;

        $response = $api->search('items', $query);
        $this->totalToProcess = $response->getTotalResults();
        if (empty($this->totalToProcess)) {
            $this->logger->warn(
                'No item selected. You may check your query.' // @translate
            );
            return;
        }

        $this->logger->info(
            'Starting bulk sizing for {total} items ({mode} media, scope: {scope}).', // @translate
            ['total' => $this->totalToProcess, 'mode' => $this->filter, 'scope' => implode(', ', $scope)]
        );

        // Collect ids of digital objects referenced by items, to deduplicate
        // across items (a DO can be shared by many items).
        $doIds = [];

        $offset = 0;
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
                    $this->logger->warn(
                        'The job "Media Dimensions" was stopped: {count}/{total} resources processed.', // @translate
                        ['count' => $offset + $key, 'total' => $this->totalToProcess]
                    );
                    break 2;
                }

                if ($doItems) {
                    /** @var \Omeka\Api\Representation\MediaRepresentation $media */
                    foreach ($item->media() as $media) {
                        if (!$this->isImageAudioVideoMedia($media)
                            // For ingester bulk_upload, wait that the process
                            // is finished, else the thumbnails won't be
                            // available and the size of derivative will be the
                            // fallback.
                            || $media->ingester() === 'bulk_upload'
                        ) {
                            unset($media);
                            continue;
                        }
                        ++$this->totalMedias;
                        $this->prepareSize($media);
                        unset($media);
                    }
                }

                if ($doDigitalObjects) {
                    foreach ($item->values() as $property) {
                        foreach ($property['values'] as $value) {
                            $vr = $value->valueResource();
                            if ($vr && $vr->resourceName() === 'digital_objects') {
                                $doIds[$vr->id()] = true;
                            }
                        }
                    }
                }

                unset($item);

                ++$this->totalProcessed;
            }

            $this->entityManager->clear();
            $offset += self::SQL_LIMIT;
            $this->logger->info(
                'Progress: {count}/{total} items processed, {medias} medias seen, {sized} sized, {skipped} skipped, {failed} failed.', // @translate
                [
                    'count' => $this->totalProcessed,
                    'total' => $this->totalToProcess,
                    'medias' => $this->totalMedias,
                    'sized' => $this->totalSucceed,
                    'skipped' => $this->totalSkipped,
                    'failed' => $this->totalFailed,
                ]
            );
        }

        // Second pass: digital objects referenced by the matched items.
        if ($doDigitalObjects && $doIds) {
            $doIds = array_keys($doIds);
            foreach (array_chunk($doIds, self::SQL_LIMIT) as $chunk) {
                if ($this->shouldStop()) {
                    break;
                }
                try {
                    $dos = $api->search('digital_objects', ['id' => $chunk])->getContent();
                } catch (\Omeka\Api\Exception\BadRequestException $e) {
                    break;
                }
                foreach ($dos as $do) {
                    if ($this->shouldStop()) {
                        break 2;
                    }
                    if (!$this->isImageAudioVideoMedia($do)) {
                        unset($do);
                        continue;
                    }
                    ++$this->totalMedias;
                    $this->prepareSize($do);
                    unset($do);
                }
                $this->entityManager->clear();
            }
        }

        $this->logger->notice(
            'End of bulk sizing: {count}/{total} items processed, {count_medias} media checked (images, audio, video), {count_succeed} sized, {count_skipped} already sized (skipped), {count_failed} errors.', // @translate
            [
                'count' => $this->totalProcessed,
                'total' => $this->totalToProcess,
                'count_medias' => $this->totalMedias,
                'count_succeed' => $this->totalSucceed,
                'count_skipped' => $this->totalSkipped,
                'count_failed' => $this->totalFailed,
            ]
        );
    }

    protected function prepareSizer(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $this->mediaDimension = $services->get('ControllerPluginManager')->get('mediaDimension');
        // The api cannot update value "data", so use entity manager.
        $this->entityManager = $services->get('Omeka\EntityManager');
        // Use Resource repository to support both Media and DigitalObject.
        $this->mediaRepository = $this->entityManager->getRepository(\Omeka\Entity\Resource::class);

        $this->filter = $this->getArg('filter', 'all');
        if (!in_array($this->filter, ['all', 'sized', 'unsized'])) {
            $this->filter = 'all';
        }
        $this->imageTypes = array_keys($services->get('Config')['thumbnails']['types']);
        // Keep original first.
        array_unshift($this->imageTypes, 'original');
    }

    /**
     * @see \IiifServer\Module::prepareSizeItem()
     * @see \IiifServer\Job\MediaDimensions::prepareSize()
     */
    protected function prepareSize(AbstractResourceEntityRepresentation $media): void
    {
        if (!$this->isImageAudioVideoMedia($media)) {
            return;
        }
        $mainMediaType = $this->mainMediaType($media);

        // Keep possible data added by another module.
        $mediaData = $media->mediaData() ?: [];

        // Expected types: image carries original + all thumbnails; audio/video
        // only the original (no derivative geometry).
        $expectedTypes = $mainMediaType === 'image'
            ? $this->imageTypes
            : ['original'];

        // Pivot value to test per type: images use width (dimension), audio /
        // video use duration (time). A type counts as "already attempted" when
        // its pivot key exists in the stored dimensions — even with a null
        // value, which means a previous run tried and failed to read the file.
        // Using empty() would treat null as missing and reprocess the same
        // broken file on every run.
        $pivot = $mainMediaType === 'image' ? 'width' : 'duration';
        $missing = [];
        foreach ($expectedTypes as $type) {
            $entry = $mediaData['dimensions'][$type] ?? null;
            if (!is_array($entry) || !array_key_exists($pivot, $entry)) {
                $missing[] = $type;
            }
        }

        if ($this->filter === 'unsized' && !$missing) {
            // Everything already sized: nothing to do for this filter.
            ++$this->totalSkipped;
            return;
        }
        if ($this->filter === 'sized' && count($missing) === count($expectedTypes)) {
            // Fully unsized: skipped by intent (the 'sized' filter targets
            // refresh of already-sized medias).
            ++$this->totalSkipped;
            return;
        }

        /** @var \Omeka\Entity\Media $mediaEntity */
        $mediaEntity = $this->mediaRepository->find($media->id());

        // Reset dimensions to make the sizer working.
        // TODO In rare cases, the original file is removed once the thumbnails are built.
        $mediaData['dimensions'] = [];
        $mediaEntity->setData($mediaData);

        $failedTypes = [];
        foreach ($mainMediaType === 'image' ? $this->imageTypes : ['original'] as $imageType) {
            // Force recalculation from file: the representation
            // still holds stale data after entity reset above.
            $result = $this->mediaDimension->__invoke($media, $imageType, true);
            if (!array_filter($result)) {
                $failedTypes[] = $imageType;
            }
            $mediaData['dimensions'][$imageType] = $result;
        }
        if (count($failedTypes)) {
            $this->logger->err(
                'Media #{media_id}: Error getting dimensions for types "{types}".', // @translate
                [
                    'media_id' => $mediaEntity->getId(),
                    'types' => implode('", "', $failedTypes),
                ]
            );
            ++$this->totalFailed;
        }

        $mediaEntity->setData($mediaData);
        // The modified date refreshes the cached manifests.
        if ($mediaData != ($media->mediaData() ?: [])) {
            $mediaEntity->setModified(new \DateTime('now'));
        }
        $this->entityManager->persist($mediaEntity);
        $this->entityManager->flush();
        unset($mediaEntity);

        // Counted as "succeeded" only when at least one expected type was
        // actually measured; otherwise the run is a true failure (loggued
        // above) and must not double-count.
        if (!$failedTypes
            || count($failedTypes) < (
                $mainMediaType === 'image' ? count($this->imageTypes) : 1
            )
        ) {
            ++$this->totalSucceed;
        }
    }

    /**
     * Recognise a resource that carries width/height/duration semantics,
     * including IIIF-ingested medias whose stored media_type is null. Those
     * remote IIIF medias are always images by construction (Image API or
     * Presentation canvas).
     */
    protected function isImageAudioVideoMedia(AbstractResourceEntityRepresentation $media): bool
    {
        $mainMediaType = $this->mainMediaType($media);
        return in_array($mainMediaType, ['image', 'audio', 'video'], true);
    }

    /**
     * Resolve the main media type, with a fallback for IIIF-ingested medias
     * whose stored media_type is null.
     *
     * The Image API ingester ('iiif') always describes an image. The
     * Presentation ingester ('iiif_presentation') can describe any media
     * (canvas content), so the type is read from the payload format/type field;
     * if absent, the media is reported as unknown.
     */
    protected function mainMediaType(AbstractResourceEntityRepresentation $media): string
    {
        $main = strtok((string) $media->mediaType(), '/');
        if ($main !== false && $main !== '') {
            return $main;
        }
        // Use the renderer (not the ingester) so digital objects, whose
        // ingester is always "digital_object", are recognized too.
        $renderer = method_exists($media, 'renderer') ? (string) $media->renderer() : '';
        if ($renderer === 'iiif') {
            return 'image';
        }
        if ($renderer === 'iiif_presentation') {
            $data = $media->mediaData() ?: [];
            // IIIF Presentation 3: canvas/painting body has a `format` (mime)
            // and a `type` ("Image", "Sound", "Video"). v2 uses `format` and
            // `@type` ("oa:Annotation" with motivation "painting" wraps the
            // content).
            $format = $data['format'] ?? null;
            if (is_string($format) && $format !== '') {
                $main = strtok($format, '/');
                if (in_array($main, ['image', 'audio', 'video'], true)) {
                    return $main;
                }
            }
            $type = $data['type'] ?? $data['@type'] ?? null;
            if (is_string($type)) {
                $type = strtolower($type);
                if ($type === 'image' || $type === 'dctypes:image' || $type === 'sc:image') {
                    return 'image';
                }
                if ($type === 'sound' || $type === 'audio') {
                    return 'audio';
                }
                if ($type === 'video' || $type === 'dctypes:movingimage') {
                    return 'video';
                }
            }
        }
        return '';
    }
}
