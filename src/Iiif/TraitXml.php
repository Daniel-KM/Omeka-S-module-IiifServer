<?php declare(strict_types=1);

/*
 * Copyright 2020-2022 Daniel Berthereau
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

use Omeka\Api\Representation\MediaRepresentation;
use SimpleXMLElement;

trait TraitXml
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\FixUtf8
     */
    protected $fixUtf8;

    protected function initBasePath(): self
    {
        $services = $this->resource->getServiceLocator();
        $config = $services->get('Config');
        $this->basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->fixUtf8 = empty($config['iiifserver']['config']['iiifserver_enable_utf8_fix'])
            ? null
            : $services->get('ControllerPluginManager')->get('fixUtf8');
        return $this;
    }

    /**
     * @see \IiifSearch\View\Helper\IiifSearch::loadXml()
     */
    protected function loadXml(MediaRepresentation $media): ?SimpleXMLElement
    {
        $filepath = ($filename = $media->filename())
            ? $this->basePath . '/original/' . $filename
            : $media->originalUrl();

        $xmlContent = file_get_contents($filepath);
        if ($this->fixUtf8) {
            $xmlContent = $this->fixUtf8->__invoke($xmlContent);
        }
        if (!$xmlContent) {
            $this->logger->err(sprintf(
                'Error: XML content seems empty for media #%d!', // @translate
                $media->id()
            ));
            return null;
        }

        // Manage an exception.
        $mediaType = $media->mediaType();
        if ($mediaType === 'application/vnd.pdf2xml+xml') {
            $xmlContent = preg_replace('/\s{2,}/ui', ' ', $xmlContent);
            $xmlContent = preg_replace('/<\/?b>/ui', '', $xmlContent);
            $xmlContent = preg_replace('/<\/?i>/ui', '', $xmlContent);
            $xmlContent = str_replace('<!doctype pdf2xml system "pdf2xml.dtd">', '<!DOCTYPE pdf2xml SYSTEM "pdf2xml.dtd">', $xmlContent);
        }

        $xmlContent = simplexml_load_string($xmlContent);
        if (!$xmlContent) {
            $this->logger->err(sprintf(
                'Error: Cannot get XML content from media #%d!', // @translate
                $media->id()
            ));
            return null;
        }

        return $xmlContent;
    }
 }
