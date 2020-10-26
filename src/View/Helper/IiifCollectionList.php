<?php declare(strict_types=1);

/*
 * Copyright 2015-2020 Daniel Berthereau
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

/**
 * Helper to get a IIIF Collection manifest for a dynamic list.
 */
class IiifCollectionList extends AbstractHelper
{
    /**
     * Get the IIIF Collection manifest for the specified list of resources.
     *
     * @param array $resources Array of resources.
     * @param string $version
     * @throws \IiifServer\Iiif\Exception\RuntimeException
     * @return Object|null
     */
    public function __invoke(array $resources, $version = null)
    {
        $view = $this->getView();

        if (is_null($version)) {
            $version = $view->setting('iiifserver_manifest_default_version', '2');
        } else {
            $version = (string) $version === '2' ? '2' : '3';
        }

        return $version === '2'
            ? $view->iiifCollectionList2($resources)
            : $view->iiifCollectionList3($resources);
    }
}
