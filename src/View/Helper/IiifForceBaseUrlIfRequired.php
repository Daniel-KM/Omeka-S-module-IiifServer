<?php

/*
 * Copyright 2015-2017  Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace IiifServer\View\Helper;

use Zend\View\Helper\AbstractHelper;

class IiifForceBaseUrlIfRequired extends AbstractHelper
{
    /**
     * Set the base of the url to change from, for example "http:".
     *
     * @var string
     */
    protected $forceFrom;

    /**
     * Set the base of the url to change to, for example "https:".
     *
     * @var string
     */
    protected $forceTo;

    /**
     * Force the base of absolute urls.
     *
     * @param string $absoluteUrl
     * @return string
     */
    public function __invoke($absoluteUrl)
    {
        if (is_null($this->forceFrom)) {
            $this->forceFrom = (string) $this->view->setting('iiifserver_url_force_from');
            $this->forceTo = (string) $this->view->setting('iiifserver_url_force_to');
        }

        return $this->forceFrom && (strpos($absoluteUrl, $this->forceFrom) === 0)
            ? substr_replace($absoluteUrl, $this->forceTo, 0, strlen($this->forceFrom))
            : $absoluteUrl;
    }
}
