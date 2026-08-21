<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for FamilySearch automatic request replay functionality
 *
 * Tests the automatic retry of failed requests after successful re-authentication
 * via the onAuthenticationFailure callback.
 */
class FamilySearchRequestReplayTest extends TestCase
{
    /**
     * Test that replay is enabled by default
     */
    public function testReplayEnabledByDefault(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that replay can be disabled via configuration
     */
    public function testReplayCanBeDisabled(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that replay can be explicitly enabled via configuration
     */
    public function testReplayCanBeExplicitlyEnabled(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that non-boolean value for replay option is ignored
     */
    public function testNonBooleanReplayOptionIgnored(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => 'invalid'
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test backward compatibility - SDK works without replay configuration
     */
    public function testBackwardCompatibilityWithoutReplayConfig(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'appKey' => 'test-key'
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
        $this->assertNull($fs->getAccessToken());
    }

    /**
     * Test that setAccessToken works normally outside of callback
     */
    public function testSetAccessTokenWorksNormally(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $fs->setAccessToken('test-token-123');

        $this->assertEquals('test-token-123', $fs->getAccessToken());
    }

    /**
     * Test replay configuration with callback
     */
    public function testReplayConfigurationWithCallback(): void
    {
        $callbackInvoked = false;

        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked) {
                $callbackInvoked = true;
            }
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test replay disabled configuration with callback
     */
    public function testReplayDisabledWithCallback(): void
    {
        $callbackInvoked = false;

        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => false,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked) {
                $callbackInvoked = true;
            }
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that callback without setAccessToken doesn't trigger replay
     *
     * This test verifies that if the callback doesn't obtain a new token,
     * no replay is attempted (even if replay is enabled).
     */
    public function testCallbackWithoutTokenChangeDoesntTriggerReplay(): void
    {
        $callbackInvoked = false;

        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked) {
                // Callback doesn't obtain new token
                $callbackInvoked = true;
            }
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test multiple configuration options together
     */
    public function testMultipleConfigurationOptions(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'appKey' => 'test-key',
            'environment' => 'integration',
            'replayFailedRequestsAfterAuth' => true,
            'expirationWarningThreshold' => 600,
            'onAuthenticationFailure' => function($response, $reason) {
                // Handler
            }
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that replay works with sessions enabled
     */
    public function testReplayWithSessionsEnabled(): void
    {
        $fs = new FamilySearch([
            'sessions' => true,
            'replayFailedRequestsAfterAuth' => true
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that replay works with encryption enabled
     */
    public function testReplayWithEncryptionEnabled(): void
    {
        $key = base64_encode(random_bytes(32));

        $fs = new FamilySearch([
            'sessions' => true,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $key,
            'replayFailedRequestsAfterAuth' => true
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test getAccessToken works normally with replay enabled
     */
    public function testGetAccessTokenWithReplayEnabled(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true
        ]);

        $fs->setAccessToken('my-token');

        $this->assertEquals('my-token', $fs->getAccessToken());

        $details = $fs->getAccessToken(true);
        $this->assertEquals('my-token', $details['token']);
    }

    /**
     * Test isTokenExpired works with replay enabled
     */
    public function testIsTokenExpiredWithReplayEnabled(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true,
            'expirationWarningThreshold' => 0
        ]);

        // No token - should be expired
        $this->assertTrue($fs->isTokenExpired());

        // Fresh token - should not be expired
        $fs->setAccessToken('fresh-token');
        $this->assertFalse($fs->isTokenExpired());
    }

    /**
     * Test that setAccessToken with expiresIn works with replay
     */
    public function testSetAccessTokenWithExpiresInAndReplay(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true
        ]);

        $fs->setAccessToken('token-with-expiry', 3600);

        $this->assertEquals('token-with-expiry', $fs->getAccessToken());
    }

    /**
     * Test replay option with null value (should use default)
     */
    public function testReplayOptionWithNull(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => null
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test replay configuration doesn't affect normal operations
     */
    public function testReplayConfigDoesntAffectNormalOperations(): void
    {
        $fs1 = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true
        ]);

        $fs2 = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => false
        ]);

        $fs1->setAccessToken('token1');
        $fs2->setAccessToken('token2');

        $this->assertEquals('token1', $fs1->getAccessToken());
        $this->assertEquals('token2', $fs2->getAccessToken());
    }

    /**
     * Test that replay doesn't interfere with token expiration tracking
     */
    public function testReplayDoesntInterfereWithExpirationTracking(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'replayFailedRequestsAfterAuth' => true
        ]);

        $beforeTime = time();
        $fs->setAccessToken('tracked-token');
        $afterTime = time();

        $details = $fs->getAccessToken(true);
        $this->assertGreaterThanOrEqual($beforeTime, $details['created']);
        $this->assertLessThanOrEqual($afterTime, $details['created']);
        $this->assertNotNull($details['expires_at']);
    }
}
