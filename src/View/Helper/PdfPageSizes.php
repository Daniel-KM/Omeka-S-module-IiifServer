<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Stdlib\Cli;

/**
 * Get the size of each page of a pdf, in points.
 *
 * The sizes are extracted with pdfinfo (poppler-utils) and cached in the data
 * of the media (key "pdf_pages").
 */
class PdfPageSizes extends AbstractHelper
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Omeka\Stdlib\Cli
     */
    protected $cli;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function __construct(string $basePath, Cli $cli, Connection $connection)
    {
        $this->basePath = $basePath;
        $this->cli = $cli;
        $this->connection = $connection;
    }

    /**
     * @return array<int, array{width: int, height: int}> List of sizes by page,
     *   from 0. Empty for a media that is not a pdf.
     */
    public function __invoke(AbstractResourceEntityRepresentation $media): array
    {
        if ($media->mediaType() !== 'application/pdf' || !$media->hasOriginal()) {
            return [];
        }

        $mediaData = $media->mediaData() ?: [];
        if (!empty($mediaData['pdf_pages']) && is_array($mediaData['pdf_pages'])) {
            return $mediaData['pdf_pages'];
        }

        $filepath = $this->basePath . '/original/' . $media->filename();
        if (!is_readable($filepath)) {
            return [];
        }

        $output = $this->cli->execute(sprintf('pdfinfo -f 1 -l 100000 %s', escapeshellarg($filepath)));
        $sizes = $output ? $this->parsePdfinfo((string) $output) : [];
        if ($sizes && $media instanceof MediaRepresentation) {
            $this->cache($media->id(), $sizes);
        }
        return $sizes;
    }

    /**
     * Get the sizes of the pages from the output of pdfinfo, rotation included.
     */
    public function parsePdfinfo(string $output): array
    {
        $sizes = [];
        if (preg_match_all('~^Page\s+(\d+)\s+size:\s+([\d.]+)\s+x\s+([\d.]+)~m', $output, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sizes[(int) $match[1]] = [
                    'width' => (int) round((float) $match[2]),
                    'height' => (int) round((float) $match[3]),
                ];
            }
        }
        if (preg_match_all('~^Page\s+(\d+)\s+rot:\s+(\d+)~m', $output, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $page = (int) $match[1];
                if (isset($sizes[$page]) && in_array((int) $match[2] % 360, [90, 270], true)) {
                    $sizes[$page] = ['width' => $sizes[$page]['height'], 'height' => $sizes[$page]['width']];
                }
            }
        }
        ksort($sizes);
        return array_values($sizes);
    }

    protected function cache(int $mediaId, array $sizes): void
    {
        try {
            $raw = $this->connection->fetchOne('SELECT `data` FROM `media` WHERE `id` = ?', [$mediaId]);
            $mediaData = $raw ? json_decode($raw, true) : [];
            if (!is_array($mediaData)) {
                $mediaData = [];
            }
            $mediaData['pdf_pages'] = $sizes;
            $this->connection->executeStatement(
                'UPDATE `media` SET `data` = ? WHERE `id` = ?',
                [json_encode($mediaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $mediaId]
            );
        } catch (\Throwable $e) {
            // Retry later.
        }
    }
}
