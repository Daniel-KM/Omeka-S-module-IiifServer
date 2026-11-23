<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class ManifestV2Test extends IiifServerControllerTestCase
{
    public function testManifestV2EmptyItem(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('@context', $json);
        $context = is_string($json['@context']) ? $json['@context'] : implode(' ', $json['@context']);
        $this->assertStringContainsString('presentation/2', $context);
    }

    public function testManifestV2ItemWithMetadata(): void
    {
        $item = $this->createItemWithMetadata('My Title', 'My Description', 'An Author');
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('label', $json);
        $this->assertArrayHasKey('metadata', $json);
    }

    public function testManifestV2ItemWithHtmlMedia(): void
    {
        $item = $this->createItemWithHtmlMedia('Item with one media', 1);
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        // HTML media may not produce sequences/canvases since they are not
        // standard IIIF renderable types. Verify valid manifest.
        $this->assertArrayHasKey('@type', $json);
        $this->assertSame('sc:Manifest', $json['@type']);
    }

    public function testManifestV2ItemWithMultipleMedia(): void
    {
        $item = $this->createItemWithHtmlMedia('Item with multiple media', 3);
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $this->assertResponseStatusCode(200);
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertSame('sc:Manifest', $json['@type']);
    }

    public function testManifestV2HasRequiredProperties(): void
    {
        $item = $this->createItemWithMetadata();
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertArrayHasKey('@id', $json);
        $this->assertArrayHasKey('@type', $json);
        $this->assertArrayHasKey('label', $json);
        $this->assertSame('sc:Manifest', $json['@type']);
    }

    public function testManifestV2ContentType(): void
    {
        $item = $this->createItem();
        $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
        $contentType = $this->getResponse()->getHeaders()->get('Content-Type')->getFieldValue();
        $this->assertStringContainsString('json', $contentType);
    }

    /**
     * Create an item with a single pdf, with the sizes of its pages stored,
     * so the tool pdfinfo is not needed.
     */
    protected function createItemWithPdf(int $countPages)
    {
        $item = $this->createItemWithHtmlMedia('Item with pdf');
        $media = $item->primaryMedia();
        $this->getApplicationServiceLocator()->get('Omeka\Connection')->executeStatement(
            'UPDATE `media` SET `media_type` = "application/pdf", `renderer` = "file", `has_original` = 1, `storage_id` = ?, `extension` = "pdf", `data` = ? WHERE `id` = ?',
            ['test-pdf-' . $media->id(), json_encode(['pdf_page_points' => array_fill(0, $countPages, [595.0, 842.0])]), $media->id()]
        );
        $this->getApplicationServiceLocator()->get('Omeka\EntityManager')->clear();
        $this->loginAsAdmin();
        return $this->api()->read('items', $item->id())->getContent();
    }

    public function testManifestV2PdfPagesAreCanvases(): void
    {
        $item = $this->createItemWithPdf(3);
        $settings = $this->getApplicationServiceLocator()->get('Omeka\Settings');
        $previous = $settings->get('iiifserver_manifest_pdf_pages');
        try {
            $settings->set('iiifserver_manifest_pdf_pages', true);
            $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
            $this->assertResponseStatusCode(200);
            $json = json_decode($this->getResponse()->getContent(), true);
            $canvases = $json['sequences'][0]['canvases'] ?? [];
            $this->assertCount(3, $canvases);
            foreach ($canvases as $index => $canvas) {
                $this->assertSame((string) ($index + 1), $canvas['label']);
                $this->assertGreaterThan(0, $canvas['width']);
                $service = $canvas['images'][0]['resource']['service'];
                $this->assertSame('http://iiif.io/api/image/2/context.json', $service['@context']);
                $this->assertStringEndsWith(';' . ($index + 1), rawurldecode($service['@id']));
            }
            // The pdf is still available for download.
            $this->assertSame('application/pdf', $json['sequences'][0]['rendering'][0]['format'] ?? null);
        } finally {
            $settings->set('iiifserver_manifest_pdf_pages', $previous);
        }
    }

    public function testManifestV2PdfWithoutPagesOptionIsNotSplit(): void
    {
        $item = $this->createItemWithPdf(3);
        $settings = $this->getApplicationServiceLocator()->get('Omeka\Settings');
        $previous = $settings->get('iiifserver_manifest_pdf_pages');
        try {
            $settings->set('iiifserver_manifest_pdf_pages', false);
            $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
            $this->assertResponseStatusCode(200);
            $json = json_decode($this->getResponse()->getContent(), true);
            $this->assertLessThanOrEqual(1, count($json['sequences'][0]['canvases'] ?? []));
        } finally {
            $settings->set('iiifserver_manifest_pdf_pages', $previous);
        }
    }

    public function testManifestV2PdfOfItemWithImageIsNotSplit(): void
    {
        $item = $this->createItemWithHtmlMedia('Item with image and pdf', 2);
        $medias = $item->media();
        $connection = $this->getApplicationServiceLocator()->get('Omeka\Connection');
        $connection->executeStatement(
            'UPDATE `media` SET `media_type` = "image/jpeg", `renderer` = "file", `has_original` = 1, `storage_id` = ?, `extension` = "jpg", `data` = ? WHERE `id` = ?',
            ['test-image-' . $medias[0]->id(), json_encode(['dimensions' => ['original' => ['width' => 100, 'height' => 100]]]), $medias[0]->id()]
        );
        $connection->executeStatement(
            'UPDATE `media` SET `media_type` = "application/pdf", `renderer` = "file", `has_original` = 1, `storage_id` = ?, `extension` = "pdf", `data` = ? WHERE `id` = ?',
            ['test-pdf-' . $medias[1]->id(), json_encode(['pdf_page_points' => [[595.0, 842.0], [595.0, 842.0]]]), $medias[1]->id()]
        );
        $this->getApplicationServiceLocator()->get('Omeka\EntityManager')->clear();
        $this->loginAsAdmin();
        $settings = $this->getApplicationServiceLocator()->get('Omeka\Settings');
        $previous = $settings->get('iiifserver_manifest_pdf_pages');
        try {
            $settings->set('iiifserver_manifest_pdf_pages', true);
            $this->dispatch('/iiif/2/' . $item->id() . '/manifest');
            $json = json_decode($this->getResponse()->getContent(), true);
            // Only the image is a canvas, the pdf is a download.
            $this->assertCount(1, $json['sequences'][0]['canvases']);
            $this->assertSame('application/pdf', $json['sequences'][0]['rendering'][0]['format'] ?? null);
        } finally {
            $settings->set('iiifserver_manifest_pdf_pages', $previous);
        }
    }
}
