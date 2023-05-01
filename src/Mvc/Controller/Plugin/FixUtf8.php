<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class FixUtf8 extends AbstractPlugin
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Some utf-8 files, generally edited under Windows, should be cleaned.
     *
     * Microsoft encodes many files with 16 bits (utf-16), so edition of a file
     * encoded as utf-8 may be broken, or the file may be converted into utf-16
     * without warning. This is particularly insidiuous for xml files, because
     * its encoding scheme is declared in the first line and is not updated.
     * The issue occurs with json files too, that must be utf-8 encoded.
     *
     * Microsoft is aware of this issue and that standards are not respected
     * since some decades, but reject fixes, that is one of the multiple
     * disloyal ways to force users to do all their workflow under Microsoft
     * tools (server, os, office, etc.).
     *
     * The same issue occurs with Apple and Google or with any monopolistic
     * publisher, so use only open standards and tools that really respect them,
     * generallly free (libre).
     *
     * @see https://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string#1401716
     *
     * Helper available in:
     * @see \EasyAdmin\Mvc\Controller\Plugin\SpecifyMediaType::fixUtf8()
     * @see \IiifServer\Mvc\Controller\Plugin\FixUtf8
     * @see \IiifSearch\View\Helper\FixUtf8
     */
    public function __invoke($string): string
    {
        $string = (string) $string;
        if (!strlen($string)) {
            return $string;
        }

        $regex = <<<'REGEX'
/
  (
    (?: [\x00-\x7F]               # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]    # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2} # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3} # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                      # ...one or more times
  )
| ( [\x80-\xBF] )                 # invalid byte in range 10000000 - 10111111
| ( [\xC0-\xFF] )                 # invalid byte in range 11000000 - 11111111
/x
REGEX;

        $utf8replacer = function ($captures) {
            if ($captures[1] !== '') {
                // Valid byte sequence. Return unmodified.
                return $captures[1];
            } elseif ($captures[2] !== '') {
                // Invalid byte of the form 10xxxxxx.
                // Encode as 11000010 10xxxxxx.
                return "\xC2" . $captures[2];
            } else {
                // Invalid byte of the form 11xxxxxx.
                // Encode as 11000011 10xxxxxx.
                return "\xC3" . chr(ord($captures[3]) - 64);
            }
        };

        // Log invalid files.
        $count = 0;
        $result = preg_replace_callback($regex, $utf8replacer, $string, -1, $count);
        if ($count && $string !== $result) {
            $this->logger->warn(sprintf(
                'Warning: some files contain invalid unicode characters and cannot be processed quickly.' // @translate
            ));
        }

        return $result;
    }
}
