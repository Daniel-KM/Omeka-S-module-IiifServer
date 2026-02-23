<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class ExternalImageServerTest extends IiifServerControllerTestCase
{
    /**
     * In the test environment, no external image server is present.
     * The IIIF image info route should be handled by IiifServer itself,
     * returning standard Apache/PHP headers.
     */
    public function testIiifImageInfoRouteResponds(): void
    {
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $mediaId = $item->media()[0]->id();
        $this->dispatch('/iiif/3/' . $mediaId . '/info.json');
        // The route should be handled (200 or 303 redirect to info.json).
        $status = $this->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 303]),
            'Expected 200 or 303 for IIIF image info, got ' . $status
        );
    }

    /**
     * Without an external server, the info.json response should come from
     * IiifServer (Content-Type: application/json or application/ld+json).
     */
    public function testIiifImageInfoContentType(): void
    {
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $mediaId = $item->media()[0]->id();
        $this->dispatch('/iiif/3/' . $mediaId . '/info.json');
        $status = $this->getResponse()->getStatusCode();
        if ($status === 200) {
            $contentType = $this->getResponse()->getHeaders()
                ->get('Content-Type')->getFieldValue();
            $this->assertTrue(
                strpos($contentType, 'json') !== false
                    || strpos($contentType, 'ld+json') !== false,
                'Expected JSON content type from IiifServer, got ' . $contentType
            );
        } else {
            // 303 redirect is also valid (redirect to canonical info.json).
            $this->assertSame(303, $status);
        }
    }

    /**
     * When no external server is present, the identifier setting should
     * not be changed by messageExternalImageServer().
     */
    public function testNoAutoConfigWithoutExternalServer(): void
    {
        $services = $this->getApplicationServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Set identifier to media_id (default).
        $settings->set('iiifserver_media_api_identifier', 'media_id');

        // Trigger the config form to invoke messageExternalImageServer().
        $this->loginAsAdmin();
        $this->dispatch('/admin/module/configure?id=IiifServer');

        // The setting should remain media_id since no external server
        // is detected in the test environment.
        $identifier = $settings->get('iiifserver_media_api_identifier');
        $this->assertSame(
            'media_id',
            $identifier,
            'Identifier should not change without external server'
        );
    }

    /**
     * A non-existent media should return 404 from IiifServer (not from an
     * external server).
     */
    public function testIiifImageInfoNonExistentMedia(): void
    {
        $this->dispatch('/iiif/3/999999/info.json');
        $this->assertResponseStatusCode(404);
    }

    /**
     * Verify that the IIIF image info response does not contain external
     * server headers (Jetty, Cantaloupe) in the test environment.
     */
    public function testNoExternalServerHeaders(): void
    {
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $mediaId = $item->media()[0]->id();
        $this->dispatch('/iiif/3/' . $mediaId . '/info.json');

        $headers = $this->getResponse()->getHeaders()->toString();
        $this->assertStringNotContainsStringIgnoringCase(
            'Cantaloupe',
            $headers,
            'No Cantaloupe header expected in test environment'
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'Jetty',
            $headers,
            'No Jetty header expected in test environment'
        );
    }
}
