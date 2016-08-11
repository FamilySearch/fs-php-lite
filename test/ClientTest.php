<?php

namespace FamilySearch\Tests;

class ClientTest extends ApiTestCase
{
    /**
     * @vcr ClientTests/testAuthenticate.json
     */
    public function testAuthenticate()
    {
        $response = $this->login();
        $this->assertEquals(200, $response->statusCode);
        $this->assertObjectHasAttribute('data', $response);
        $this->assertArrayHasKey('token', $response->data);
    }
}