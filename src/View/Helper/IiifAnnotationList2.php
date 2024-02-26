<?php declare(strict_types=1);

/*
 * Copyright 2015-2024 Daniel Berthereau
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

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\MediaRepresentation;

class IiifAnnotationList2 extends AbstractHelper
{
    /**
     * @var string
     */
    protected $_baseUrl;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Api\Representation\MediaRepresentation
     */
    protected $resource;

    /**
     * Get the IIIF annotation list for a media from Annotate/Cartography.
     *
     * Adapted from the mirador example: https://iiif.harvardartmuseums.org/manifests/object/299843/list/47174896
     *
     * @todo Factorize IiifAnnotationList2, IiifManifest2 and AnnotationList.
     *
     * @see \IiifServer\Iiif\AnnotationList::initAnnotationList()
     * @see \IiifServer\View\Helper\IiifAnnotationList2
     * @see \IiifServer\View\Helper\IiifAnnotationList3
     * @see \IiifServer\View\Helper\IiifManifest2::otherContentAnnotationList()
     *
     * @param MediaRepresentation $resource
     * @param int|string $index Used to set the standard name of the image.
     * @return object|null
     */
    public function __invoke(MediaRepresentation $resource, $index)
    {
        static $api;
        static $oaHasSelector;

        $media = $resource;

        if ($api === null) {
            $plugins = $this->view->getHelperPluginManager();
            $api = $plugins->has('annotations') ? $plugins->get('api') : false;
            if (!$api) {
                return null;
            }
            $oaHasSelector = $api->searchOne('properties', ['term' => 'oa:hasSelector'])->getContent();
            if (!$oaHasSelector) {
                $api = false;
                return null;
            }
            $oaHasSelector = $oaHasSelector->id();
            $this->logger = $plugins->get('logger')();
        } elseif (!$api) {
            return null;
        }

        // Check if media has at least one annotation via oa:hasSelector to set
        // the reference to the list.
        $annotations = $api->search('annotations', [
            'property' => [[
                'property' => $oaHasSelector,
                'type' => 'res',
                'text' => (string) $media->id(),
            ]],
        ], ['initialize' => false, 'finalize' => false])->getContent();

        if (!$annotations) {
            return null;
        }

        // The base urls for some ids to quick process.

        $item = $media->item();
        $mediaId = $media->id();

        $manifestUrl = $this->view->iiifUrl($item, 'iiifserver/manifest', '2');

        $prefixIndex = (string) (int) $index === (string) $index ? 'p' : '';
        $baseCanvasUrl = $this->view->iiifUrl($item, 'iiifserver/uri', '2', [
            'type' => 'canvas',
            'name' => $prefixIndex ? $prefixIndex . $index : $mediaId,
        ]);

        $baseAnnotationUrl = $this->view->iiifUrl($item, 'iiifserver/uri', '2', [
            'type' => 'annotation-list',
            'name' => $mediaId,
            'subtype' => 'annotation',
        ]);

        $annotationList = [
            '@context' => 'http://www.shared-canvas.org/ns/context.json',
            '@id' => $baseAnnotationUrl,
            '@type' => 'sc:AnnotationList',
            'resources' => [],
        ];

        /** @var \Annotate\Api\Representation\AnnotationRepresentation[] $annotations */
        foreach ($annotations as $annotation) {
            $motivations = $annotation->value('oa:motivatedBy', ['all' => true]);
            $iiifMotivations = [];
            if ($motivations) {
                foreach ($motivations as $motivation) {
                    $val = (string) $motivation->value();
                    if ($val && $val !== 'undefined') {
                        $iiifMotivations[] = $val;
                    }
                }
            }
            if (!$iiifMotivations) {
                $iiifMotivations = ['oa:commenting'];
            }

            $iiifBody = [];
            foreach ($annotation->bodies() as $body) {
                $bodyValues = $body->value('rdf:value', ['all' => true]);
                foreach ($bodyValues as $bodyValue) {
                    $bodyValueType = $bodyValue->type();
                    $iiifBody[] = [
                        '@type' => 'dctypes:Text',
                        'format' => in_array($bodyValueType, ['html', 'uri']) ? 'text/html' : 'text/plain',
                        'chars' => (string) $bodyValue,
                    ];
                }
            }

            $iiifAnnotation = [
                '@context' => 'http://iiif.io/api/presentation/2/context.json',
                '@id' => $baseAnnotationUrl . '/annotation/' . $annotation->id(),
                '@type' => 'oa:Annotation',
                'motivation' => $iiifMotivations,
                'resource' => $iiifBody,
                'on' => [
                    '@type' => 'oa:SpecificResource',
                    'full' => $baseCanvasUrl,
                    'selector' => [
                        '@type' => 'oa:FragmentSelector',
                        'value' => 'xywh=451,421,1018,1187',
                    ],
                    'within' => [
                        '@id' => $manifestUrl,
                        '@type' => 'sc:Manifest',
                    ],
                ],
            ];

            $annotationList['resources'][] = $iiifAnnotation;
        }

        if (!count($annotationList['resources'])) {
            return null;
        }

        return $annotationList;
    }
}
