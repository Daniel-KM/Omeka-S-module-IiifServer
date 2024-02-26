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
 * Manage the IIIF resource types (iiif v3.0).
 *
 * @author Daniel Berthereau
 */
abstract class AbstractResourceType extends AbstractType
{
    use TraitDescriptiveLabel;

    /**
     * Ordered list of properties associated with requirements for the type.
     *
     * This is an abstract class, so nothing is allowed.
     * @see https://iiif.io/api/presentation/3.0/#a-summary-of-property-requirements
     *
     * @var array
     */
    protected $propertyRequirements = [
        '@context' => self::NOT_ALLOWED,

        'id' => self::NOT_ALLOWED,
        'type' => self::NOT_ALLOWED,

        // Descriptive and rights properties.
        'label' => self::NOT_ALLOWED,
        'metadata' => self::NOT_ALLOWED,
        'summary' => self::NOT_ALLOWED,
        'requiredStatement' => self::NOT_ALLOWED,
        'rights' => self::NOT_ALLOWED,
        'navDate' => self::NOT_ALLOWED,
        'language' => self::NOT_ALLOWED,
        'provider' => self::NOT_ALLOWED,
        'thumbnail' => self::NOT_ALLOWED,
        'placeholderCanvas' => self::NOT_ALLOWED,
        'accompanyingCanvas' => self::NOT_ALLOWED,

        // Technical properties.
        // Id and type are set above to avoid ordering by default.
        // 'id' => self::NOT_ALLOWED,
        // 'type' => self::NOT_ALLOWED,
        'format' => self::NOT_ALLOWED,
        'profile' => self::NOT_ALLOWED,
        'height' => self::NOT_ALLOWED,
        'width' => self::NOT_ALLOWED,
        'duration' => self::NOT_ALLOWED,
        'viewingDirection' => self::NOT_ALLOWED,
        'behavior' => self::NOT_ALLOWED,
        'timeMode' => self::NOT_ALLOWED,

        // Linking properties.
        // External linking.
        'seeAlso' => self::NOT_ALLOWED,
        'service' => self::NOT_ALLOWED,
        'homepage' => self::NOT_ALLOWED,
        'logo' => self::NOT_ALLOWED,
        'rendering' => self::NOT_ALLOWED,
        // Internal linking.
        'partOf' => self::NOT_ALLOWED,
        'start' => self::NOT_ALLOWED,
        'supplementary' => self::NOT_ALLOWED,
        'services' => self::NOT_ALLOWED,

        // Structural properties.
        'items' => self::NOT_ALLOWED,
        'structures' => self::NOT_ALLOWED,
        'annotations' => self::NOT_ALLOWED,
    ];

    /**
     * Behavior values.
     *
     * @var array
     */
    protected $behaviors = [
        // Temporal behaviors.
        'auto-advance' => self::NOT_ALLOWED,
        'no-auto-advance' => self::NOT_ALLOWED,
        'repeat' => self::NOT_ALLOWED,
        'no-repeat' => self::NOT_ALLOWED,
        // Layout behaviors.
        'unordered' => self::NOT_ALLOWED,
        'individuals' => self::NOT_ALLOWED,
        'continuous' => self::NOT_ALLOWED,
        'paged' => self::NOT_ALLOWED,
        'facing-pages' => self::NOT_ALLOWED,
        'non-paged' => self::NOT_ALLOWED,
        // Collection behaviors.
        'multi-part' => self::NOT_ALLOWED,
        'together' => self::NOT_ALLOWED,
        // Range behaviors.
        'sequence' => self::NOT_ALLOWED,
        'thumbnail-nav' => self::NOT_ALLOWED,
        'no-nav' => self::NOT_ALLOWED,
        // Miscellaneous behaviors.
        'hidden' => self::NOT_ALLOWED,
    ];

    /**
     * List of IIIF types.
     *
     * Currently not used.
     * @todo Use AbstractResourceType only for the main iiif types. Other may derivate to get the init.
     *
     * The content is the body or the textual body.
     *
     * @see https://iiif.io/api/presentation/3.0/#2-resource-type-overview
     *
     * @var array
     */
    protected $iiifTypes = [
        // Defined types.
        Collection::class => 'Collection',
        Manifest::class => 'Manifest',
        Canvas::class => 'Canvas',
        Range::class => 'Range',
        // Selected additionnal types from Web Annotation Data Model.
        AnnotationPage::class => 'AnnotationPage',
        Annotation::class => 'Annotation',
        ContentResource::class => 'Content',
        AnnotationCollection::class => 'AnnotationCollection',
    ];

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Omeka\Api\Representation\SiteRepresentation|null
     */
    protected $defaultSite;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\FixUtf8
     */
    protected $fixUtf8;

    /**
     * @var bool
     */
    protected $hasModuleAccess;

