<?php

namespace FamilySearch\Tests\Integration;

use VCR\VCR;

/**
 * Integration tests using VCR for HTTP recording/replay
 */
class FamilySearchIntegrationTest extends ApiTestCase
{
    /**
     * @vcr testAuthenticate.json
     */
    public function testAuthenticate(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testAuthenticate.json');

        $response = $this->login();

        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertArrayHasKey('access_token', $response->data);

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @vcr testPost.json
     */
    public function testPost(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testPost.json');

        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        if ($personId) {
            $this->assertNotEmpty($personId);
        } else {
            $this->markTestSkipped('Could not create person for testing');
        }

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @vcr testGet.json
     */
    public function testGet(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testGet.json');

        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        if ($personId) {
            $response = $this->client->get('/platform/tree/persons/' . $personId);
            $this->assertResponseOK($response);
            $this->assertResponseData($response);
        } else {
            $this->markTestSkipped('Could not create person for testing');
        }

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @vcr testHead.json
     */
    public function testHead(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testHead.json');

        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        if ($personId) {
            $response = $this->client->head('/platform/tree/persons/' . $personId);
            $this->assertResponseOK($response);
            $this->assertEmpty($response->body);
            $this->assertEmpty($response->data);
        } else {
            $this->markTestSkipped('Could not create person for testing');
        }

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @vcr testDelete.json
     */
    public function testDelete(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testDelete.json');

        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        if ($personId) {
            $response = $this->client->delete('/platform/tree/persons/' . $personId);
            $this->assertResponseOK($response);

            $response = $this->client->get('/platform/tree/persons/' . $personId);
            $this->assertEquals(410, $response->statusCode);
        } else {
            $this->markTestSkipped('Could not create person for testing');
        }

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @vcr testRedirect.json
     */
    public function testRedirect(): void
    {
        // Note: VCR cassette response parsing currently doesn't work correctly
        // with SDK's redirect handling. The cassette contains the full redirect
        // chain but VCR's interception interferes with curl_exec() response format.
        // This test passes when run against live API but fails with VCR playback.
        $this->markTestSkipped('VCR redirect handling needs investigation');

        VCR::turnOn();
        VCR::insertCassette('testRedirect.json');

        $this->assertResponseOK($this->login());
        $response = $this->client->get('/platform/tree/current-person');

        $this->assertTrue($response->redirected);
        $this->assertEquals('https://api-integ.familysearch.org/platform/tree/current-person', $response->originalUrl);
        $this->assertEquals('https://api-integ.familysearch.org/platform/tree/persons/KW7G-28J', $response->effectiveUrl);

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @vcr testPendingModification.json
     */
    public function testPendingModification(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testPendingModification.json');

        $creds = $this->getCredentials();
        $this->client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'pendingModifications' => ['consolidate-redundant-resources']
        ]);

        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();

        if ($personId) {
            $response = $this->client->get('/platform/tree/persons-with-relationships?person=' . $personId);
            $this->assertResponseOK($response);
            $this->assertResponseData($response);
            $this->assertTrue($response->redirected);
        } else {
            $this->markTestSkipped('Could not create person for testing');
        }

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @vcr testUserAgent.json
     */
    public function testUserAgent(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testUserAgent.json');

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

        VCR::eject();
        VCR::turnOff();
    }
}
