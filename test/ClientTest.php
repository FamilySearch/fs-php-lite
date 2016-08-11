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
}