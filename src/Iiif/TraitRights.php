<?php declare(strict_types=1);

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

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

trait TraitRights
{
    /**
     * List of allowed urls for rights for api 3.
     *
     * Creative commons machine readable statements are http, but the https are
     * provided too for compatibility with real data and human display.
     * @link https://iiif.io/api/presentation/3.0/#31-descriptive-properties
     *
     * @var array
     */
    protected $rightUrls = [
        'http://creativecommons.org/',
        'https://creativecommons.org/',
        'http://rightsstatements.org/',
        'https://rightsstatements.org/',
        // Other uris are allowed only by iiif extensions.
    ];

    /**
     * Get the license of the resource.
     *
     * Warning: the option for Iiif Server (manifest) and Image Server (info.json)
     * are different, so they can be used independantly.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return string|null
     */
    public function getRights()
    {
        // For simplicity for info.json, use another method.
        return $this->rightsResource($this->resource);
    }

    /**
     * This method can be used without resource, in case of a list.
     *
     * @todo Add a way to manage image server settings.
     * Note: in api 2, the value can be a list.
     *
     * @param AbstractResourceEntityRepresentation|null $resource
     * @return string|null
     */
    protected function rightsResource(AbstractResourceEntityRepresentation $resource = null)
    {
        $url = null;
        $orUrl = false;
        $orText = false;

        $param = $this->setting->__invoke('iiifserver_manifest_rights');
        switch ($param) {
            case 'text':
                if ($this->getContext() === 'http://iiif.io/api/presentation/3/context.json') {
                    return null;
                }
                $url = $this->setting->__invoke('iiifserver_manifest_rights_text');
                break;
            case 'url':
                $url = $this->setting->__invoke('iiifserver_manifest_rights_url');
                break;
            case 'property_or_text':
                $orText = !empty($this->setting->__invoke('iiifserver_manifest_rights_text'));
                // no break.
            case 'property_or_url':
                if ($param === 'property_or_url') {
                    $orUrl = true;
                }
                // no break.
            case 'property':
                if ($resource) {
                    $property = $this->setting->__invoke('iiifserver_manifest_rights_property');
                    $url = (string) $resource->value($property);
                }
                break;
            case 'none':
            default:
                return null;
        }

        // Text is not allowed for presentation 3.
        $isPresentation3 = $this->getContext() === 'http://iiif.io/api/presentation/3/context.json';
        $orText = $orText && !$isPresentation3;

        if (!$url) {
            if ($orUrl) {
                $url = $this->setting->__invoke('iiifserver_manifest_rights_url');
            } elseif ($orText) {
                $url = $this->setting->__invoke('iiifserver_manifest_rights_text');
            } else {
                return null;
            }
        }

        if ($isPresentation3 && $url) {
            foreach ($this->rightUrls as $rightUrl) {
                if (strpos($url, $rightUrl) === 0) {
                    return $url;
                }
            }
        }

        return null;
    }
}
