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

use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

trait TraitDescriptive
{
    // use TraitDescriptiveLabel;
    use TraitDescriptiveRights;
    use TraitDescriptiveThumbnail;
    use TraitLinkingLogo;
    use TraitMedia;

    /**
     * @var \IiifServer\View\Helper\MediaDimension
     */
    protected $mediaDimension;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * Label is specific for each iiif resource, so skipped for now here.
     */
    /*
    public function label(): ?array
    {
        return null;
    }
    */

    /**
     * List metadata of the resource.
     *
     * @todo Remove setting iiifserver_manifest_html_descriptive for v3.0.
     * @todo Remove publicResourceUrl for v3.0?
     * @todo Remove some settings for v3.0.
     * @todo Use displayValues() or the event?
     * @todo Remove special properties used in other property.
     */
    public function metadata(): array
    {
        $jsonLdType = $this->resource->getResourceJsonLdType();
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

        $whitelist = $this->settings->get($map[$jsonLdType]['whitelist'], []);
        if ($whitelist === ['none']) {
            return [];
        }

        $values = $whitelist
            ? array_intersect_key($this->resource->values(), array_flip($whitelist))
            : $this->resource->values();

        $blacklist = $this->settings->get($map[$jsonLdType]['blacklist'], []);
        if ($blacklist) {
            $values = array_diff_key($values, array_flip($blacklist));
        }
        if (empty($values)) {
            return [];
        }

        // TODO Remove automatically special properties, and only for values that are used (check complex conditions…).

        $metadata = [];
        foreach ($values as $propertyData) {
            $metadataValue = new ValueLanguage($propertyData['values'], true, null, true);
            if (!$metadataValue->count()) {
                continue;
            }

            $propertyLabel = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $labels = [];
            foreach ($metadataValue->langs() as $lang) {
                $labels[$lang][] = $propertyLabel;
            }
            $metadataLabel = new ValueLanguage($labels);

            $metadata[] = [
                'label' => $metadataLabel->jsonSerialize(),
                'value' => $metadataValue->jsonSerialize(),
            ];
        }
        return $metadata;
    }

    public function summary(): ?array
    {
        $summaryProperty = $this->settings->get('iiifserver_manifest_summary_property');
        if (!$summaryProperty) {
            return null;
        }
        // TODO Manage language of the summary.
        $values = $summaryProperty === 'template'
            ? array_filter([$this->resource->displayDescription()])
            : $this->resource->value($summaryProperty, ['all' => true]);
        return ValueLanguage::output($values, true);
    }

    /**
     * @todo Normalize format of required statement.
     *
     * Adapted according to https://github.com/Daniel-KM/Omeka-S-module-IiifServer/issues/37
     * in order to include the rights value when it is not a url, since it is
     * skipped when it is not an url.
     */
    public function requiredStatement(): ?array
    {
        $requiredStatement = [];
        $requiredStatement = $this->settings->get('iiifserver_manifest_attribution_property');
        if ($requiredStatement) {
            $requiredStatement = $this->resource->value($requiredStatement, ['all' => true]);
        }

        if (empty($requiredStatement)) {
            $license = $this->resource ? $this->rightsResource($this->resource, true) : null;
            if ($license && !$this->checkAllowedLicense($license)) {
                $requiredStatement = ['none' => [$license]];
            } else {
                $default = $this->settings->get('iiifserver_manifest_attribution_default');
                if ($default) {
                    $requiredStatement = ['none' => [$default]];
                } else {
                    return null;
                }
            }
        }

        $metadataValue = new ValueLanguage($requiredStatement, true);
        if (!$metadataValue->count()) {
            return null;
        }

        $propertyLabel = 'Attribution'; // @translate

        $labels = [];
        foreach ($metadataValue->langs() as $lang) {
            $labels[$lang][] = $propertyLabel;
        }
        $metadataLabel = new ValueLanguage($labels);

        return [
            'label' => $metadataLabel->jsonSerialize(),
            'value' => $metadataValue->jsonSerialize(),
        ];
    }

