<?php

namespace FamilySearch\Tests;

class GedcomxPHPTest extends ApiTestCase
{
    
    /**
     * Automatically called by PHPUnit before each test is run. This resets
     * the client to a fresh non-authenticated state.
     */
    public function setUp()
    {
        $this->client = new \FamilySearch([
            'appKey' => SandboxCredentials::API_KEY,
            'objects' => true
        ]);
    }
    
    /**
     * @vcr gedcomx/testAuthenticate.json
     */
    public function testAuthenticate()
    {
        $response = $this->login();
        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertArrayHasKey('token', $response->data);
        $this->assertNotHasGedcomxObject($response);
    }
    
    /**
     * @vcr gedcomx/testPost.json
     */
    public function testPost()
    {
        $this->assertResponseOK($this->login());
        $response = $this->createPerson();
        $this->assertNotNull($response);
        $this->assertNotHasGedcomxObject($response);
    }
    
    /**
     * @vcr gedcomx/testGet.json
     */
    public function testGet()
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();
        $response = $this->client->get('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $this->assertResponseData($response);
        $this->assertHasGedcomxObject($response);
        $this->assertEquals(1, count($response->gedcomx->getPersons()));
    }
    
    /**
     * @vcr gedcomx/testHead.json
     */
    public function testHead()
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();
        $response = $this->client->head('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $this->assertEmpty($response->body);
        $this->assertEmpty($response->data);
        $this->assertNotHasGedcomxObject($response);
    }
    
    /**
     * @vcr gedcomx/testDelete.json
     */
    public function testDelete()
    {
        $this->assertResponseOK($this->login());
        $personId = $this->createPerson();
        $response = $this->client->delete('/platform/tree/persons/' . $personId);
        $this->assertResponseOK($response);
        $response = $this->client->get('/platform/tree/persons/' . $personId);
        $this->assertEquals(410, $response->statusCode);
        $this->assertHasGedcomxObject($response);
    }
    
    /**
     * @vcr gedcomx/testRedirect.json
     */
    public function testRedirect()
    {
        $this->assertResponseOK($this->login());
        $response = $this->client->get('/platform/tree/current-person');
        $this->assertTrue($response->redirected);
        $this->assertEquals('https://integration.familysearch.org/platform/tree/current-person', $response->originalUrl);
        $this->assertEquals('https://integration.familysearch.org/platform/tree/persons/KW7G-28J', $response->effectiveUrl);
        $this->assertHasGedcomxObject($response);
    }
    
    /**
     * Create a person and return the person's ID
     *
     * @return string person ID
     */
    protected function createPerson()
    {
        $response = $this->client->post('/platform/tree/persons', [
            'body' => new \Gedcomx\Extensions\FamilySearch\FamilySearchPlatform($this->personData())
        ]);
        return $response->headers['X-ENTITY-ID'];
    }
    
    private function assertHasGedcomxObject($response)
    {
        $this->assertObjectHasAttribute('gedcomx', $response);
    }
    
    private function assertNotHasGedcomxObject($response)
    {
        $this->assertTrue(!isset($response->gedcomx));
    }
    
}