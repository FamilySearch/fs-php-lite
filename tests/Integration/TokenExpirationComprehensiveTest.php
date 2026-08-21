<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

/**
 * Comprehensive integration tests for token expiration tracking
 *
 * This test suite covers all aspects of the token expiration system:
 * - Token creation and activity tracking
 * - Absolute expiration (24 hours)
 * - Inactivity expiration (60 minutes)
 * - Near-expiration detection with thresholds
 * - Authentication failure callbacks
 * - Request replay after re-authentication
 * - Backward compatibility
 * - Edge cases
 */
class TokenExpirationComprehensiveTest extends ApiTestCase
{
    // ========================================================================
    // Token Tracking Tests
    // ========================================================================

    /**
     * Test 1: Token creation timestamp is recorded when setAccessToken() is called
     */
    public function testTokenCreationTimestampRecorded(): void
    {
        $client = new \FamilySearch(['sessions' => false]);

        $beforeTime = time();
        $client->setAccessToken('test-token');
        $afterTime = time();

        $details = $client->getAccessToken(true);

        $this->assertNotNull($details['created'], 'Creation timestamp should be recorded');
        $this->assertGreaterThanOrEqual($beforeTime, $details['created']);
        $this->assertLessThanOrEqual($afterTime, $details['created']);
    }

