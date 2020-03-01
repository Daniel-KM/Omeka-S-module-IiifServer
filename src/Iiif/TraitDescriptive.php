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

trait TraitDescriptive
{
    use TraitRights;

    /**
     * List metadata of the resource.
     *
     * @todo Remove setting iiifserver_manifest_html_descriptive for v3.0.
     * @todo Remove publicResourceUrl for v3.0?
     * @todo Remove some settings for v3.0.
     * @todo Use displayValues() or the event?
     *
     * @return array
     */
    public function getMetadata()
    {
        $jsonLdType = $this->resource->getResourceJsonLdType();
        $map = [
            'o:ItemSet' => 'iiifserver_manifest_properties_collection',
            'o:Item' => 'iiifserver_manifest_properties_item',
            'o:Media' => 'iiifserver_manifest_properties_media',
        ];
        if (!isset($map[$jsonLdType])) {
            return [];
        }

        $settingHelper = $this->setting;
        $properties = $settingHelper($map[$jsonLdType]);
        if ($properties === ['none']) {
            return [];
        }

        $values = $properties
            ? array_intersect_key($this->resource->values(), array_flip($properties))
            : $this->resource->values();

        $metadata = [];
        foreach ($values as $propertyData) {
            $metadataValue = new ValueLanguage($propertyData['values'], true);
            if (!$metadataValue->count()) {
                continue;
            }

            $propertyLabel = $propertyData['alternate_label'] ?: $propertyData['property']->label();
            $labels = [];
            foreach ($metadataValue->langs() as $lang) {
                $labels[$lang][] = $propertyLabel;
            }
            $metadataLabel = new ValueLanguage($labels);

            $metadata[] = (object) [
                'label' => $metadataLabel,
                'value' => $metadataValue,
            ];
        }
        return $metadata;
    }

    /**
     * @return ValueLanguage
     */
    public function getSummary()
    {
        $helper = $this->setting;
        $summaryProperty = $helper('iiifserver_manifest_description_property');
        $values = [];
        if ($summaryProperty) {
            $values = $this->resource->value($summaryProperty, ['all' => true, 'default' => []]);
        }
        return new ValueLanguage($values, true);
    }

    /**
     * @return ValueLanguage[]
     */
    public function getRequiredStatement()
    {
        $helper = $this->setting;

        $license = [];
        $licenseProperty = $helper('iiifserver_manifest_attribution_property');
        if ($licenseProperty) {
            $license = $this->resource->value($licenseProperty, ['all' => true, 'default' => []]);
        }

        if (empty($license)) {
            $default = $helper('iiifserver_manifest_attribution_default');
            if ($default) {
                $license = ['none' => [$default]];
            } else {
                return null;
            }
        }

        $metadataValue = new ValueLanguage($license, true);
        if (!$metadataValue->count()) {
            return $metadataValue;
        }

        $propertyLabel = 'Attribution'; // @translate

        $labels = [];
        foreach ($metadataValue->langs() as $lang) {
            $labels[$lang][] = $propertyLabel;
        }
        $metadataLabel = new ValueLanguage($labels);

        return (object) [
            'label' => $metadataLabel,
            'value' => $metadataValue,
        ];
    }
}
