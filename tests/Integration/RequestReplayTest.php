<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

/**
 * Integration tests for automatic request replay after re-authentication
 *
 * These tests verify that failed requests are automatically retried when the
 * onAuthenticationFailure callback successfully obtains a new token.
 */
class RequestReplayTest extends ApiTestCase
{
    /**
     * Test automatic request replay after successful re-authentication
     */
    public function testAutomaticReplayAfterReauthentication(): void
    {
        $callbackInvoked = false;
        $reauthenticated = false;
        $clientRef = null;

        $creds = $this->getCredentials();

        // Create client with callback that re-authenticates
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-initial-token',
            'replayFailedRequestsAfterAuth' => true, // Enable automatic replay
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$reauthenticated, $creds, &$clientRef) {
                $callbackInvoked = true;

                // Re-authenticate using password grant
                $authResponse = $clientRef->oauthPassword($creds['username'], $creds['password']);

                if ($authResponse->statusCode === 200) {
                    $reauthenticated = true;
                }
            }
        ]);

        // Store reference for callback
        $clientRef = $client;

        // Make request with invalid token
        // Should fail with 401, callback re-authenticates, request is automatically retried
        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked, 'Callback should be invoked on 401');
        $this->assertTrue($reauthenticated, 'Should have re-authenticated in callback');

        // Response should be successful (from replay, not original 401)
        $this->assertLessThan(400, $response->statusCode, 'Replayed request should succeed');

        // Response should have replay metadata
        $this->assertObjectHasProperty('replayed', $response);
        $this->assertTrue($response->replayed, 'Response should be marked as replayed');
        $this->assertObjectHasProperty('originalResponse', $response);
        $this->assertEquals(401, $response->originalResponse->statusCode, 'Original response should be 401');
    }

    /**
     * Test that replay can be disabled
     */
    public function testReplayCanBeDisabled(): void
    {
        $callbackInvoked = false;
        $reauthenticated = false;
        $clientRef = null;

        $creds = $this->getCredentials();

        // Create client with callback that re-authenticates but replay disabled
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-initial-token',
            'replayFailedRequestsAfterAuth' => false, // Disable automatic replay
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$reauthenticated, $creds, &$clientRef) {
                $callbackInvoked = true;

                // Re-authenticate using password grant
                $authResponse = $clientRef->oauthPassword($creds['username'], $creds['password']);

                if ($authResponse->statusCode === 200) {
                    $reauthenticated = true;
                }
            }
        ]);

        // Store reference
        $clientRef = $client;

        // Make request with invalid token
        // Should fail with 401, callback re-authenticates, but NO automatic retry
        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked, 'Callback should be invoked on 401');
        $this->assertTrue($reauthenticated, 'Should have re-authenticated in callback');

        // Response should still be 401 (no replay)
        $this->assertEquals(401, $response->statusCode, 'Should return original 401 when replay disabled');

        // Response should NOT have replay metadata
        $this->assertObjectNotHasProperty('replayed', $response);

        // But token should be valid now, so next request should succeed
        $response2 = $client->get('/platform/users/current');
        $this->assertLessThan(400, $response2->statusCode, 'Next request should succeed with new token');
    }

    /**
     * Test that replay only happens once (no infinite loops)
     */
    public function testReplayOnlyHappensOnce(): void
    {
        $callbackInvocationCount = 0;
        $clientRef = null;

        $creds = $this->getCredentials();

        // Create client with callback that sets an invalid token
        // This will cause the replay to also fail with 401
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-initial-token',
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvocationCount, &$clientRef) {
                $callbackInvocationCount++;

                // Set another invalid token (replay will also fail)
                $clientRef->setAccessToken('still-invalid-token-' . $callbackInvocationCount);
            }
        ]);

        // Store reference
        $clientRef = $client;

        // Make request - should trigger callback, replay, and callback again, but then stop
        $response = $client->get('/platform/users/current');

        // Callback should be invoked twice: once for original, once for replay
        // But no infinite loop - should stop after second failure
        $this->assertLessThanOrEqual(2, $callbackInvocationCount, 'Should not retry infinitely');

        // Final response should still be 401
        $this->assertEquals(401, $response->statusCode);
    }

    /**
     * Test that replay doesn't happen if callback doesn't change token
     */
    public function testNoReplayIfTokenNotChanged(): void
    {
        $callbackInvoked = false;

        $creds = $this->getCredentials();

        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token',
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked) {
                $callbackInvoked = true;
                // Callback does nothing - doesn't obtain new token
            }
        ]);

        // Make request with invalid token
        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked, 'Callback should be invoked');

        // Response should be 401 (no replay because token didn't change)
        $this->assertEquals(401, $response->statusCode);

        // Response should NOT have replay metadata
        $this->assertObjectNotHasProperty('replayed', $response);
    }

    /**
     * Test replay with successful retry
     */
    public function testReplayWithSuccessfulRetry(): void
    {
        $callbackInvoked = false;

        $creds = $this->getCredentials();

        // First authenticate to get a valid token
        $tempClient = new \FamilySearch(['appKey' => $creds['api_key'], 'sessions' => false]);
        $authResponse = $tempClient->oauthPassword($creds['username'], $creds['password']);
        $this->assertResponseOK($authResponse);
        $validToken = $tempClient->getAccessToken();

        $clientRef = null;

        // Create client with invalid token and callback that sets valid token
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-initial-token',
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$clientRef, $validToken) {
                $callbackInvoked = true;
                // Set the valid token obtained earlier
                $clientRef->setAccessToken($validToken);
            }
        ]);

        // Store reference
        $clientRef = $client;

        // Make request - should fail, callback sets valid token, replay succeeds
        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked, 'Callback should be invoked');
        $this->assertLessThan(400, $response->statusCode, 'Replayed request should succeed');
        $this->assertTrue($response->replayed ?? false, 'Response should be marked as replayed');
    }

    /**
     * Test replay with POST request
     */
    public function testReplayWithPostRequest(): void
    {
        $callbackInvoked = false;

        $creds = $this->getCredentials();

        // First authenticate to get a valid token
        $tempClient = new \FamilySearch(['appKey' => $creds['api_key'], 'sessions' => false]);
        $authResponse = $tempClient->oauthPassword($creds['username'], $creds['password']);
        $this->assertResponseOK($authResponse);
        $validToken = $tempClient->getAccessToken();

        $clientRef = null;

        // Create client with invalid token
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-initial-token',
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$clientRef, $validToken) {
                $callbackInvoked = true;
                $clientRef->setAccessToken($validToken);
            }
        ]);

        // Store reference
        $clientRef = $client;

        // Make POST request - should fail, callback sets valid token, replay succeeds
        $response = $client->post('/platform/tree/persons', [
            'body' => $this->personData()
        ]);

        $this->assertTrue($callbackInvoked, 'Callback should be invoked');
        // POST should succeed on replay
        $this->assertLessThan(400, $response->statusCode, 'Replayed POST should succeed');
    }

    /**
     * Test that successful requests don't trigger replay logic
     */
    public function testSuccessfulRequestsNoReplay(): void
    {
        $callbackInvoked = false;

        $creds = $this->getCredentials();

        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true,
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

        // Callback should NOT be invoked
        $this->assertFalse($callbackInvoked, 'Callback should not be invoked on successful requests');

        // Response should NOT have replay metadata
        $this->assertObjectNotHasProperty('replayed', $response);
    }

    /**
     * Test replay with expired token reason
     */
    public function testReplayWithExpiredTokenReason(): void
    {
        $receivedReason = null;
        $callbackInvoked = false;
        $clientRef = null;

        $creds = $this->getCredentials();

        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'expirationWarningThreshold' => 0,
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$receivedReason, $creds, &$clientRef) {
                $callbackInvoked = true;
                $receivedReason = $reason;

                // Re-authenticate
                $clientRef->oauthPassword($creds['username'], $creds['password']);
            }
        ]);

        // Store reference
        $clientRef = $client;

        // Set expired token
        $client->setAccessToken('expired-token', -3600);

        // Make request
        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked);
        $this->assertEquals('expired', $receivedReason, 'Reason should be expired');
        $this->assertLessThan(400, $response->statusCode, 'Replayed request should succeed');
    }
}
