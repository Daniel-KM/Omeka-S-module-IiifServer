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

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

trait TraitRights
{
    /**
     * List of allowed urls for rights.
     *
     * @var array
     */
    protected $rightUrls = [
        'https://creativecommons.org/',
        // For cross domain issues, allows https for right statements, but the
        // right statement is http.
        'http://rightsstatements.org/',
        'https://rightsstatements.org/',
    ];

    /**
     * @return string|null
     */
    public function getRights()
    {
        // For simplicity for info.json, use another method.
        return $this->rightsResource($this->resource);
    }

    protected function rightsResource(AbstractResourceEntityRepresentation $resource)
    {
        $helper = $this->setting;
        $url = null;
        $orUrl = false;

        $param = $helper('iiifserver_manifest_rights');
        switch ($param) {
            case 'url':
            case 'text':
                $url = $helper('iiifserver_info_rights_url');
                break;
            case 'property_or_url':
            case 'property_or_text':
                $orUrl = true;
                // no break.
            case 'property':
                $property = $helper('iiifserver_info_rights_property');
                $url = (string) $resource->value($property);
                break;
            case 'none':
            default:
                return null;
        }

        if (!$url && $orUrl) {
            $url = $helper('iiifserver_info_rights_url');
        }

        if ($url) {
            foreach ($this->rightUrls as $rightUrl) {
                if (strpos($url, $rightUrl) === 0) {
                    return $url;
                }
            }
        }

        return null;
    }
}
