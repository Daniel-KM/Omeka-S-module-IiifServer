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

use Common\Stdlib\PsrMessage;
use Omeka\Api\Representation\MediaRepresentation;

trait TraitLinking
{
    /**
     * @var \Omeka\Api\Representation\SiteRepresentation|null
     */
    protected $defaultSite;

    /**
     * @var \IiifServer\View\Helper\IiifUrl
     */
    protected $iiifUrl;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSize;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \Omeka\Settings\SiteSettings
     */
    protected $siteSettings;

    /**
     * @var \Laminas\View\Helper\Url
     */
    protected $urlHelper;

    public function homepage(): ?array
    {
        if (array_key_exists('homepage', $this->cache)) {
            return $this->cache['homepage'];
        }

        $homePages = $this->settings->get('iiifserver_manifest_homepage', ['property_or_resources']);
        if (empty($homePages) || in_array('none', $homePages)) {
            $this->cache['homepage'] = [];
            return $this->cache['homepage'];
        }
        $homePages = array_combine($homePages, $homePages);

        $defaultLocale = ($this->settings->get('locale') ?: 'none');

        $site = $this->defaultSite;
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
            $language = $this->siteSettings->get('locale') ?: $defaultLocale;
        } else {
            $language = $defaultLocale;
        }

        // TODO Move this cleaning of list of home page into config.

        $hasResource = isset($homePages['resource']);
        $hasResources = isset($homePages['resources']);
        $hasPropertyOrResources = isset($homePages['property_or_resources']);
        $hasPropertyOrResource = isset($homePages['property_or_resource']);

        if ($hasPropertyOrResource) {
            $homePages['property'] = 'property';
            $homePages['resource'] = 'resource';
            unset($homePages['property_or_resource']);
        }
        if ($hasPropertyOrResources) {
            $homePages['property'] = 'property';
            $homePages['resources'] = 'resources';
            unset($homePages['property_or_resources']);
            unset($homePages['property_or_resource']);
        }

        // "site" is default site, but "sites" is sites of the resource.
        $sites = (isset($homePages['resources']) || isset($homePages['sites'])) && method_exists($this->resource, 'sites')
            ? $this->resource->sites()
            : [];

        $homePageValue = function ($id, $format, $language, $values, $fallback) {
            return [
                'id' => $id,
                'type' => 'Text',
                'label' => ValueLanguage::output($values, false, $fallback),
                'format' => $format,
                'language' => [
                    $language,
                ],
            ];
        };

        $result = [
            'property' => [],
            'resource' => [],
            'resources' => [],
            'site' => [],
            'sites' => [],
            'api' => [],
        ];

        foreach ($homePages as $homePage) {
            $id = null;
            $values = [];
            $fallback = null;
            $format = 'text/html';
            switch ($homePage) {
                default:
                case 'property_or_resource':
                case 'property_or_resources':
                    continue 2;
                case 'property':
                    $property = $this->settings->get('iiifserver_manifest_homepage_property');
                    if (!$property) {
                        continue 2;
                    }
                    /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
                    $resourceValues = $this->resource->value($property, ['all' => true]);
                    if ($resourceValues) {
                        foreach ($resourceValues as $value) {
                            $val = $value->uri() ?: $value->value();
                            if (filter_var($val, FILTER_VALIDATE_URL)) {
                                $id = $val;
                                if ($value->type() === 'uri') {
                                    $fallback = $value->value() ?: 'Source'; // @translate
                                } else {
                                    $fallback = 'Source'; // @translate
                                }
                                $result['property'][] = $homePageValue($id, $format, $language, [], $fallback);
                            }
                        }
                    }
                    break;
                case 'resource':
                    if ($site) {
                        $id = $this->resource->siteUrl($site->slug(), true);
                        $fallback = (string) new PsrMessage(
                            'Resource in site: {site_title}', // @translate
                            ['site_title' => $site->title()]
                        );
                    } else {
                        $id = $this->resource->apiUrl();
                        $format = 'application/ld+json';
                        $fallback = 'Json-ld api'; // @translate
                    }
                    $result['resource'][] = $homePageValue($id, $format, $language, [], $fallback);
                    break;
                case 'resources':
                    foreach ($sites as $site) {
                        $this->siteSettings->setTargetId($site->id());
                        $id = $this->resource->siteUrl($site->slug(), true);
                        $locale = $this->siteSettings->get('locale') ?: $defaultLocale;
                        $fallback = (string) new PsrMessage(
                            'Resource in site: {site_title}', // @translate
                            ['site_title' => $site->title()]
                        );
                        $result['resources'][] = $homePageValue($id, $format, $locale, [], $fallback);
                    }
                    break;
                case 'site':
                    if ($site) {
                        $id = $site->siteUrl($site->slug(), true);
                        $values = [$language => [$site->title()]];
                    } else {
                        $id = $this->urlHelper->__invoke('top', [], ['force_canonical' => true]);
                        $values = [$language => [$this->settings->get('installation_title')]];
                    }
                    $result['site'][] = $homePageValue($id, $format, $language, $values, $fallback);
                    break;
                case 'sites':
                    foreach ($sites as $site) {
                        $this->siteSettings->setTargetId($site->id());
                        $id = $site->siteUrl($site->slug(), true);
                        $locale = $this->siteSettings->get('locale') ?: $defaultLocale;
                        $values = [$locale => [$site->title()]];
                        $result['sites'][] = $homePageValue($id, $format, $locale, $values, $fallback);
                    }
                    break;
                case 'api':
                    $id = $this->resource->apiUrl();
                    $format = 'application/ld+json';
                    $fallback = 'Json-ld api'; // @translate
                    $result['api'][] = $homePageValue($id, $format, $language, [], $fallback);
                    break;
            }
        }

