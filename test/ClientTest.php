<?php

namespace FamilySearch\Tests;

class ClientTest extends ApiTestCase
{
    /**
     * @vcr ClientTests/testAuthenticate.json
     */
    public function testAuthenticate()
    {
        $accessToken = $this->login();
        $this->assertNotNull($accessToken);
    }
}