    /* Managed by TraitDescriptiveRights.
    public function rights(): ?string
    {
        return null;
    }
    */

    public function provider(): ?array
    {
        $providers = $this->settings->get('iiifserver_manifest_provider', []);
        if (empty($providers) || in_array('none', $providers)) {
            return null;
        }

        $providersAll = array_fill_keys($providers, true);

        $onlyPropertyOrAgent = isset($providersAll['property_or_agent']);
        if ($onlyPropertyOrAgent) {
            $providersAll['property'] ??= false;
            $providersAll['agent'] ??= false;
            unset($providersAll['property_or_agent']);
        }

        $onlyPropertyOrSimple = isset($providersAll['property_or_simple']);
        if ($onlyPropertyOrAgent) {
            $providersAll['property'] ??= false;
            $providersAll['simple'] ??= false;
            unset($providersAll['property_or_simple']);
        }

        $output = [];
        foreach (array_keys($providersAll) as $provider) {
            $agent = null;
            switch ($provider) {
                default:
                    continue 2;
                case 'none':
                    return null;
                case 'property':
                    $property = $this->settings->get('iiifserver_manifest_provider_property');
                    if (!$property) {
                        continue 2;
                    }
                    /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
                    $values = $this->resource->value($property, ['all' => true]);
                    if ($values) {
                        foreach ($values as $value) {
                            if (!$value->uri() && !$value->valueResource()) {
                                $agent = $value->value();
                                break;
                            }
                        }
                    }
                    if ($agent) {
                        $provider = json_decode($agent, true);
                        if ($provider && is_array($provider)) {
                            $output['property'] = $provider;
                        }
                    }
                    break;
                case 'agent':
                    $provider = $this->settings->get('iiifserver_manifest_provider_agent');
                    if ($provider) {
                        $provider = json_decode($provider, true);
                        if ($provider && is_array($provider)) {
                            $output['agent'] = $provider;
                        }
                    }
                    break;
                case 'simple':
                    $output['simple'] = [
                        'id' => $this->urlHelper->__invoke('top', [], ['force_canonical' => true]),
                        'type' => 'Agent',
                        'label' => ValueLanguage::output([], false, $this->settings->get('installation_title')),
                    ];
                    $logo = $this->logo();
                    if ($logo) {
                        $output['simple']['logo'] = $logo;
                    }
                    break;
            }
        }

        if ($onlyPropertyOrAgent
            && (!empty($output['property']) && !empty($output['agent']) && !$providersAll['agent'])
        ) {
            unset($output['agent']);
        }

        if ($onlyPropertyOrSimple
            && (!empty($output['property']) && !empty($output['simple']) && !$providersAll['simple'])
        ) {
            unset($output['simple']);
        }

        return array_values($output);
    }

    /* Managed by TraitDescriptiveThumbnail.
    public function thumbnail(): ?array
    {
        return null;
    }
     */

    /**
     * The placeholder canvas can be set in item or media values.
     *
     * Because the accompanying canvas is not supported by common iiif viewers,
     * duplicate it with a placeholder canvas.
     *
     * @see https://iiif.io/api/presentation/3.0/#placeholdercanvas
     *
     * @todo Return a canvas instead of an array.
     */
    public function placeholderCanvas(): ?array
    {
        $specificCanvas = $this->accompanyingOrPlaceholderCanvas('placeholder');
        return $specificCanvas
            ?: $this->placeholderCanvasDefault();
    }