        if ($result['property']) {
            if ($hasPropertyOrResource && !$hasResource) {
                unset($result['resource']);
            }
            if ($hasPropertyOrResources && !$hasResources) {
                unset($result['resources']);
            }
        }

        $output = [];
        foreach ($result as $value) {
            $output = array_merge(array_values($output), array_values($value));
        }

        $this->cache['homepage'] = count($output) < 2
            ? $output
            : array_values(array_map('unserialize', array_unique(array_map('serialize', array_filter($output)))));

        return $this->cache['homepage'];
    }

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

    /**
     * @todo Normalize logo().
     */
    public function logo(): ?array
    {
        $url = $this->settings->get('iiifserver_manifest_logo_default');
        if (!$url) {
            return null;
        }

        // TODO Improve check of media type of the logo.
        $format = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        $mediaTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];

        try {
            $size = $this->imageSize->__invoke($url);
        } catch (\Exception $e) {
            return null;
        }

        if (empty($size['width']) || empty($size['height'])) {
            return null;
        }

        $output = [
            'id' => $url,
            'type' => 'Image',
            'format' => @$mediaTypes[$format],
            'height' => $size['height'],
            'width' => $size['width'],
        ];
        return [
            $output,
        ];
    }

    /**
     * @todo Normalize seeAlso().
     */
    public function seeAlso(): ?array
    {
        $output = [];

        $property = $this->settings->get('iiifserver_manifest_seealso_property');

        /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
        $values = $property ? $this->resource->value($property, ['all' => true]) : [];
        foreach ($values as $value) {
            $uri = $value->uri();
            $val = (string) $value->value();
            $id = $uri ?: $val;
            if (filter_var($id, FILTER_VALIDATE_URL)) {
                $element = [
                    'id' => $id,
                    'type' => 'Dataset',
                ];
                if ($value->type() === 'uri') {
                    if (mb_strlen($val)) {
                        // TODO Use ValueLanguage.
                        $element['label'] = ['none' => [$val]];
                    }
                    // TODO Add format and profile of the seealso (require a fetch?).
                    // $element['format'] = $value->value();
                    // $element['profile'] = $value->value();
                }
                $output[] = $element;
            }
        }

        // Added the link to the json-ld representation.
        $output[] = [
            'id' => $this->resource->apiUrl(),
            'type' => 'Dataset',
            'label' => ['none' => ['Api rest json-ld']],
            'format' => 'application/ld+json',
            'profile' => $this->urlHelper->__invoke('api-context', [], ['force_canonical' => true]),
        ];

        return $output;
    }

    /**
     * @see https://iiif.io/api/presentation/3.0/#start
     */
    public function start(): ?array
    {
        // TODO Start is currently only available for Manifest, not Range.
        if ($this->type !== 'Manifest') {
            return null;
        }

        $property = $this->settings->get('iiifserver_manifest_start_property');
        $usePrimaryMedia = (bool) $this->settings->get('iiifserver_manifest_start_primary_media');
        if (!$property && !$usePrimaryMedia) {
            return null;
        }

        // TODO Factorize with TraitDescriptive::placeholderCanvas().
        // Get the index of the media as Canvas (see manifest->items()).
        $canvasId = function (?MediaRepresentation $media): ?string {
            $mediaInfo = $this->mediaInfo($media);
            if ($mediaInfo && !empty($mediaInfo['index'])) {
                // See construction of the url in Canvas->id().
                $name = $mediaInfo['index'];
                $targetName = (string) (int) $name === (string) $name
                    ? 'p' . $name
                    : $name;
                return $this->iiifUrl->__invoke($this->resource, 'iiifserver/uri', '3', [
                    'type' => 'canvas',
                    'name' => $targetName,
                ]);
            }
            return null;
        };

        /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
        $values = $property ? $this->resource->value($property, ['all' => true]) : [];
        foreach ($values as $value) {
            $vr = $value->resource();
            if ($vr) {
                if ($vr instanceof  MediaRepresentation) {
                    $id = $canvasId($vr);
                    if ($id) {
                        return [
                            'id' => $id,
                            'type' => 'Canvas',
                        ];
                    }
                }
            } elseif (!$value->uri()) {
                // There is no check on the value for start.
                $id = $canvasId($this->resource->primaryMedia());
                if ($id) {
                    // TODO Create a class for SpecificResource.
                    return [
                        // TODO Check if the canvas id (uri, not url) with subtype /segment/ is better than /canvas-segment/.
                        'id' => $id . '/segment/1',
                        'type' => 'SpecificResource',
                        // Here, we are in manifest. So the value is used for
                        // audio/video, so the first media, so index #1.
                        'source' =>$id,
                        'selector' => [
                            'type' => 'PointSelector',
                            't' => $value->value(),
                        ],
                    ];
                }
            }
        }

        if (!$usePrimaryMedia) {
            $media = $this->resource->primaryMedia();
            $id = $canvasId($media);
            if ($id) {
                return [
                    'id' => $id,
                    'type' => 'Canvas',
                ];
            }
        }

        return null;
    }
}
