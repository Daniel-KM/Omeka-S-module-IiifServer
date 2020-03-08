<?php

/*
 * Copyright 2020 Daniel Berthereau
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

trait TraitLinking
{
    /**
     * @var \IiifServer\View\Helper\ImageSize
     */
    protected $imageSizeHelper;

    public function initLinking()
    {
        $viewHelpers = $this->resource->getServiceLocator()->get('ViewHelperManager');
        $this->imageSizeHelper = $viewHelpers->get('imageSize');
        $this->iiifImageUrl = $viewHelpers->get('iiifImageUrl');
    }

    /**
     * @return \stdClass
     */
    public function getHomepage()
    {
        $output = new \ArrayObject;

        $setting = $this->setting;
        $site = $this->defaultSite();
        if ($site) {
            $siteSettings = $this->resource->getServiceLocator()->get('Omeka\Settings\Site');
            $siteSettings->setTargetId($site->id());
            $language = $siteSettings->get('locale') ?: ($setting('locale') ?: 'none');
        } else {
            $language = $setting('locale') ?: 'none';
        }

        $homePage = $setting('iiifserver_manifest_homepage', 'resource');
        switch ($homePage) {
            case 'none':
                return null;
            case 'site':
                if ($site) {
                    $id = $site->siteUrl($site->slug(), true);
                    $label = new ValueLanguage([$language => [$site->title()]]);
                } else {
                    $helper = $this->urlHelper;
                    $id = $helper('top', [], ['force_canonical' => true]);
                    $label = new ValueLanguage([$language => [$setting('installation_title')]]);
                }
                $output[] = (object) [
                    'id' => $id,
                    'type' => 'Text',
                    'label' => $label,
                    'format' => 'text/html',
                    'language' => [
                        $language,
                    ],
                ];
                break;
            case 'property':
            case 'property_or_resource':
            case 'property_and_resource':
                $property = $setting('iiifserver_manifest_homepage_property');
                /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
                $values = $this->resource->value($property, ['all' => true, 'default' => []]);
                if ($values) {
                    foreach ($values as $value) {
                        $val = $value->uri() ?: $value->value();
                        if (filter_var($val, FILTER_VALIDATE_URL)) {
                            $id = $val;
                            break;
                        }
                    }
                }
                if (isset($id)) {
                    if ($value->type() === 'uri') {
                        $fallback = $value->value() ?: 'Source';
                    } else {
                        $fallback = 'Source';
                    }
                    $label = new ValueLanguage([], false, $fallback);
                    $output[] = (object) [
                        'id' => $id,
                        'type' => 'Text',
                        'label' => $label,
                        'format' => 'text/html',
                        'language' => [
                            $language,
                        ],
                    ];
                    if ($homePage !== 'property_and_resource') {
                        break;
                    }
                } elseif ($homePage === 'property') {
                    return null;
                }
                // no break;
            case 'resource':
            default:
                if ($site) {
                    $id = $this->resource->siteUrl($site->slug(), true);
                } else {
                    $id = $this->resource->apiUrl();
                }
                // displayTitle() can't be used, because language is needed.
                $template = $this->resource->resourceTemplate();
                if ($template && $template->titleProperty()) {
                    $values = $this->resource->value($template->titleProperty()->term(), ['all' => true, 'default' => []]);
                    if (empty($values)) {
                        $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => []]);
                    }
                } else {
                    $values = $this->resource->value('dcterms:title', ['all' => true, 'default' => []]);
                }
                $label = new ValueLanguage($values, false, 'Source');
                $output[] = (object) [
                    'id' => $id,
                    'type' => 'Text',
                    'label' => $label,
                    'format' => 'text/html',
                    'language' => [
                        $language,
                    ],
                ];
                break;
        }

        return $output;
    }

    /**
     * @return \stdClass
     */
    public function getLogo()
    {
        $setting = $this->setting;
        $url = $setting('iiifserver_manifest_logo_default');
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

        $helper = $this->imageSizeHelper;
        try {
            $size = $helper($url);
        } catch (\Exception $e) {
            $size = ['height' => null, 'width' => null];
        }

        $output = [
            'id' => $url,
            'type' => 'Image',
            'format' => @$mediaTypes[$format],
            'height' => $size['height'],
            'width' => $size['width'],
        ];
        return [
            (object) $output,
        ];
    }

    /**
     * @return \stdClass
     */
    public function getSeeAlso()
    {
        $output = new \ArrayObject;

        $setting = $this->setting;
        $property = $setting('iiifserver_manifest_seealso_property');

        /** @var \Omeka\Api\Representation\ValueRepresentation[] $values */
        $values = $this->resource->value($property, ['all' => true, 'default' => []]);
        if ($values) {
            foreach ($values as $value) {
                $id= $value->uri() ?: $value->value();
                if (filter_var($id, FILTER_VALIDATE_URL)) {
                    if ($value->type() === 'uri') {
                        $format = $value->value() ?: 'Dataset';
                    } else {
                        $format = 'Dataset';
                    }
                    $output[] = (object) [
                        'id' => $id,
                        'type' => 'Dataset',
                        'format' => $format,
                    ];
                    break;
                }
            }
        }

        $helper = $this->urlHelper;
        $output[] = (object) [
            'id' => $this->resource->apiUrl(),
            'type' => 'Dataset',
            'format' => 'application/ld+json',
            'profile' => $helper('api-context', [], ['force_canonical' => true]),
        ];

        return $output;
    }

    /**
     * @return \Omeka\Api\Representation\SiteRepresentation|null
     */
    protected function defaultSite()
    {
        if (!array_key_exists('site', $this->_storage)) {
            $api = $this->resource->getServiceLocator()->get('Omeka\ApiManager');
            $setting = $this->setting;
            $defaultSiteId = $setting('default_site');
            if ($defaultSiteId) {
                try {
                    $this->_storage['site'] = $api->read('sites', ['id' => $defaultSiteId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $this->_storage['site'] = null;
                }
            } else {
                $sites = $api->search('sites', ['limit' => 1, 'sort_by' => 'id'])->getContent();
                $this->_storage['site'] = reset($sites);
            }
        }
        return $this->_storage['site'];
    }
}
