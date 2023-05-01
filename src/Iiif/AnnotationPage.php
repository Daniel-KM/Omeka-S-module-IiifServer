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

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 *@link https://iiif.io/api/presentation/3.0/#55-annotation-page
 */
class AnnotationPage extends AbstractResourceType
{
    use TraitXml;

    protected $type = 'AnnotationPage';

    protected $keys = [
        '@context' => self::NOT_ALLOWED,

        'id' => self::REQUIRED,
        'type' => self::REQUIRED,

        // Descriptive and rights properties.
        // To fix an issue with Open Annotation, the label is forbidden.
        // @link https://iiif.io/api/presentation/3.0/#55-annotation-page
        // 'label' => self::OPTIONAL,
        'label' => self::NOT_ALLOWED,
        'metadata' => self::OPTIONAL,
        'summary' => self::OPTIONAL,
        'requiredStatement' => self::OPTIONAL,
        'rights' => self::OPTIONAL,
        'navDate' => self::NOT_ALLOWED,
        'language' => self::NOT_ALLOWED,
        'provider' => self::OPTIONAL,
        'thumbnail' => self::OPTIONAL,
        'placeholderCanvas' => self::NOT_ALLOWED,
        'accompanyingCanvas' => self::NOT_ALLOWED,

        // Technical properties.
        // 'id' => self::REQUIRED,
        // 'type' => self::REQUIRED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        'height' => self::NOT_ALLOWED,
        'width' => self::NOT_ALLOWED,
        'duration' => self::NOT_ALLOWED,
        'viewingDirection' => self::NOT_ALLOWED,
        'behavior' => self::OPTIONAL,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        'seeAlso' => self::OPTIONAL,
        'service' => self::OPTIONAL,
        'homepage' => self::OPTIONAL,
        'rendering' => self::OPTIONAL,
        'partOf' => self::OPTIONAL,
        'start' => self::NOT_ALLOWED,
        'supplementary' => self::NOT_ALLOWED,
        'services' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::RECOMMENDED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::NOT_ALLOWED,
    ];

    protected $behaviors = [
        'hidden' => self::OPTIONAL,
    ];

    protected $callingResource;

    protected $callingMotivation;

    protected $dereferenced = false;

    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = null)
    {
        parent::__construct($resource, $options);
        $this->callingResource = $options['callingResource'] ?? null;
        $this->callingMotivation = $options['callingMotivation'] ?? null;
        $this->dereferenced = !empty($options['dereferenced']);
        if ($this->dereferenced) {
            $this->keys['@context'] = self::REQUIRED;
        }
        if ($this->callingResource && $this->callingMotivation === 'annotation') {
            $this->initAnnotationPage();
        }
    }

    public function id(): ?string
    {
        if ($this->callingMotivation !== 'painting') {
            return $this->_storage['id'] ?? null;
        }
        return $this->iiifUrl->__invoke($this->resource->item(), 'iiifserver/uri', '3', [
            'type' => 'annotation-page',
            'name' => $this->resource->id(),
        ]);
    }

    public function label(): ?ValueLanguage
    {
        if ($this->callingMotivation === 'painting') {
            return parent::label();
        }
        return isset($this->_storage['label'])
            ? new ValueLanguage(['none' => [$this->_storage['label']]])
            : null;
    }

    /**
     * @todo Canvas multiple items.
     *
     * There is only one file by canvas for now: one item = one document.
     *
     * The canvas can have multiple items, for example when a page is composed
     * of fragments.
     */
    public function items(): ?array
    {
        if ($this->callingMotivation === 'annotation') {
            return $this->_storage['items'] ?? null;
        }

        $item = new Annotation($this->resource, $this->options);
        return [$item];
    }

    /**
     * Prepare annotation page.
     *
     * Only alto is managed for now to create AnnotationLine.
     *
     * Here, the canvas contains a supported image/audio/video to be displayed,
     * no xml, pdf, etc. These other files can be attached to the displayable
     * media.
     *
     * There are two ways to make a relation between two media: use a property
     * with a linked media or use the same basename from the original source.
     *
     * @todo Merge with SeeAlso?
     */
    protected function initAnnotationPage(): AbstractType
    {
        if (empty($this->callingResource)) {
            return $this;
        }

        $callingResourceId = $this->callingResource->id();

        if ($this->resource->id() === $callingResourceId) {
            return $this;
        }

        $callingResourceBasename = pathinfo((string) $this->callingResource->source(), PATHINFO_FILENAME);
        if (!$callingResourceBasename) {
            return $this;
        }

        $resourceBasename = pathinfo((string) $this->resource->source(), PATHINFO_FILENAME);
        if ($resourceBasename !== $callingResourceBasename) {
            return $this;
        }

        $mediaType = $this->resource->mediaType();
        if ($mediaType !== 'application/alto+xml') {
            return $this;
        }

        $this->_storage['id'] = $this->iiifUrl->__invoke($this->resource->item(), 'iiifserver/uri', '3', [
            'type' => 'annotation-page',
            'name' => $callingResourceId,
            'subtype' => 'line',
        ]);
        $this->_storage['type'] = $this->type;
        $this->_storage['label'] = 'Text of the current page'; // @translate
        $this->_storage['items'] = [];
        if ($this->dereferenced) {
            $this->initAnnotationPageLines();
        }

        if (!count($this->_storage['items'])) {
            $this->_storage = [];
        }

        return $this;
    }

    /**
     * Extract lines of an ocr.
     *
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine2
     */
    protected function initAnnotationPageLines(): AbstractType
    {
        $this->_storage['items'] = [];

        $this->initTraitXml();

        $xml = $this->loadXml($this->resource);
        if (!$xml) {
            return $this;
        }

        $namespaces = $xml->getDocNamespaces();
        $altoNamespace = $namespaces['alto'] ?? $namespaces[''] ?? 'http://www.loc.gov/standards/alto/ns-v4#';
        $xml->registerXPathNamespace('alto', $altoNamespace);

        $opts = [];
        $opts['callingResource'] = $this->callingResource;
        $opts['motivation'] = 'supplementing';
        $opts['body'] = 'TextualBody';
        $opts['target_name'] = $this->callingResource->id();
        $index = 0;
        foreach ($xml->xpath('/alto:alto/alto:Layout//alto:TextLine') as $xmlTextLine) {
            $attributes = $xmlTextLine->attributes();
            $zone = [];
            $zone['left'] = (int) @$attributes->HPOS;
            $zone['top'] = (int) @$attributes->VPOS;
            $zone['width'] = (int) @$attributes->WIDTH;
            $zone['height'] = (int) @$attributes->HEIGHT;
            $opts['target_fragment'] = 'xywh=' . implode(',', $zone);
            $value = '';
            /** @var \SimpleXMLElement $xmlString */
            foreach ($xmlTextLine->children() as $xmlString) {
                if ($xmlString->getName() === 'String') {
                    $attributes = $xmlString->attributes();
                    $value .= (string) $attributes->CONTENT . ' ';
                }
            }
            $opts['value'] = trim($value);
            if (!strlen($opts['value'])) {
                continue;
            }
            $opts['index'] = ++$index;
            $this->_storage['items'][] = new Annotation($this->resource, $opts);
        }

        return $this;
    }
}