    protected function placeholderCanvasDefault(): ?array
    {
        $property = $this->settings->get('iiifserver_manifest_placeholder_canvas_property');
        if (!$property) {
            return null;
        }

        $defaultPlaceholder = $this->settings->get('iiifserver_manifest_placeholder_canvas_default');
        if ($defaultPlaceholder && !filter_var($defaultPlaceholder, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            $defaultPlaceholder = null;
        }

        $matchValue = mb_strtolower($this->settings->get('iiifserver_manifest_placeholder_canvas_value', ''));

        $isCanvas = $this->type === 'Canvas';

        // For canvas, check if tthe parent items contains a list media to protect.
        if ($isCanvas && $defaultPlaceholder && $this->resource instanceof MediaRepresentation) {
            /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
            $resourceId = (int) $this->resource->id();
            $values = $this->resource->item()->value($property, ['all' => true]);
            foreach ($values as $value) {
                $vr = $value->valueResource();
                if ($vr && (int) $vr->id() === $resourceId) {
                    return $this->canvasFromUrlPlaceholder($defaultPlaceholder, null);
                }
            }
        }

        /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
        $values = $this->resource->value($property, ['all' => true]);
        foreach ($values as $value) {
            if ($value->valueResource()) {
                continue;
            }
            $uri = $value->uri();
            $val = (string) $value->value();
            $url = $uri ?: $val;
            if (filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                return $this->canvasFromUrlPlaceholder($url, $val === $url ? null : $val);
            } elseif ($defaultPlaceholder) {
                $dataType = $value->type();
                if (($dataType === 'boolean' && $value->value())
                    || ($matchValue && mb_strtolower($val) === $matchValue)
                    // Presence of a value means to use the default placeholder.
                    || (!$matchValue && $dataType !== 'boolean')
                ) {
                    return $this->canvasFromUrlPlaceholder($defaultPlaceholder, null);
                }
            }
        }

        return null;
    }

    /**
     * The accompanying canvas can be set in item or media values.
     *
     * @see https://iiif.io/api/presentation/3.0/#accompanyingcanvas
     * @see https://iiif.io/api/cookbook/recipe/0014-accompanyingcanvas/
     *
     * @todo Return a canvas instead of an array.
     */
    public function accompanyingCanvas(): ?array
    {
        return $this->accompanyingOrPlaceholderCanvas('accompanying');
    }

    protected function accompanyingOrPlaceholderCanvas(string $subType): ?array
    {
        // TODO The accompanying canvas manages only podcast for now (an audio and a single image).
        // TODO Use a real Canvas and remove too much specific code.

        // TODO Factorize.

        if ($subType === 'placeholder') {
            if (!$this->resource instanceof itemRepresentation
                || count($this->mediaInfos) !== 2
            ) {
                return null;
            }
            // Check for sound and image.
            $first = reset($this->mediaInfos);
            // Fix when there is no resource because the media is html.
            if (empty($first['resource'])) {
                return null;
            }
            $firstMediaType = (string) $first['resource']->mediaType();
            if (strtok($firstMediaType, '/') !== 'audio') {
                return null;
            }
            $second = array_reverse($this->mediaInfos);
            $second = reset($second);
            if (empty($second['resource'])) {
                return null;
            }
            $mediaAccompanying = $second['resource'];
            $secondMediaType = (string) $mediaAccompanying->mediaType();
            if (strtok($secondMediaType, '/') !== 'image') {
                return null;
            }

            $mediaAccompanyingSize = $this->imageSize->__invoke($mediaAccompanying);
            if (!$mediaAccompanyingSize['width'] || !$mediaAccompanyingSize['height']) {
                return null;
            }

            $contentResource = $second['content'];
        } elseif (!$this->resource instanceof MediaRepresentation
            || $this->options['index'] !== 1
            || count($this->options['mediaInfos']['indexes']) !== 2
        ) {
            return null;
        } else {
            // Check if it is an audio.
            // TODO Here, $this->isAudio() does not work, because this is a Canvas.
            $mediaDims = $this->mediaDimensions();
            if (count(array_filter($mediaDims)) !== 1 || !isset($mediaDims['duration'])) {
                return null;
            }

            // Check if the second media is an image.
            $mediaId = $this->options['mediaInfos']['indexes'];
            unset($mediaId[$this->resource->id()]);
            $mediaId = key($mediaId);
            if (!$mediaId) {
                return null;
            }

            try {
                // The media should be cached by doctrine.
                /** @var \Omeka\Api\Representation\MediaRepresentation $mediaAccompanying */
                $mediaAccompanying = $this->api->read('media', ['id' => $mediaId])->getContent();
            } catch (\Exception $e) {
                return null;
            }

            $mediaType = (string) $mediaAccompanying->mediaType();
            if (strtok($mediaType, '/') !== 'image') {
                return null;
            }

            $mediaAccompanyingSize = $this->imageSize->__invoke($mediaAccompanying);
            if (!$mediaAccompanyingSize['width'] || !$mediaAccompanyingSize['height']) {
                return null;
            }

            $contentResource = new ContentResource();
            $contentResource
                ->setResource($mediaAccompanying);
            if (!$contentResource->hasIdAndType()) {
                return null;
            }
        }

        $item = $mediaAccompanying->item();

        /*
        $opts = [
            'callingResource' => $this->resource,
            'callingMotivation' => 'painting',
            'isDereferenced' => false,
        ];

        $annotationPage = new AnnotationPage();
        $annotationPage
            ->setOptions($opts)
            ->setResource($this->resource);

        return $annotationPage->id()
            ? $annotationPage
            : null;
        */

        /*
        $annotation = new Annotation();
        $annotation
            ->setOptions([
                'motivation' => 'painting',
            ])
            ->setResource($mediaAccompanying);

        if (!$annotation->id()) {
            return null;
        }
        */

        $body = new Annotation\Body();
        $body
            ->setOptions([
                'content' => $contentResource,
            ])
            ->setResource($mediaAccompanying)
            ->normalize();

        $target = $this->iiifUrl->__invoke($item, 'iiifserver/uri', '3', [
            'type' => 'canvas',
            'name' => $item->id(),
            'subtype' => $subType,
            'subname' => $mediaAccompanying->id(),
        ]);

        $annotation = [
            'id' => $this->iiifUrl->__invoke($item, 'iiifserver/uri', '3', [
                'type' => 'annotation',
                'name' => $mediaAccompanying->id(),
            ]),
            'type' => 'Annotation',
            'motivation' => 'painting',
            'body' => $body->jsonSerialize(),
            'target' => $target,
        ];

        $annotationPage = [
            'id' => $this->iiifUrl->__invoke($item, 'iiifserver/uri', '3', [
                'type' => 'annotation-page',
                'name' => $mediaAccompanying->id(),
            ]),
            'type' => 'AnnotationPage',
            'items' => [$annotation],
        ];

        // Get a single accompanying canvas.
        return [
            'id' => $target,
            'type' => 'Canvas',
            'label' => new valueLanguage($mediaAccompanying->displayTitle()),
            'height' => $mediaAccompanyingSize['height'],
            'width' => $mediaAccompanyingSize['width'],
            'items' => [$annotationPage],
        ];
    }

    /**
     * @todo Return a real canvas instead of an url.
     */
    protected function canvasFromUrlPlaceholder(string $url, ?string $label): ?array
    {
        // Dimensions are required for a canvas.
        $dimensions = array_filter($this->mediaDimension->__invoke($url));
        if (!$dimensions || !array_filter($dimensions)) {
            return null;
        }

        // TODO Set format of the image.
        if (isset($dimensions['duration'])) {
            $iiifType = isset($dimensions['width']) ? 'Video' : 'Sound';
        } else {
            $iiifType = 'Image';
        }

        // Create canvas manually.
        $element = [
            'id' => $url . '/placeholder',
            'type' => 'Canvas',
        ];

        if (mb_strlen((string) $label)) {
            // TODO Use ValueLanguage.
            $element['label'] = ['none' => [$label]];
        }

        $element += $dimensions;

        // TODO Use Annotation / AnnotationPage to build the placeholderCanvas (and canvas?).
        $body = [
            'id' => $url,
            'type' => $iiifType,
            // TODO Get media type of a file.
            // 'format' => 'image/png',
        ] + $dimensions;

        $element['items'][] = [
            'id' => $url . '/placeholder/1',
            'type' => 'AnnotationPage',
            'items' => [
                [
                    'id' => $url . '/placeholder/1-image',
                    'type' => 'Annotation',
                    'motivation' => 'painting',
                    'body' => $body,
                    'target' => $url . '/placeholder',
                ],
            ],
        ];

        return $element;
    }
}
