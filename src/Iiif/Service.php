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
class Service extends AbstractType
{
    protected $type = 'Service';

    protected $keys = [
        '@context' => self::OPTIONAL,
        'id' => self::REQUIRED,
        'type' => self::REQUIRED,
        'profile' => self::RECOMMENDED,
        'label' => self::OPTIONAL,
        // May have any other keys.
    ];

    protected $officialServices = [
        'ImageService1',
        'ImageService2',
        'ImageService3',
        'SearchService1',
        'AutoCompleteService1',
        'AuthCookieService1',
        'AuthTokenService1',
        'AuthLogoutService1',
    ];

    protected $id;

    /**
     * @var array
     */
    protected $options;

    public function __construct(AbstractResourceEntityRepresentation $resource, array $options = [])
    {
        if (isset($options['@id'])) {
            $options['id'] = $options['@id'];
            unset($options['@id']);
        }
        if (isset($options['@type'])) {
            $options['type'] = $options['@type'];
            unset($options['@type']);
        }
        $this->options = array_filter($options);
    }

    public function getContent(): array
    {
        return $this->options;
    }

    public function id(): ?string
    {
        return $this->options['id'] ?? null;
    }

    public function type(): ?string
    {
        return $this->options['type'] ?? null;
    }

    public function profile(): ?string
    {
        return $this->options['profile'] ?? null;
    }

    public function label(): ?string
    {
        return $this->options['label'] ?? null;
    }
}
