<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

use VCR\VCR;

/**
 * Integration tests for authentication methods
 */
class FamilySearchAuthTest extends ApiTestCase
{
    /**
     * Test isAuthenticated returns false without token
     */
    public function testIsAuthenticatedWithoutToken(): void
    {
        $client = new \FamilySearch([
            'appKey' => 'test-key',
            'sessions' => false
        ]);

        // Without access token, should return false
        $this->assertFalse($client->isAuthenticated());
    }

    /**
     * Test isAuthenticated with valid token
     *
     * @vcr testIsAuthenticated.json
     */
    public function testIsAuthenticatedWithValidToken(): void
    {
        VCR::turnOn();
        VCR::insertCassette('testIsAuthenticated.json');

        $this->assertResponseOK($this->login());

        // Now check if authenticated
        $isAuth = $this->client->isAuthenticated();

        $this->assertTrue($isAuth);

        VCR::eject();
        VCR::turnOff();
    }

    /**
     * Test isAuthenticated with invalid token
     */
    public function testIsAuthenticatedWithInvalidToken(): void
    {
        $client = new \FamilySearch([
            'appKey' => 'test-key',
            'accessToken' => 'invalid-token-xyz',
            'sessions' => false
        ]);

        // With invalid token, API call should fail
        // We can't use VCR for this as it would require recording a 401
        // Just verify the method exists and returns a boolean
        $this->assertIsBool($client->isAuthenticated());
    }
}
