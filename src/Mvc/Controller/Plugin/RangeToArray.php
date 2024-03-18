<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class RangeToArray extends AbstractPlugin
{
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
     * - To manage names with "-", another extender can be set, for example "/".
     *   Nevertheless, the same rules above apply.
     */
    public function __invoke(
        string $string,
        ?int $min = null,
        ?int $max = null,
        bool $unique = false,
        bool $sort = false,
        ?string $separator = null,
        ?string $extender = '-'
    ): array {
        $result = is_null($separator)
            ? $this->rangeToArrayInteger($string, $min, $max, $extender ?? '-')
            : $this->rangeToArrayString($string, $min, $max, $separator, $extender ?? '-');

        if ($unique) {
            $result = array_values(array_unique($result));
        }

        if ($sort) {
            sort($result);
        }

        return $result;
    }

    protected function rangeToArrayInteger(
        string $string,
        ?int $min = null,
        ?int $max = null,
        string $extender = ''
    ): array {
        $string = preg_replace('/[^0-9' . preg_quote($extender, '/') . ']/', ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        $string = trim($string);

        $list = explode(' ', $string);

        // Skip fake range and ranges with multiple "-".
        $list = array_values(array_filter($list, fn ($v) => $v !== $extender && substr_count($v, $extender) <= 1));

        if (empty($list)) {
            return [];
        }

        $result = [];
        foreach ($list as $range) {
            if (strpos($range, $extender) === false) {
                $result[] = (int) $range;
                continue;
            }
            [$from, $to] = explode($extender, $range);
            $from = strlen($from) ? (int) $from : null;
            $to = strlen($to) ? (int) $to : null;
            if (is_null($from)) {
                if (is_null($min) || $min === $to) {
                    $result[] = $to;
                    continue;
                }
                $from = $min;
            } elseif (is_null($to)) {
                if (is_null($max) || $max === $from) {
                    $result[] = $from;
                    continue;
                }
                $to = $max;
            }
            $result = array_merge($result, range($from, $to));
        }

        return $result;
    }

    protected function rangeToArrayString(
        string $string,
        ?int $min = null,
        ?int $max = null,
        string $separator = '',
        string $extender = '-'
    ): array {
        $result = [];
        foreach (array_filter(array_map('trim', explode($separator, $string)), 'strlen') as $str) {
            if (preg_replace('/[^0-9' . preg_quote($extender, '/') . ']/', '', $str) === $str) {
                $result = array_merge($result, $this->rangeToArrayInteger($str, $min, $max, $extender));
            } elseif (strpos($str, $extender) !== false) {
                // TODO Range to array with a string should be finalized.
                $result[] = $str;
            } else {
                $result[] = $str;
            }
        }
        return $result;
    }
}
