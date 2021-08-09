<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class RangeToArray extends AbstractHelper
{
    /**
     * @var \IiifServer\Mvc\Controller\Plugin\RangeToArray
     */
    protected $rangeToArrayPlugin;

    /**
     * @param \IiifServer\Mvc\Controller\Plugin\RangeToArray $rangeToArrayPlugin
     */
    public function __construct($rangeToArrayPlugin)
    {
        $this->rangeToArrayPlugin = $rangeToArrayPlugin;
    }

    /**
     * Convert a string like "-3 6-8 12-" into a filled array with all values,
     * here `[3, 6, 7, 8, 12]` (min should be set to 1 to start from 1).
     *
     * - The numbers should be positive integers.
     * - The separator can be a space, a ";", or any other character except "-"
     *   and numbers.
     * - There should not be space after or before a "-", else it will be an
     *   open range.
     * - If the first or last value is not set ("-3" or "12-" here), the min or
     *   max value is used. If min or max is null, the range is a single value.
     * - if a separator is set, string values will be kept, for example with ";"
     *   for "cover; 2-4; 9-; back cover", the output will be `['cover', 2, 3, 4, 9, 'back cover']`.
     *
     * @uses \IiifServer\Mvc\Controller\Plugin\RangeToArray
     */
    public function __invoke(
        string $string,
        ?int $min = null,
        ?int $max = null,
        bool $unique = false,
        bool $sort = false,
        ?string $separator = null
    ): array {
        return $this->rangeToArrayPlugin->__invoke($string, $min, $max, $unique, $sort, $separator);
    }
}