    /**
     * Test 2: Last activity timestamp is updated on successful API calls
     */
    public function testLastActivityUpdatedOnSuccessfulCalls(): void
    {
        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false
        ]);

        // Authenticate
        $authResponse = $client->oauthPassword($creds['username'], $creds['password']);
        $this->assertResponseOK($authResponse);

        // Get initial timestamps
        $details1 = $client->getAccessToken(true);
        $initialActivity = $details1['last_activity'];

        // Wait a moment to ensure timestamp difference
        sleep(1);

        // Make successful API call
        $response = $client->get('/platform/users/current');
        $this->assertResponseOK($response);

        // Check that last activity was updated
        $details2 = $client->getAccessToken(true);
        $this->assertGreaterThan($initialActivity, $details2['last_activity'],
            'Last activity should be updated after successful API call');
    }

    /**
     * Test 3: Last activity timestamp is NOT updated on 401 or error responses
     */
    public function testLastActivityNotUpdatedOn401(): void
    {
        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token-will-get-401'
        ]);

        // Get initial timestamps
        $details1 = $client->getAccessToken(true);
        $initialActivity = $details1['last_activity'];

        // Wait a moment
        sleep(1);

        // Make request that will get 401
        $response = $client->get('/platform/users/current');
        $this->assertEquals(401, $response->statusCode);

        // Check that last activity was NOT updated
        $details2 = $client->getAccessToken(true);
        $this->assertEquals($initialActivity, $details2['last_activity'],
            'Last activity should NOT be updated on 401 response');
    }

    /**
     * Test 4: isTokenExpired() returns false for fresh tokens
     */
    public function testFreshTokenNotExpired(): void
    {
        $client = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0 // No warning threshold
        ]);

        $client->setAccessToken('fresh-token');

        $this->assertFalse($client->isTokenExpired(),
            'Fresh token should not be considered expired');
    }

    /**
     * Test 5: isTokenExpired() returns true after 24 hours (absolute expiration)
     */
    public function testTokenExpiredAfter24Hours(): void
    {
        $client = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        // Set token that was created 24+ hours ago
        // Using negative expiresIn means token expired X seconds ago
        $client->setAccessToken('expired-token', -1); // Expired 1 second ago

        $this->assertTrue($client->isTokenExpired(),
            'Token created 24+ hours ago should be expired');
    }

    /**
     * Test 6: isTokenExpired() returns true after 60 minutes of inactivity
     */
    public function testTokenExpiredAfter60MinutesInactivity(): void
    {
        $client = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        // Create a token, then manually set last activity to 61 minutes ago
        $client->setAccessToken('inactive-token');

        // Access internal state via reflection to simulate 61 minutes of inactivity
        $reflection = new \ReflectionClass($client);
        $lastActivityProp = $reflection->getProperty('tokenLastActivityTime');
        $lastActivityProp->setAccessible(true);
        $lastActivityProp->setValue($client, time() - 3660); // 61 minutes ago

        $this->assertTrue($client->isTokenExpired(),
            'Token with 60+ minutes of inactivity should be expired');
    }

    /**
     * Test 7: isTokenExpired() returns false if activity occurred within 60 minutes
     */
    public function testTokenNotExpiredIfRecentActivity(): void
    {
        $client = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        // Set token created 2 hours ago (past 60 minutes)
        $client->setAccessToken('old-but-active-token', 86400 - 7200); // Created 2 hours ago

        // But manually set last activity to 30 minutes ago (recent)
        $reflection = new \ReflectionClass($client);
        $lastActivityProp = $reflection->getProperty('tokenLastActivityTime');
        $lastActivityProp->setAccessible(true);
        $lastActivityProp->setValue($client, time() - 1800); // 30 minutes ago

        $this->assertFalse($client->isTokenExpired(),
            'Token with recent activity (< 60min) should not be expired');
    }

    /**
     * Test 8: getTokenExpirationTime() returns correct Unix timestamp
     */
    public function testGetTokenExpirationTimeReturnsCorrectTimestamp(): void
    {
        $client = new \FamilySearch(['sessions' => false]);

        $beforeTime = time();
        $client->setAccessToken('test-token');
        $afterTime = time();

        $expirationTime = $client->getTokenExpirationTime();

        // For fresh token, expiration should be ~60 minutes from now
        // (inactivity expiration is sooner than 24-hour absolute)
        $expectedExpiration = $beforeTime + 3600; // 60 minutes
        $this->assertGreaterThanOrEqual($expectedExpiration - 2, $expirationTime);
        $this->assertLessThanOrEqual($afterTime + 3600 + 2, $expirationTime);
    }

    // ========================================================================
    // Expiration Threshold Tests
    // ========================================================================

    /**
     * Test 9: Token near-expiration detection (within threshold, default 5 minutes)
     */
    public function testNearExpirationDetection(): void
    {
        $client = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 300 // 5 minutes (default)
        ]);

        // Set token that expires in 4 minutes (within threshold)
        // Token was created (60 - 4) = 56 minutes ago
        $client->setAccessToken('near-expiry-token', 240); // 4 minutes remaining

        $this->assertTrue($client->isTokenExpired(),
            'Token within 5-minute threshold should be considered expired');
    }

    /**
     * Test 10: Custom threshold configuration works correctly
     */
    public function testCustomThresholdConfiguration(): void
    {
        $client = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 600 // 10 minutes
        ]);

        // Token expires in 8 minutes (within 10-minute threshold)
        $client->setAccessToken('custom-threshold-token', 480); // 8 minutes remaining

        $this->assertTrue($client->isTokenExpired(),
            'Token within custom threshold should be considered expired');
    }

    /**
     * Test 11: Near-expiration detection for both 24-hour and 60-minute boundaries
     */
    public function testNearExpirationBothBoundaries(): void
    {
        // Test near 60-minute boundary
        $client1 = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 300 // 5 minutes
        ]);

        $client1->setAccessToken('near-60min-boundary', 240); // 4 minutes until inactivity expiration
        $this->assertTrue($client1->isTokenExpired(),
            'Token near 60-minute inactivity boundary should be detected');

        // Test near 24-hour boundary
        $client2 = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 300 // 5 minutes
        ]);

        // Token created almost 24 hours ago (4 minutes until absolute expiration)
        // expiresIn = 240 means 4 minutes left, so created (24hrs - 4min) ago
        $client2->setAccessToken('near-24hr-boundary', 240);

        // Manually update last activity to be recent (so inactivity doesn't trigger)
        $reflection = new \ReflectionClass($client2);
        $lastActivityProp = $reflection->getProperty('tokenLastActivityTime');
        $lastActivityProp->setAccessible(true);
        $lastActivityProp->setValue($client2, time() - 60); // 1 minute ago

        // Token should still be considered expired due to absolute expiration threshold
        $this->assertTrue($client2->isTokenExpired(),
            'Token near 24-hour absolute boundary should be detected');
    }

    // ========================================================================
    // Callback Tests
    // ========================================================================

    /**
     * Test 12: onAuthenticationFailure callback triggered on 401 with correct parameters
     */
    public function testCallbackTriggeredOn401WithCorrectParameters(): void
    {
        $callbackInvoked = false;
        $receivedResponse = null;
        $receivedReason = null;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token',
            'replayFailedRequestsAfterAuth' => false, // Disable replay for this test
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, &$receivedResponse, &$receivedReason) {
                $callbackInvoked = true;
                $receivedResponse = $response;
                $receivedReason = $reason;
            }
        ]);

        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked, 'Callback should be invoked on 401');
        $this->assertIsObject($receivedResponse, 'Response parameter should be an object');
        $this->assertEquals(401, $receivedResponse->statusCode);
        $this->assertIsString($receivedReason, 'Reason should be a string');
        $this->assertContains($receivedReason, ['expired', 'invalid']);
    }

    /**
     * Test 13: Callback receives 'expired' reason when isTokenExpired() returns true
     */
    public function testCallbackReceivesExpiredReasonForExpiredToken(): void
    {
        $receivedReason = null;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'expirationWarningThreshold' => 0,
            'replayFailedRequestsAfterAuth' => false,
            'onAuthenticationFailure' => function($response, $reason) use (&$receivedReason) {
                $receivedReason = $reason;
            }
        ]);

        // Set token that appears expired
        $client->setAccessToken('expired-token', -3600); // Expired 1 hour ago

        $response = $client->get('/platform/users/current');

        $this->assertEquals('expired', $receivedReason,
            'Callback should receive "expired" reason for expired token');
    }

    /**
     * Test 14: Callback receives 'invalid' reason when isTokenExpired() returns false
     */
    public function testCallbackReceivesInvalidReasonForNonExpiredToken(): void
    {
        $receivedReason = null;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'expirationWarningThreshold' => 0,
            'replayFailedRequestsAfterAuth' => false,
            'onAuthenticationFailure' => function($response, $reason) use (&$receivedReason) {
                $receivedReason = $reason;
            }
        ]);

        // Set fresh token that's invalid (not expired, just wrong)
        $client->setAccessToken('invalid-but-not-expired-token');

        $response = $client->get('/platform/users/current');

        $this->assertEquals('invalid', $receivedReason,
            'Callback should receive "invalid" reason for non-expired but invalid token');
    }

    /**
     * Test 15: Request replay after token refresh in callback
     */
    public function testRequestReplayAfterTokenRefreshInCallback(): void
    {
        $callbackInvoked = false;
        $clientRef = null;

        $creds = $this->getCredentials();

        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token',
            'replayFailedRequestsAfterAuth' => true, // Enable replay
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked, $creds, &$clientRef) {
                $callbackInvoked = true;
                // Re-authenticate to get new token
                $clientRef->oauthPassword($creds['username'], $creds['password']);
            }
        ]);

        $clientRef = $client;

        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked);
        $this->assertLessThan(400, $response->statusCode, 'Request should succeed after replay');
        $this->assertTrue($response->replayed ?? false, 'Response should be marked as replayed');
    }

    /**
     * Test 16: No replay when token is not refreshed in callback
     */
    public function testNoReplayWhenTokenNotRefreshed(): void
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
                // Callback doesn't obtain new token
            }
        ]);

        $response = $client->get('/platform/users/current');

        $this->assertTrue($callbackInvoked);
        $this->assertEquals(401, $response->statusCode, 'Should return 401 when no token refresh');
        $this->assertObjectNotHasProperty('replayed', $response);
    }

    /**
     * Test 17: Replay only happens once (second 401 doesn't retry again)
     */
    public function testReplayOnlyHappensOnce(): void
    {
        $callbackCount = 0;
        $clientRef = null;

        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'accessToken' => 'invalid-token-1',
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackCount, &$clientRef) {
                $callbackCount++;
                // Set another invalid token (replay will fail too)
                $clientRef->setAccessToken('invalid-token-' . ($callbackCount + 1));
            }
        ]);

        $clientRef = $client;

        $response = $client->get('/platform/users/current');

        // Callback invoked twice: once for original, once for replay
        // But should not infinite loop
        $this->assertLessThanOrEqual(2, $callbackCount,
            'Callback should not be invoked more than twice (original + one replay)');
        $this->assertEquals(401, $response->statusCode);
    }

    // ========================================================================
    // Backward Compatibility Tests
    // ========================================================================

    /**
     * Test 18: Existing code using getAccessToken() as string still works
     */
    public function testBackwardCompatibilityGetAccessTokenAsString(): void
    {
        $client = new \FamilySearch(['sessions' => false]);
        $client->setAccessToken('my-token');

        $token = $client->getAccessToken();

        $this->assertIsString($token, 'getAccessToken() should return string by default');
        $this->assertEquals('my-token', $token);
    }

    /**
     * Test 19: Enhanced getAccessToken() returns array with token and expiration info
     */
    public function testEnhancedGetAccessTokenReturnsArray(): void
    {
        $client = new \FamilySearch(['sessions' => false]);
        $client->setAccessToken('detailed-token');

        $details = $client->getAccessToken(true);

        $this->assertIsArray($details);
        $this->assertArrayHasKey('token', $details);
        $this->assertArrayHasKey('created', $details);
        $this->assertArrayHasKey('last_activity', $details);
        $this->assertArrayHasKey('expires_at', $details);
        $this->assertArrayHasKey('is_expired', $details);

        $this->assertEquals('detailed-token', $details['token']);
        $this->assertIsInt($details['created']);
        $this->assertIsInt($details['last_activity']);
        $this->assertIsInt($details['expires_at']);
        $this->assertIsBool($details['is_expired']);
    }

    /**
     * Test 20: setAccessToken($token, $expiresIn) properly stores expiration time
     */
    public function testSetAccessTokenWithExpiresInStoresExpiration(): void
    {
        $client = new \FamilySearch(['sessions' => false]);

        // Set token that expires in 1 hour
        $beforeTime = time();
        $client->setAccessToken('token-with-expiry', 3600);
        $afterTime = time();

        $expirationTime = $client->getTokenExpirationTime();

        // Should expire approximately 1 hour from now
        $expectedExpiration = $beforeTime + 3600;
        $this->assertGreaterThanOrEqual($expectedExpiration - 2, $expirationTime);
        $this->assertLessThanOrEqual($afterTime + 3600 + 2, $expirationTime);
    }

    /**
     * Test 21: setAccessToken($token) without expiresIn uses 24-hour default
     */
    public function testSetAccessTokenWithoutExpiresInUses24HourDefault(): void
    {
        $client = new \FamilySearch(['sessions' => false]);

        $beforeTime = time();
        $client->setAccessToken('default-expiry-token');
        $afterTime = time();

        $details = $client->getAccessToken(true);

        // Creation time should be now
        $this->assertGreaterThanOrEqual($beforeTime, $details['created']);
        $this->assertLessThanOrEqual($afterTime, $details['created']);

        // Expiration should be ~60 minutes (inactivity) not 24 hours (absolute)
        // For fresh token, inactivity expiration is sooner
        $expirationTime = $client->getTokenExpirationTime();
        $expectedInactivityExpiration = $beforeTime + 3600; // 60 minutes
        $this->assertGreaterThanOrEqual($expectedInactivityExpiration - 2, $expirationTime);
        $this->assertLessThanOrEqual($afterTime + 3600 + 2, $expirationTime);
    }

    // ========================================================================
    // Edge Cases
    // ========================================================================

    /**
     * Test 22: Tokens already expired when set (past timestamp)
     */
    public function testTokenAlreadyExpiredWhenSet(): void
    {
        $client = new \FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        // Set token that expired 2 hours ago
        $client->setAccessToken('already-expired-token', -7200);

        $this->assertTrue($client->isTokenExpired(),
            'Token that was already expired should be considered expired');

        $expirationTime = $client->getTokenExpirationTime();
        $this->assertLessThan(time(), $expirationTime,
            'Expiration time should be in the past');
    }

    /**
     * Test 23: OAuth response without expires_in (FamilySearch normal behavior)
     */
    public function testOAuthResponseWithoutExpiresIn(): void
    {
        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false
        ]);

        $beforeTime = time();
        $response = $client->oauthPassword($creds['username'], $creds['password']);
        $afterTime = time();

        $this->assertResponseOK($response);

        // FamilySearch OAuth doesn't return expires_in, but SDK should track it
        $details = $client->getAccessToken(true);
        $this->assertNotNull($details['created']);
        $this->assertNotNull($details['last_activity']);
        $this->assertNotNull($details['expires_at']);

        // Timestamps should be recent
        $this->assertGreaterThanOrEqual($beforeTime, $details['created']);
        $this->assertLessThanOrEqual($afterTime, $details['created']);
    }

    /**
     * Test 24: Activity updates extend inactivity window but not absolute expiration
     */
    public function testActivityExtendsInactivityButNotAbsoluteExpiration(): void
    {
        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false
        ]);

        // Authenticate
        $client->oauthPassword($creds['username'], $creds['password']);

        // Manually set creation time to 23 hours ago (close to absolute expiration)
        $reflection = new \ReflectionClass($client);
        $createdProp = $reflection->getProperty('tokenCreationTime');
        $createdProp->setAccessible(true);
        $createdProp->setValue($client, time() - 82800); // 23 hours ago

        // Set last activity to recent (30 minutes ago)
        $lastActivityProp = $reflection->getProperty('tokenLastActivityTime');
        $lastActivityProp->setAccessible(true);
        $lastActivityProp->setValue($client, time() - 1800); // 30 minutes ago

        $expirationTime = $client->getTokenExpirationTime();

        // Expiration should be based on absolute (creation + 24hrs), not inactivity
        // Because absolute expiration (1 hour from now) is sooner than inactivity (30 minutes from now)
        $absoluteExpiration = (time() - 82800) + 86400; // Creation + 24 hours
        $inactivityExpiration = (time() - 1800) + 3600; // Last activity + 60 minutes

        $expected = min($absoluteExpiration, $inactivityExpiration);
        $this->assertEqualsWithDelta($expected, $expirationTime, 2,
            'Expiration should be the sooner of absolute or inactivity');
    }

    /**
     * Test 25: Token expiring between checking isTokenExpired() and making request
     */
    public function testTokenExpiringBetweenCheckAndRequest(): void
    {
        $creds = $this->getCredentials();
        $client = new \FamilySearch([
            'appKey' => $creds['api_key'],
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        // Authenticate
        $client->oauthPassword($creds['username'], $creds['password']);

        // Check token is not expired
        $this->assertFalse($client->isTokenExpired(), 'Fresh token should not be expired');

        // Make request immediately (token still valid)
        $response = $client->get('/platform/users/current');
        $this->assertResponseOK($response);

        // Now manually expire the token by setting last activity to past
        $reflection = new \ReflectionClass($client);
        $lastActivityProp = $reflection->getProperty('tokenLastActivityTime');
        $lastActivityProp->setAccessible(true);
        $lastActivityProp->setValue($client, time() - 3660); // 61 minutes ago

        // Token should now be expired
        $this->assertTrue($client->isTokenExpired(), 'Token should be expired after inactivity');

        // Next request should fail with 401 (simulating the edge case scenario)
        // In real usage, the callback would handle re-authentication
    }
}
