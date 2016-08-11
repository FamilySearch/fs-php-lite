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
        $response = $this->client->get('/platform/users/current');
        $this->assertTrue($response->redirected);
        $this->assertEquals('/platform/users/current', $response->originalUrl);
        
        // $this->assertEquals('/platform/users/current', $response->finalUrl);
    }
    
}