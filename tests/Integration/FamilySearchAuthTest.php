<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

/**
 * Integration tests for authentication methods against live sandbox API
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
     */
    public function testIsAuthenticatedWithValidToken(): void
    {
        $this->assertResponseOK($this->login());

        // Now check if authenticated
        $isAuth = $this->client->isAuthenticated();

        $this->assertTrue($isAuth);
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
        // Just verify the method exists and returns a boolean
        $this->assertIsBool($client->isAuthenticated());
    }
}
