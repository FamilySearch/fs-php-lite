<?php

namespace FamilySearch\Tests;

class FamilySearchTests extends ApiTestCase
{
    /**
     * @vcr testAuthenticate.json
     */
    public function testAuthenticate()
    {
        $response = $this->login();
        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertArrayHasKey('token', $response->data);
    }
    
    /**
     * @vcr testPost.json
     */
    public function testPost()
    {
        $this->assertResponseOK($this->login());
        $this->assertNotNull($this->createPerson());
    }
    
    /**
     * @vcr testGet.json
     */
    public function testGet()
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();
        $response = $this->client->get('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $this->assertResponseData($response);
    }
    
    /**
     * @vcr testHead.json
     */
    public function testHead()
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();
        $response = $this->client->head('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $this->assertEmpty($response->body);
        $this->assertEmpty($response->data);
    }
    
    /**
     * @vcr testDelete.json
     */
    public function testDelete()
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();
        $response = $this->client->delete('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $response = $this->client->get('/platform/tree/persons/' . $personId);
        $this->assertEquals(410, $response->statusCode);
    }
    
    /**
     * @vcr testRedirect.json
     */
    public function testRedirect()
    {
        $this->assertResponseOK($this->login());
        $response = $this->client->get('/platform/tree/current-person');
        $this->assertTrue($response->redirected);
        $this->assertEquals('https://api-integ.familysearch.org/platform/tree/current-person', $response->originalUrl);
        $this->assertEquals('https://api-integ.familysearch.org/platform/tree/persons/KW7G-28J', $response->effectiveUrl);
    }
    
    /**
     * @vcr testPendingModification.json
     */
    public function testPendingModification()
    {
        $this->client = new \FamilySearch([
            'appKey' => SandboxCredentials::API_KEY,
            'pendingModifications' => ['consolidate-redundant-resources']
        ]);
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();
        $response = $this->client->get('/platform/tree/persons-with-relationships?person=' . $personId);
        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertTrue($response->redirected);
    }
    
    /**
     * @vcr testUserAgent.json
     */
    public function testUserAgent()
    {
        $this->client = new \FamilySearch([
            'appKey' => SandboxCredentials::API_KEY,
            'userAgent' => 'myApp/1.2.3'
        ]);
        $this->assertResponseOK($this->login());
        $response = $this->client->get('https://httpbin.org/user-agent');
        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertTrue(strpos($response->requestHeaders['User-Agent'], 'FS-PHP-Lite') === 0);
        $this->assertTrue(strpos($response->requestHeaders['User-Agent'], 'curl') !== false);
        $this->assertTrue(strpos($response->requestHeaders['User-Agent'], 'PHP') !== false);
        $this->assertTrue(strpos($response->requestHeaders['User-Agent'], 'myApp/1.2.3') !== false);
    }
    
}