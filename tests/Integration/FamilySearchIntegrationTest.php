<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

/**
 * Integration tests against live FamilySearch sandbox API
 */
class FamilySearchIntegrationTest extends ApiTestCase
{
    public function testAuthenticate(): void
    {
        $response = $this->login();

        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertArrayHasKey('access_token', $response->data);
    }

    public function testPost(): void
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        $this->assertNotNull($personId);
        $this->assertNotEmpty($personId);
    }

    public function testGet(): void
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        $this->assertNotNull($personId);

        $response = $this->client->get('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $this->assertResponseData($response);
    }

    public function testHead(): void
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        $this->assertNotNull($personId);

        $response = $this->client->head('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $this->assertEmpty($response->body);
        $this->assertEmpty($response->data ?? null);
    }

    public function testDelete(): void
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        $this->assertNotNull($personId);

        $response = $this->client->delete('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);

        $response = $this->client->get('/platform/tree/persons/' . $personId);
        $this->assertEquals(410, $response->statusCode);
    }

    /**
     * Test redirect handling against live API
     */
    public function testRedirect(): void
    {
        $this->assertResponseOK($this->login());
        $response = $this->client->get('/platform/tree/current-person');

        $this->assertTrue($response->redirected);
        $this->assertStringContainsString('/platform/tree/current-person', $response->originalUrl);
        $this->assertStringContainsString('/platform/tree/persons/', $response->effectiveUrl);
    }

    /**
     * Test that pendingModifications config sends X-FS-Feature-Tag header in requests
     */
    public function testPendingModification(): void
    {
        $creds = $this->getCredentials();
        $this->client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'pendingModifications' => ['consolidate-redundant-resources']
        ]);

        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        $this->assertNotNull($personId);

        $response = $this->client->get('/platform/tree/persons/' . $personId);

        $this->assertResponseOK($response);
        $this->assertResponseData($response);

        // Verify the X-FS-Feature-Tag header was sent with the request
        $this->assertArrayHasKey('X-FS-Feature-Tag', $response->requestHeaders);
        $this->assertStringContainsString('consolidate-redundant-resources', $response->requestHeaders['X-FS-Feature-Tag']);
    }

    public function testUserAgent(): void
    {
        $creds = $this->getCredentials();
        $this->client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'userAgent' => 'myApp/1.2.3'
        ]);

        $this->assertResponseOK($this->login());
        $response = $this->client->get('https://httpbin.org/user-agent');

        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertStringStartsWith('FS-PHP-Lite', $response->requestHeaders['User-Agent']);
        $this->assertStringContainsString('curl', $response->requestHeaders['User-Agent']);
        $this->assertStringContainsString('PHP', $response->requestHeaders['User-Agent']);
        $this->assertStringContainsString('myApp/1.2.3', $response->requestHeaders['User-Agent']);
    }
}
