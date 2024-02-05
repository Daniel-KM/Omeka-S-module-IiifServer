<?php declare(strict_types=1);

/*
 * Copyright 2020-2023 Daniel Berthereau
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

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\SiteRepresentation;

trait TraitLinking
{
    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSize;

    public function initLinking(): AbstractType
    {
        $services = $this->resource->getServiceLocator();
        $this->imageSize = $services->get('ControllerPluginManager')->get('imageSize');
        return $this;
    }

    public function homepage(): ?array
    {
        $homePages = $this->settings->get('iiifserver_manifest_homepage', ['property_or_resource']);
        if (empty($homePages) || in_array('none', $homePages)) {
            return null;
        }

        $site = $this->defaultSite();
        if ($site) {
            $siteSettings = $this->resource->getServiceLocator()->get('Omeka\Settings\Site');
            $siteSettings->setTargetId($site->id());
            $language = $siteSettings->get('locale') ?: ($this->settings->get('locale') ?: 'none');
        } else {
            $language = $this->settings->get('locale') ?: 'none';
        }

        $homePagesAll = array_fill_keys($homePages, true);
        $onlyPropertyOrResource = isset($homePagesAll['property_or_resource']);
        if ($onlyPropertyOrResource) {
            $homePagesAll['property'] ??= false;
            $homePagesAll['resource'] ??= false;
            unset($homePagesAll['property_or_resource']);
        }

        $output = [];
        foreach (array_keys($homePagesAll) as $homePage) {
            $id = null;
            $values = [];
            $fallback = null;
            $format = 'text/html';
            switch ($homePage) {
                default:
                    continue 2;
                case 'property':
                    $property = $this->settings->get('iiifserver_manifest_homepage_property');
                    if (!$property) {
                        continue 2;
                    }
                    /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
                    $values = $this->resource->value($property, ['all' => true]);
                    if ($values) {
                        foreach ($values as $value) {
                            $val = $value->uri() ?: $value->value();
                            if (filter_var($val, FILTER_VALIDATE_URL)) {
                                $id = $val;
                                break;
                            }
                        }
                    }
                    if (empty($id)) {
                        continue 2;
                    }
                    if ($value->type() === 'uri') {
                        $fallback = $value->value() ?: 'Source';
                    } else {
                        $fallback = 'Source';
                    }
                    $values = [];
                    break;
                case 'resource':
                    if ($site) {
                        $id = $this->resource->siteUrl($site->slug(), true);
                    } else {
                        $id = $this->resource->apiUrl();
                        $format = 'application/ld+json';
                    }
                    // displayTitle() can't be used, because language is needed.
                    $template = $this->resource->resourceTemplate();
                    if ($template && $template->titleProperty()) {
                        $term = $template->titleProperty()->term();
                        $values = $this->resource->value($term, ['all' => true]);
                        if (empty($values) && $term !== 'dcterms:title') {
                            $values = $this->resource->value('dcterms:title', ['all' => true]);
                        }
                    } else {
                        $values = $this->resource->value('dcterms:title', ['all' => true]);
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
                    break;
                case 'api':
                    $id = $this->resource->apiUrl();
                    $format = 'application/ld+json';
                    // displayTitle() can't be used, because language is needed.
                    $template = $this->resource->resourceTemplate();
                    if ($template && $template->titleProperty()) {
                        $term = $template->titleProperty()->term();
                        $values = $this->resource->value($term, ['all' => true]);
                        if (empty($values) && $term !== 'dcterms:title') {
                            $values = $this->resource->value('dcterms:title', ['all' => true]);
                        }
                    } else {
                        $values = $this->resource->value('dcterms:title', ['all' => true]);
                    }
                    break;
                case 'none':
                    return null;
            }
            if ($id) {
                $label = new ValueLanguage($values, false, $fallback);
                $output[$homePage] = [
                    'id' => $id,
                    'type' => 'Text',
                    'label' => $label,
                    'format' => $format,
                    'language' => [
                        $language,
                    ],
                ];
            }
        }

        if ($onlyPropertyOrResource
            && (!empty($output['property']) && !empty($output['resource']) && !$homePagesAll['resource'])
        ) {
            unset($output['resource']);
        }

        return array_values($output);
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
                        'label' => new ValueLanguage([], false, $this->settings->get('installation_title')),
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

    protected function defaultSite(): ?SiteRepresentation
    {
        if (!array_key_exists('site', $this->_storage)) {
            $this->_storage['site'] = null;
            if (!$this->resource) {
                return null;
            }
            /** @var \Omeka\Api\Manager $api */
            $api = $this->resource->getServiceLocator()->get('Omeka\ApiManager');
            $defaultSiteId = $this->settings->get('default_site');
            if ($defaultSiteId) {
                try {
                    $this->_storage['site'] = $api->read('sites', ['id' => $defaultSiteId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $this->_storage['site'] = null;
                }
            } else {
                $sites = $api->search('sites', ['limit' => 1, 'sort_by' => 'id'])->getContent();
                $this->_storage['site'] = reset($sites) ?: null;
            }
        }
        return $this->_storage['site'];
    }
}
