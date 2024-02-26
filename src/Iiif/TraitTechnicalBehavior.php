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

trait TraitTechnicalBehavior
{
    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * List of excluding behaviors.
     *
     * @see https://iiif.io/api/presentation/3.0/#behavior
     *
     * @var array
     */
    protected $behaviorsExcluding = [
        // Temporal behaviors.
        'auto-advance' => ['no-auto-advance'],
        'no-auto-advance' => ['auto-advance'],
        'repeat' => ['no-repeat'],
        'no-repeat' => ['repeat'],
        // Layout behaviors.
        'unordered' => ['individuals', 'continuous', 'paged'],
        'individuals' => ['unordered', 'continuous', 'paged'],
        'continuous' => ['unordered', 'individuals', 'paged'],
        'paged' => ['unordered', 'individuals', 'continuous', 'facing-pages', 'non-paged'],
        'facing-pages' => ['paged', 'non-paged'],
        'non-paged' => ['paged', 'facing-pages'],
        // Collection behaviors.
        'multi-part' => ['together'],
        'together' => ['multi-part'],
        // Range behaviors.
        'sequence' => ['thumbnail-nav', 'no-nav'],
        'thumbnail-nav' => ['sequence', 'no-nav'],
        'no-nav' => ['sequence', 'thumbnail-nav'],
        // Miscellaneous behaviors.
        'hidden' => [],
    ];

    /**
     * @todo Should have the key "behaviors", present in AbstractResourceType?
     */
    public function behavior(): array
    {
        $behaviorProperty = $this->settings->get('iiifserver_manifest_behavior_property', []);
        if ($behaviorProperty) {
            $behaviors = $this->resource->value($behaviorProperty, ['all' => true]);
        }
        if (empty($behaviors)) {
            $behaviors = $this->settings->get('iiifserver_manifest_behavior_default', []);
        }

        if (in_array('none', $behaviors)) {
            return [];
        }

        foreach ($behaviors as $key => $behavior) {
            $behavior = (string) $behavior;
            if (!isset($this->behaviors[$behavior])
                || $this->behaviors[$behavior] === self::NOT_ALLOWED
            ) {
                unset($behaviors[$key]);
            }
        }

        // Check for exclusions.
        $result = $behaviors;
        foreach ($result as $behavior) {
            $behaviors = array_diff($behaviors, $this->behaviorsExcluding[$behavior] ?? []);
        }

        return array_values($behaviors);
    }
}
