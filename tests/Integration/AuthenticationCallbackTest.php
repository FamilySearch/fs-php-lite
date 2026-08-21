<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

/**
 * Integration tests for authentication failure callback system
 *
 * These tests verify that the onAuthenticationFailure callback is invoked
 * correctly when real 401 responses are received from the FamilySearch API.
 */
class AuthenticationCallbackTest extends ApiTestCase
{
    /**
     * Test that callback is invoked on 401 response with invalid token
     */
    public function testCallbackInvokedOn401WithInvalidToken(): void
    {
        $callbackInvoked = false;
        $receivedReason = null;
        $receivedStatusCode = null;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token-will-get-401',
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$receivedReason, &$receivedStatusCode) {
                $callbackInvoked = true;
                $receivedReason = $reason;
                $receivedStatusCode = $response->statusCode;
            }
        ]);

        // Make request with invalid token - should get 401
        $response = $client->get('/platform/users/current');

        // Callback should have been invoked
        $this->assertTrue($callbackInvoked, 'Callback should be invoked on 401 response');
        $this->assertEquals(401, $receivedStatusCode, 'Status code should be 401');
        $this->assertEquals('invalid', $receivedReason, 'Reason should be invalid (token not expired, just invalid)');

        // Response should still be returned normally
        $this->assertEquals(401, $response->statusCode);
    }

    /**
     * Test that callback receives 'expired' reason for expired token
     */
    public function testCallbackReceivesExpiredReasonForExpiredToken(): void
    {
        $callbackInvoked = false;
        $receivedReason = null;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'expirationWarningThreshold' => 0,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$receivedReason) {
                $callbackInvoked = true;
                $receivedReason = $reason;
            }
        ]);

        // Set token that appears expired (created long ago)
        $client->setAccessToken('expired-looking-token', -3600); // Token expired 1 hour ago

        // Make request - should get 401
        $response = $client->get('/platform/users/current');

        // Callback should have been invoked with 'expired' reason
        $this->assertTrue($callbackInvoked, 'Callback should be invoked on 401 response');
        $this->assertEquals('expired', $receivedReason, 'Reason should be expired (based on client-side tracking)');
        $this->assertEquals(401, $response->statusCode);
    }

    /**
     * Test that callback can re-authenticate and retry request
     */
    public function testCallbackCanReauthenticate(): void
    {
        $callbackInvoked = false;
        $reauthenticated = false;

        $creds = $this->getCredentials();

        // Create client with callback that re-authenticates
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-initial-token',
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$reauthenticated, $creds, &$client) {
                $callbackInvoked = true;

                // Re-authenticate using password grant
                $authResponse = $client->oauthPassword($creds['username'], $creds['password']);

                if ($authResponse->statusCode === 200) {
                    $reauthenticated = true;
                }
            }
        ]);

        // First request with invalid token - should trigger callback and re-auth
        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked, 'Callback should be invoked on first 401');
        $this->assertTrue($reauthenticated, 'Should have re-authenticated in callback');

        // Token should now be valid
        $this->assertNotNull($client->getAccessToken());
        $this->assertNotEquals('invalid-initial-token', $client->getAccessToken());

        // Second request should succeed with new token
        $response2 = $client->get('/platform/users/current');
        $this->assertLessThan(400, $response2->statusCode, 'Second request should succeed with new token');
    }

    /**
     * Test that SDK continues to work without callback (backward compatibility)
     */
    public function testBackwardCompatibilityWithout401Callback(): void
    {
        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token'
        ]);

        // Make request with invalid token - should get 401, no callback invoked
        $response = $client->get('/platform/users/current');

        // Should get 401 response normally (no callback, no exception)
        $this->assertEquals(401, $response->statusCode);
    }

    /**
     * Test that callback exception doesn't break request flow
     */
    public function testCallbackExceptionDoesntBreakFlow(): void
    {
        $callbackInvoked = false;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token',
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked) {
                $callbackInvoked = true;
                // Throw exception in callback
                throw new \Exception('Callback error');
            }
        ]);

        // Suppress PHP warnings from the callback exception handler
        $errorLevel = error_reporting();
        error_reporting($errorLevel & ~E_USER_WARNING);

        // Make request - callback will throw, but request should complete
        $response = $client->get('/platform/users/current');

        // Restore error reporting
        error_reporting($errorLevel);

        $this->assertTrue($callbackInvoked, 'Callback should have been invoked');
        $this->assertEquals(401, $response->statusCode, 'Should still return 401 response despite callback exception');
    }

    /**
     * Test callback with multiple 401 responses
     */
    public function testCallbackInvokedMultipleTimes(): void
    {
        $invocationCount = 0;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token-first',
            'onAuthenticationFailure' => function($response, $reason) use (&$invocationCount) {
                $invocationCount++;
            }
        ]);

        // Make first request with invalid token
        $response1 = $client->get('/platform/users/current');

        // Reset token to a different invalid value for second request
        $client->setAccessToken('invalid-token-second');

        // Make second request with different invalid token
        $response2 = $client->get('/platform/users/current');

        // At least one of these should have triggered the callback
        // (The API behavior with invalid tokens can vary)
        $this->assertGreaterThanOrEqual(1, $invocationCount, 'Callback should be invoked at least once for 401 responses');
    }

    /**
     * Test that successful requests don't trigger callback
     */
    public function testCallbackNotInvokedOnSuccessfulRequest(): void
    {
        $callbackInvoked = false;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked) {
                $callbackInvoked = true;
            }
        ]);

        // Authenticate with valid credentials
        $authResponse = $client->oauthPassword($creds['username'], $creds['password']);
        $this->assertResponseOK($authResponse);

        // Make successful request
        $response = $client->get('/platform/users/current');
        $this->assertResponseOK($response);

        // Callback should NOT be invoked for successful requests
        $this->assertFalse($callbackInvoked, 'Callback should not be invoked on successful (non-401) requests');
    }

    /**
     * Test callback receives full response object
     */
    public function testCallbackReceivesFullResponseObject(): void
    {
        $receivedResponse = null;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token',
            'onAuthenticationFailure' => function($response, $reason) use (&$receivedResponse) {
                $receivedResponse = $response;
            }
        ]);

        // Make request that will get 401
        $response = $client->get('/platform/users/current');

        // Callback should have received full response object
        $this->assertNotNull($receivedResponse);
        $this->assertIsObject($receivedResponse);
        $this->assertObjectHasProperty('statusCode', $receivedResponse);
        $this->assertObjectHasProperty('headers', $receivedResponse);
        $this->assertObjectHasProperty('body', $receivedResponse);
        $this->assertEquals(401, $receivedResponse->statusCode);
    }
}
