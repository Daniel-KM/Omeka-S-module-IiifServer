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
        $site = $this->defaultSite();
        $setting = $this->setting;
        if ($site) {
            $id = $site->siteUrl($site->slug(), true);
            $label = $site->title();
            $siteSettings = $this->resource->getServiceLocator()->get('Omeka\Settings\Site');
            $siteSettings->setTargetId($site->id());
            $language = $siteSettings->get('locale') ?: ($setting('locale') ?: 'none');
        } else {
            $helper = $this->urlHelper;
            $id = $helper('top', [], ['force_canonical' => true]);
            $label = $setting('installation_title');
            $language = $setting('locale') ?: 'none';
        }

        $output = [
            'id' => $id,
            'type' => 'Text',
            'label' => new ValueLanguage([$language => [$label]]),
            'format' => 'text/html',
            'language' => [
                $language,
            ],
        ];

        return [
            (object) $output
        ];
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
        $helper = $this->urlHelper;
        $output = [
            'id' => $this->resource->apiUrl(),
            'type' => 'Dataset',
            'format' => 'application/ld+json',
            'profile' => $helper('api-context', [], ['force_canonical' => true]),
        ];
        return [
            (object) $output,
        ];
    }

    /**
     * @return \stdClass
     */
    public function getRendering()
    {
        $renderings = [];
        $site = $this->defaultSite();
        $siteSlug = $site ? $site->slug() : null;
        foreach ($this->resource->media() as $media) {
            if (strtok($media->mediaType(), '/') !== 'image') {
                $rendering = new Rendering($media, [
                    'index' => $media->id(),
                    'siteSlug' => $siteSlug,
                ]);
                if ($rendering->getId() && $rendering->getType()) {
                    $renderings[] = $rendering;
                }
            }
        }
        return $renderings;
    }

    /**
     * @return \Omeka\Api\Representation\SiteRepresentation|null
     */
    protected function defaultSite()
    {
        static $site;

        if (is_null($site)) {
            $api = $this->resource->getServiceLocator()->get('Omeka\ApiManager');
            $setting = $this->setting;
            $defaultSiteId = $setting('default_site');
            if ($defaultSiteId) {
                try {
                    $site = $api->read('sites', ['id' => $defaultSiteId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $site = false;
                }
            } else {
                $sites = $api->search('sites', ['limit' => 1, 'sort_by' => 'id'])->getContent();
                $site = reset($sites);
            }
        }

        return $site ?: null;
    }
}
