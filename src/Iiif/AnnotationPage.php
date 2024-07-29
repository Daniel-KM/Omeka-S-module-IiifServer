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

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 *@link https://iiif.io/api/presentation/3.0/#55-annotation-page
 */
class AnnotationPage extends AbstractResourceType
{
    use TraitXml;

    protected $type = 'AnnotationPage';

    protected $propertyRequirements = [
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
        'logo' => self::OPTIONAL,
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
        // // Temporal behaviors.
        // 'auto-advance' => self::NOT_ALLOWED,
        // 'no-auto-advance' => self::NOT_ALLOWED,
        // 'repeat' => self::NOT_ALLOWED,
        // 'no-repeat' => self::NOT_ALLOWED,
        // // Layout behaviors.
        // 'unordered' => self::NOT_ALLOWED,
        // 'individuals' => self::NOT_ALLOWED,
        // 'continuous' => self::NOT_ALLOWED,
        // 'paged' => self::NOT_ALLOWED,
        // 'facing-pages' => self::NOT_ALLOWED,
        // 'non-paged' => self::NOT_ALLOWED,
        // // Collection behaviors.
        // 'multi-part' => self::NOT_ALLOWED,
        // 'together' => self::NOT_ALLOWED,
        // // Range behaviors.
        // 'sequence' => self::NOT_ALLOWED,
        // 'thumbnail-nav' => self::NOT_ALLOWED,
        // 'no-nav' => self::NOT_ALLOWED,
        // Miscellaneous behaviors.
        'hidden' => self::OPTIONAL,
    ];

    protected $callingResource;

    protected $callingMotivation;

    protected $isDereferenced = false;

    public function setOptions(array $options): self
    {
        parent::setOptions($options);

        $this->callingResource = $options['callingResource'] ?? null;
        $this->callingMotivation = $options['callingMotivation'] ?? null;
        $this->isDereferenced = !empty($options['isDereferenced']);
        if ($this->isDereferenced) {
            $this->propertyRequirements['@context'] = self::REQUIRED;
        }

        return $this;
    }

    public function setResource(AbstractResourceEntityRepresentation $resource): self
    {
        parent::setResource($resource);

        // Xml is used only for annotation.
        if ($this->callingMotivation === 'annotation') {
            $this->initAnnotationPage();
        // } elseif ($this->callingMotivation === 'painting') {
        }

        return $this;
    }

    public function id(): ?string
    {
        if (array_key_exists('id', $this->cache)) {
            return $this->cache['id'];
        }
        if ($this->callingMotivation !== 'painting') {
            return null;
        }
        // Here, the resource is a media.
        $this->cache['id'] = $this->iiifUrl->__invoke($this->resource->item(), 'iiifserver/uri', '3', [
            'type' => 'annotation-page',
            'name' => $this->resource->id(),
        ]);
        return $this->cache['id'];
    }

    public function label(): ?array
    {
        if ($this->callingMotivation === 'painting') {
            return parent::label();
        }
        return $this->cache['label'] ?? null;
    }

