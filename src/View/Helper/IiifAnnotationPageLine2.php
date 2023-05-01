<?php declare(strict_types=1);

/*
 * Copyright 2015-2023 Daniel Berthereau
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

use IiifServer\Iiif\TraitXml;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\MediaRepresentation;
use IiifServer\Iiif\TraitMediaRelated;

class IiifAnnotationPageLine2 extends AbstractHelper
{
    use TraitMediaRelated;
    use TraitXml;

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
     * Get the IIIF canvas for the specified resource.
     *
     * @todo Factorize IiifAnnotationPageLine2, IiifManifest2 and AnnotationPage.
     *
     * @see \IiifServer\Iiif\AnnotationPage::initAnnotationPage()
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine2
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine3
     * @see \IiifServer\View\Helper\IiifManifest2::otherContent()
     * @see \IiifServer\View\Helper\IiifManifest2::relatedMediaOcr()
     *
     * @param MediaRepresentation $resource
     * @param int|string $index Used to set the standard name of the image.
     * @return object|null
     */
    public function __invoke(MediaRepresentation $resource, $index)
    {
        $view = $this->view;
        $plugins = $view->getHelperPluginManager();
        $this->logger = $plugins->get('logger')();

        $relatedMedia = $this->relatedMediaOcr($resource, $index);
        if (!$relatedMedia) {
            return null;
        }

        $this->resource = $resource;
        $this->initTraitXml();

        $xml = $this->loadXml($relatedMedia);
        if (!$xml) {
            return null;
        }

        $callingResource = $resource;
        $callingResourceId = $callingResource->id();

        $item = $callingResource->item();

        // The base urls for some ids to quick process.

        $prefixIndex = (string) (int) $index === (string) $index ? 'p' : '';
        $baseCanvasUrl = $view->iiifUrl($item, 'iiifserver/uri', '2', [
            'type' => 'canvas',
            'name' => $prefixIndex ? $prefixIndex . $index : $callingResourceId,
        ]);

        $baseAnnotationUrl = $view->iiifUrl($item, 'iiifserver/uri', '2', [
            'type' => 'annotation-page',
            'name' => $callingResourceId,
            'subtype' => 'line',
        ]);

        $annotationPage = [];
        $annotationPage['@context'] = 'http://iiif.io/api/presentation/2/context.json';
        $annotationPage['@id'] = $baseAnnotationUrl;
        $annotationPage['@type'] = 'sc:AnnotationList';
        $annotationPage['resources'] = [];

        $namespaces = $xml->getDocNamespaces();
        $altoNamespace = $namespaces['alto'] ?? $namespaces[''] ?? 'http://www.loc.gov/standards/alto/ns-v4#';
        $xml->registerXPathNamespace('alto', $altoNamespace);

        $indexAnnotationLine = 0;
        foreach ($xml->xpath('/alto:alto/alto:Layout//alto:TextLine') as $xmlTextLine) {
            $attributes = $xmlTextLine->attributes();
            $zone = [];
            $zone['left'] = (int) @$attributes->HPOS;
            $zone['top'] = (int) @$attributes->VPOS;
            $zone['width'] = (int) @$attributes->WIDTH;
            $zone['height'] = (int) @$attributes->HEIGHT;
            $value = '';
            /** @var \SimpleXMLElement $xmlString */
            foreach ($xmlTextLine->children() as $xmlString) {
                if ($xmlString->getName() === 'String') {
                    $attributes = $xmlString->attributes();
                    $value .= (string) @$attributes->CONTENT . ' ';
                }
            }
            $value = trim($value);
            if (!strlen($value)) {
                continue;
            }

            $annotation = [
                '@id' => $baseAnnotationUrl . '/l' . ++$indexAnnotationLine,
                '@type' => 'oa:Annotation',
                'motivation' => 'sc:painting',
                'resource' => [
                    '@type' => 'cnt:ContentAsText',
                    'format' => 'text/plain',
                    'chars' => $value,
                ],
                'on' => $baseCanvasUrl . '#xywh=' . implode(',', $zone),
            ];

            $annotationPage['resources'][] = $annotation;
        }

        if (!count($annotationPage['resources'])) {
            return null;
        }

        return $annotationPage;
    }
}
