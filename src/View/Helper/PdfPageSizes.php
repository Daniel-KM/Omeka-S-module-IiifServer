<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Stdlib\Cli;

/**
 * Get the size of each page of a pdf, in pixels as rendered by the image server.
 *
 * The sizes in points are extracted with pdfinfo (poppler-utils) and cached in
 * the data of the media (key "pdf_page_points"). The size in pixels depends on
 * the resolution of the image server (150 dpi by default for Cantaloupe), that
 * is the size of the canvas, because some viewers expect the same size for the
 * canvas and the image.
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

    /**
     * @var int
     */
    protected $dpi;

    public function __construct(string $basePath, Cli $cli, Connection $connection, int $dpi)
    {
        $this->basePath = $basePath;
        $this->cli = $cli;
        $this->connection = $connection;
        $this->dpi = $dpi ?: 150;
    }

    /**
     * @return array<int, array{width: int, height: int, points_width: float, points_height: float}>
     *   List of sizes by page, from 0, in pixels and in points. Empty for a
     *   media that is not a pdf.
     */
    public function __invoke(AbstractResourceEntityRepresentation $media): array
    {
        if ($media->mediaType() !== 'application/pdf' || !$media->hasOriginal()) {
            return [];
        }

        $mediaData = $media->mediaData() ?: [];
        $points = $mediaData['pdf_page_points'] ?? null;
        if (!$points || !is_array($points)) {
            $filepath = $this->basePath . '/original/' . $media->filename();
            if (!is_readable($filepath)) {
                return [];
            }
            $output = $this->cli->execute(sprintf('pdfinfo -f 1 -l 100000 %s', escapeshellarg($filepath)));
            $points = $output ? $this->parsePdfinfo((string) $output) : [];
            if ($points && $media instanceof MediaRepresentation) {
                $this->cache($media->id(), $points);
            }
        }

        $scale = $this->dpi / 72;
        return array_map(fn ($size) => [
            'width' => (int) round($size[0] * $scale),
            'height' => (int) round($size[1] * $scale),
            'points_width' => (float) $size[0],
            'points_height' => (float) $size[1],
        ], $points);
    }

    /**
     * Get the sizes of the pages in points from the output of pdfinfo, with the
     * rotation.
     *
     * @return array<int, array{0: float, 1: float}> Width and height by page.
     */
    public function parsePdfinfo(string $output): array
    {
        $sizes = [];
        if (preg_match_all('~^Page\s+(\d+)\s+size:\s+([\d.]+)\s+x\s+([\d.]+)~m', $output, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sizes[(int) $match[1]] = [(float) $match[2], (float) $match[3]];
            }
        }
        if (preg_match_all('~^Page\s+(\d+)\s+rot:\s+(\d+)~m', $output, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $page = (int) $match[1];
                if (isset($sizes[$page]) && in_array((int) $match[2] % 360, [90, 270], true)) {
                    $sizes[$page] = [$sizes[$page][1], $sizes[$page][0]];
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
            unset($mediaData['pdf_pages']);
            $mediaData['pdf_page_points'] = $sizes;
            $this->connection->executeStatement(
                'UPDATE `media` SET `data` = ? WHERE `id` = ?',
                [json_encode($mediaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $mediaId]
            );
        } catch (\Throwable $e) {
            // Retry later.
        }
    }
}
