<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

class ExternalImageServerTest extends IiifServerControllerTestCase
{
    /**
     * In the test environment, no external image server is present, so the
     * IIIF image info route is handled by IiifServer itself.
     *
     * The media has no image file, so the response is an error 404, as
     * recommended by the IIIF Image API.
     */
    public function testIiifImageInfoRouteResponds(): void
    {
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $mediaId = $item->media()[0]->id();
        $this->dispatch('/iiif/3/' . $mediaId . '/info.json');
        $this->assertControllerName(\IiifServer\Controller\MediaController::class);
        $this->assertActionName('info');
        $this->assertResponseStatusCode(404);
    }

    /**
     * Without an external server, the info.json response comes from IiifServer
     * as json, even for an error.
     */
    public function testIiifImageInfoContentType(): void
    {
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $mediaId = $item->media()[0]->id();
        $this->dispatch('/iiif/3/' . $mediaId . '/info.json');
        $contentType = $this->getResponse()->getHeaders()
            ->get('Content-Type')->getFieldValue();
        $this->assertStringContainsString('json', $contentType);
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

    /**
     * The option to append "info.json" is ignored without external server: the
     * id of the info.json is the base of the image requests.
     */
    public function testInfoJsonIdentifierIsIgnoredWithoutExternalServer(): void
    {
        $item = $this->createItemWithHtmlMedia('Test item', 1);
        $mediaId = $item->media()[0]->id();
        $this->getApplicationServiceLocator()->get('Omeka\Connection')->executeStatement(
            'UPDATE `media` SET `media_type` = "image/jpeg", `renderer` = "file", `has_original` = 1, `storage_id` = ?, `extension` = "jpg", `data` = ? WHERE `id` = ?',
            ['test-infojson-' . $mediaId, json_encode(['dimensions' => ['original' => ['width' => 1000, 'height' => 1500]]]), $mediaId]
        );
        $settings = $this->getApplicationServiceLocator()->get('Omeka\Settings');
        $previous = [
            'iiifserver_media_api_identifier_infojson' => $settings->get('iiifserver_media_api_identifier_infojson'),
            'iiifserver_media_api_url' => $settings->get('iiifserver_media_api_url'),
        ];
        try {
            $settings->set('iiifserver_media_api_identifier_infojson', true);
            $settings->set('iiifserver_media_api_url', '');
            $this->dispatch('/iiif/3/' . $mediaId . '/info.json');
            $this->assertResponseStatusCode(200);
            $json = json_decode($this->getResponse()->getContent(), true);
            $this->assertStringEndsWith('/' . $mediaId, $json['id']);
        } finally {
            foreach ($previous as $name => $value) {
                $settings->set($name, $value);
            }
        }
    }
}