    /**
     * @todo Canvas multiple items.
     *
     * There is only one file by canvas for now: one item = one document.
     *
     * The canvas can have multiple items, for example when a page is composed
     * of fragments.
     */
    public function items(): array
    {
        if (array_key_exists('items', $this->cache)) {
            return $this->cache['items'];
        }

        if ($this->callingMotivation === 'annotation') {
            $this->cache['items'] = [];
            return $this->cache['items'];
        }

        $item = new Annotation();
        $item
            ->setOptions($this->options)
            ->setResource($this->resource);

        $this->cache['items'] = [
            $item,
        ];
        return $this->cache['items'];
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
     *
     * @todo Factorize IiifAnnotationPageLine2, IiifManifest2 and AnnotationPage.
     *
     * @see \IiifServer\Iiif\AnnotationPage::initAnnotationPage()
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine2
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine3
     * @see \IiifServer\View\Helper\IiifManifest2::otherContent()
     * @see \IiifServer\View\Helper\IiifManifest2::relatedMediaOcr()
     */
    protected function initAnnotationPage(): self
    {
        if (empty($this->callingResource)) {
            return $this;
        }

        if (!empty($this->options['useExtraFiles'])) {
            $filepath = $this->options['mediaInfos']['extraFiles']['alto'] ?? null;
            $imageNumber = $this->options['mediaInfos']['indexes'][$this->resource->id()] ?? null;
            if (!$filepath || !$imageNumber) {
                return $this;
            }
        } else {
            $relatedMedia = $this->iiifMediaRelatedOcr->__invoke($this->callingResource, null);
            if (!$relatedMedia) {
                return $this;
            }
            $filepath = null;
            $imageNumber = null;
        }

        $callingResourceId = $this->callingResource->id();

        // Here, the resource is a media.
        $this->cache['id'] = $this->iiifUrl->__invoke($this->resource->item(), 'iiifserver/uri', '3', [
            'type' => 'annotation-page',
            'name' => $callingResourceId,
            'subtype' => 'line',
        ]);
        $this->cache['type'] = $this->type;
        $this->cache['label'] = ValueLanguage::output([
            'none' => ['Text of the current page'], // @translate
        ]);
        $this->cache['items'] = [];
        if ($this->isDereferenced) {
            $this->initAnnotationPageLines($filepath, $imageNumber);
        }

        if (!count($this->cache['items'])) {
            $this->cache = [];
        }

        return $this;
    }

    /**
     * Extract lines of an ocr.
     *
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine2
     */
    protected function initAnnotationPageLines(?string $filepath = null, ?int $imageNumber = null): self
    {
        $this->cache['items'] = [];

        $xml = $filepath
            ? @simplexml_load_file($filepath)
            : $this->loadXml($this->resource);
        if (!$xml) {
            return $this;
        }

        $namespaces = $xml->getDocNamespaces();
        $altoNamespace = $namespaces['alto'] ?? $namespaces[''] ?? 'http://www.loc.gov/standards/alto/ns-v4#';
        $xml->registerXPathNamespace('alto', $altoNamespace);

        // Check the size of the page stored in alto and the real size of image.
        // With common tools, it may be 300 / 108.
        // It allows to fix refactored images and alto extracted from pdf via
        // the module Extract Ocr.
        [$widthImage, $heightImage] = array_values($this->imageSize->__invoke($this->callingResource));
        $xpath = $imageNumber
            ? "/alto:alto/alto:Layout/alto:Page[@PHYSICAL_IMG_NR='$imageNumber']/@WIDTH"
            : '/alto:alto/alto:Layout/alto:Page/@WIDTH';
        $widthLayout = $xml->xpath($xpath);
        $widthLayout = (string) reset($widthLayout);
        $xpath = $imageNumber
            ? "/alto:alto/alto:Layout/alto:Page[@PHYSICAL_IMG_NR='$imageNumber']/@HEIGHT"
            : '/alto:alto/alto:Layout/alto:Page/@HEIGHT';
        $heightLayout = $xml->xpath($xpath);
        $heightLayout = (string) reset($heightLayout);
        if ($widthImage && $heightImage && $widthLayout && $heightLayout) {
            $widthCoef = $widthImage / $widthLayout;
            $heightCoef = $heightImage / $heightLayout;
        } else {
            $widthCoef = 1;
            $heightCoef = 1;
        }

        $opts = [];
        $opts['callingResource'] = $this->callingResource;
        $opts['motivation'] = 'supplementing';
        $opts['body'] = 'TextualBody';
        $opts['target_name'] = $this->callingResource->id();

        $index = 0;
        $xpath = $imageNumber
            ? "/alto:alto/alto:Layout/alto:Page[@PHYSICAL_IMG_NR='$imageNumber']//alto:TextLine"
            : '/alto:alto/alto:Layout//alto:TextLine';

        foreach ($xml->xpath($xpath) as $xmlTextLine) {
            // TODO Add a coefficient when text is extracted from pdf, not images.
            $attributes = $xmlTextLine->attributes();
            $zone = [];
            $zone['left'] = (int) (@$attributes->HPOS * $widthCoef);
            $zone['top'] = (int) (@$attributes->VPOS * $widthCoef);
            $zone['width'] = (int) (@$attributes->WIDTH * $heightCoef);
            $zone['height'] = (int) (@$attributes->HEIGHT * $heightCoef);
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
            $item = new Annotation();
            $item
                ->setOptions($opts)
                ->setResource($this->resource);
            $this->cache['items'][] = $item;
        }

        return $this;
    }
}
