<?php declare(strict_types=1);

namespace IiifServerTest\View\Helper;

use IiifServer\View\Helper\PdfPageSizes;
use PHPUnit\Framework\TestCase;

class PdfPageSizesTest extends TestCase
{
    protected function helper(): PdfPageSizes
    {
        return (new \ReflectionClass(PdfPageSizes::class))->newInstanceWithoutConstructor();
    }

    public function testParsePdfinfo(): void
    {
        $output = <<<'TXT'
            Pages:           3
            Page    1 size:  334 x 524 pts
            Page    1 rot:   0
            Page    2 size:  326.4 x 524.6 pts
            Page    2 rot:   0
            Page    3 size:  595.28 x 841.89 pts (A4)
            Page    3 rot:   90
            File size:       9798433 bytes
            TXT;
        $this->assertSame([
            [334.0, 524.0],
            [326.4, 524.6],
            [841.89, 595.28],
        ], $this->helper()->parsePdfinfo($output));
    }

    public function testSizesInPixelsFromCachedPoints(): void
    {
        $helper = new PdfPageSizes(
            '/tmp',
            $this->createMock(\Omeka\Stdlib\Cli::class),
            $this->createMock(\Doctrine\DBAL\Connection::class),
            150
        );
        $media = $this->createMock(\Omeka\Api\Representation\MediaRepresentation::class);
        $media->method('mediaType')->willReturn('application/pdf');
        $media->method('hasOriginal')->willReturn(true);
        $media->method('mediaData')->willReturn(['pdf_page_points' => [[334.08, 524.16], [326, 524]]]);
        // Like Cantaloupe at 150 dpi: 696 x 1092 and 679 x 1092.
        $this->assertSame([
            ['width' => 696, 'height' => 1092, 'points_width' => 334.08, 'points_height' => 524.16],
            ['width' => 679, 'height' => 1092, 'points_width' => 326.0, 'points_height' => 524.0],
        ], $helper($media));
    }

    public function testParseEmptyOutput(): void
    {
        $this->assertSame([], $this->helper()->parsePdfinfo('Syntax Error: Couldn\'t read xref table'));
    }
}