    /**
     * @var bool
     */
    protected $hasModuleImageServer;

    /**
     * @var \IiifServer\View\Helper\IiifCleanIdentifiers
     */
    protected $iiifCleanIdentifiers;

    /**
     * @var string
     */
    protected $iiifImageApiVersion;

    /**
     * @var array
     */
    protected $iiifImageApiSupportedVersions;

    /**
     * @var \IiifServer\View\Helper\IiifMediaUrl
     */
    protected $iiifMediaUrl;

    /**
     * @var \IiifServer\View\Helper\IiifTileInfo
     */
    protected $iiifTileInfo;

    /**
     * @var \IiifServer\View\Helper\IiifTypeOfMedia
     */
    protected $iiifTypeOfMedia;

    /**
     * @var \IiifServer\View\Helper\IiifUrl
     */
    protected $iiifUrl;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\ImageSize
     */
    protected $imageSize;

    /**
     * @var \Access\Mvc\Controller\Plugin\IsAllowedMediaContent
     */
    protected $isAllowedMediaContent;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \IiifServer\View\Helper\MediaDimension
     */
    protected $mediaDimension;

    /**
     * @var \IiifServer\View\Helper\PublicResourceUrl
     */
    protected $publicResourceUrl;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\RangeToArray
     */
    protected $rangeToArray;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * Warning: the site id should be set.
     *
     * @var \Omeka\Settings\SiteSettings
     */
    protected $siteSettings;

    /**
     * @var \ImageServer\Mvc\Controller\Plugin\TileMediaInfo|null
     */
    protected $tileMediaInfo;

    /**
     * @var \Common\I18n\Translator
     */
    protected $translator;

    /**
     * @var \Laminas\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * @var string
     */
    protected $xmlFixMode;

    /**
     * @var AbstractResourceEntityRepresentation
     */
    protected $resource;

    /**
     * @var array
     */
    protected $cache = [];

    public function setResource(AbstractResourceEntityRepresentation $resource): self
    {
        $this->resource = $resource;
        $this->services = $resource->getServiceLocator();

        $config = $this->services->get('Config');
        $plugins = $this->services->get('ControllerPluginManager');
        $viewHelpers = $this->services->get('ViewHelperManager');

        $this->hasModuleAccess = $plugins->has('isAllowedMediaContent');
        $this->hasModuleImageServer = $plugins->has('tileMediaInfo');

        $this->api = $this->services->get('Omeka\ApiManager');
        $this->basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->defaultSite = $viewHelpers->get('defaultSite')();
        $this->easyMeta = $this->services->get('EasyMeta');
        $this->fixUtf8 = $plugins->get('fixUtf8');
        $this->iiifCleanIdentifiers = $viewHelpers->get('iiifCleanIdentifiers');
        $this->iiifMediaUrl = $viewHelpers->get('iiifMediaUrl');
        $this->iiifTileInfo = $viewHelpers->get('iiifTileInfo');
        $this->iiifTypeOfMedia = $viewHelpers->get('iiifTypeOfMedia');
        $this->iiifUrl = $viewHelpers->get('iiifUrl');
        $this->imageSize = $plugins->get('imageSize');
        $this->isAllowedMediaContent = $this->hasModuleAccess ? $plugins->get('isAllowedMediaContent') : null;
        $this->logger = $this->services->get('Omeka\Logger');
        $this->mediaDimension = $viewHelpers->get('mediaDimension');
        $this->publicResourceUrl = $viewHelpers->get('publicResourceUrl');
        $this->rangeToArray = $plugins->get('rangeToArray');
        $this->settings = $this->services->get('Omeka\Settings');
        $this->siteSettings = $this->services->get('Omeka\Settings\Site');
        $this->tileMediaInfo = $this->hasModuleImageServer ? $plugins->get('tileMediaInfo') : null;
        $this->translator = $this->services->get('MvcTranslator');
        // TODO Use plugin url to simplify call.
        $this->urlHelper = $viewHelpers->get('url');

        $this->iiifImageApiVersion = $this->settings->get('iiifserver_media_api_default_version', '2');
        $this->iiifImageApiSupportedVersions = (array) $this->settings->get('iiifserver_media_api_supported_versions', ['2/2', '3/2']);
        $this->xmlFixMode = $this->settings->get('iiifsearch_xml_fix_mode', 'no');

        return $this;
    }

    /**
     * Get the resource used to create this part of the manifest.
     *
     * It avoids to do reverse engineering to determine it, in particular inside
     * the controller or in the event iiifserver.manifest.
     */
    public function getResource(): AbstractResourceEntityRepresentation
    {
        return $this->resource;
    }

    public function context(): ?string
    {
        return empty($this->options['skip']['@context'])
            ? 'http://iiif.io/api/presentation/3/context.json'
            : null;
    }

    public function id(): ?string
    {
        return null;
    }
}
