<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for FamilySearch token expiration tracking functionality
 */
class FamilySearchTokenExpirationTest extends TestCase
{
    /**
     * Test that isTokenExpired returns true when no token is present
     */
    public function testIsTokenExpiredWithNoToken(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $this->assertTrue($fs->isTokenExpired());
    }

    /**
     * Test that getTokenExpirationTime returns null when no token is present
     */
    public function testGetTokenExpirationTimeWithNoToken(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $this->assertNull($fs->getTokenExpirationTime());
    }

    /**
     * Test setAccessToken with null expiresIn (fresh token)
     */
    public function testSetAccessTokenWithNullExpiresIn(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $beforeTime = time();
        $fs->setAccessToken('test-token-123');
        $afterTime = time();

        $this->assertEquals('test-token-123', $fs->getAccessToken());

        $details = $fs->getAccessToken(true);
        $this->assertEquals('test-token-123', $details['token']);
        $this->assertNotNull($details['created']);
        $this->assertNotNull($details['last_activity']);
        $this->assertGreaterThanOrEqual($beforeTime, $details['created']);
        $this->assertLessThanOrEqual($afterTime, $details['created']);
        $this->assertEquals($details['created'], $details['last_activity']);
    }

    /**
     * Test setAccessToken with specific expiresIn value
     */
    public function testSetAccessTokenWithExpiresIn(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        // Set token that expires in 1 hour (3600 seconds)
        $beforeTime = time();
        $fs->setAccessToken('test-token-456', 3600);
        $afterTime = time();

        $this->assertEquals('test-token-456', $fs->getAccessToken());

        $details = $fs->getAccessToken(true);
        // Creation time should be calculated as (now - (24 hours - expiresIn))
        // For expiresIn=3600, creation was 24hrs - 1hr = 23 hours ago
        $expectedCreation = $beforeTime - (86400 - 3600);
        $this->assertGreaterThanOrEqual($expectedCreation - 1, $details['created']);
        $this->assertLessThanOrEqual($expectedCreation + 1, $details['created']);
    }

    /**
     * Test getAccessToken backward compatibility (returns string by default)
     */
    public function testGetAccessTokenBackwardCompatibility(): void
    {
        $fs = new FamilySearch(['sessions' => false]);
        $fs->setAccessToken('my-token');

        $token = $fs->getAccessToken();
        $this->assertIsString($token);
        $this->assertEquals('my-token', $token);
    }

    /**
     * Test getAccessToken with detailed=true returns array
     */
    public function testGetAccessTokenDetailed(): void
    {
        $fs = new FamilySearch(['sessions' => false]);
        $fs->setAccessToken('detailed-token');

        $details = $fs->getAccessToken(true);
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
     * Test getTokenExpirationTime calculates correctly
     */
    public function testGetTokenExpirationTimeCalculation(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $beforeTime = time();
        $fs->setAccessToken('expiration-test-token');
        $afterTime = time();

        $expirationTime = $fs->getTokenExpirationTime();

        // Token should expire 24 hours from creation OR 60 minutes from last activity
        // For a fresh token, both are set to now, so expiration is min(now+24hrs, now+60min) = now+60min
        $expectedExpiration = $beforeTime + 3600; // 60 minutes
        $this->assertGreaterThanOrEqual($expectedExpiration - 1, $expirationTime);
        $this->assertLessThanOrEqual($afterTime + 3600 + 1, $expirationTime);
    }

    /**
     * Test isTokenExpired with fresh token (should not be expired)
     */
    public function testIsTokenExpiredWithFreshToken(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0 // No warning threshold
        ]);

        $fs->setAccessToken('fresh-token');

        $this->assertFalse($fs->isTokenExpired());
    }

    /**
     * Test isTokenExpired with token within warning threshold
     */
    public function testIsTokenExpiredWithinWarningThreshold(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 3600 // 60 minute warning (same as inactivity timeout)
        ]);

        $fs->setAccessToken('warning-token');

        // With 60 minute warning threshold and 60 minute inactivity expiration,
        // token should be considered expired immediately
        $this->assertTrue($fs->isTokenExpired());
    }

    /**
     * Test custom expirationWarningThreshold configuration
     */
    public function testCustomExpirationWarningThreshold(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 600 // 10 minutes
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that default expirationWarningThreshold is 300 seconds
     */
    public function testDefaultExpirationWarningThreshold(): void
    {
        $fs = new FamilySearch(['sessions' => false]);
        $fs->setAccessToken('default-threshold-token');

        // With default 5-minute threshold, fresh token should not be expired
        // (expires in 60 minutes, threshold is 5 minutes)
        $this->assertFalse($fs->isTokenExpired());
    }

    /**
     * Test getAccessToken returns null when no token is set
     */
    public function testGetAccessTokenReturnsNullWithNoToken(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $this->assertNull($fs->getAccessToken());
    }

    /**
     * Test getAccessToken detailed returns null token when no token is set
     */
    public function testGetAccessTokenDetailedWithNoToken(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $details = $fs->getAccessToken(true);
        $this->assertIsArray($details);
        $this->assertNull($details['token']);
        $this->assertNull($details['created']);
        $this->assertNull($details['last_activity']);
        $this->assertNull($details['expires_at']);
        $this->assertTrue($details['is_expired']);
    }

    /**
     * Test that token expiration considers absolute expiration (24 hours)
     */
    public function testTokenExpirationAbsoluteLimit(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        // Set token that was created 23 hours ago (has 1 hour left on absolute expiration)
        // But last activity is now, so inactivity expiration is in 60 minutes
        // Expiration should be min(1 hour from now, 60 minutes from now) = 60 minutes
        $fs->setAccessToken('absolute-test-token', 3600); // 1 hour remaining

        $expirationTime = $fs->getTokenExpirationTime();
        $expectedExpiration = time() + 3600; // Should expire in 60 minutes (inactivity)

        $this->assertEqualsWithDelta($expectedExpiration, $expirationTime, 2);
    }

    /**
     * Test constructor accessToken option initializes timestamps
     */
    public function testConstructorAccessTokenOption(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'accessToken' => 'constructor-token'
        ]);

        // When setting token via constructor without timestamp info,
        // token should be present but timestamps may not be initialized
        $this->assertEquals('constructor-token', $fs->getAccessToken());
    }

    /**
     * Test zero expirationWarningThreshold
     */
    public function testZeroExpirationWarningThreshold(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        $fs->setAccessToken('zero-threshold-token');

        // With zero threshold, only truly expired tokens should return true
        // Fresh token should not be expired
        $this->assertFalse($fs->isTokenExpired());
    }

    /**
     * Test large expirationWarningThreshold (larger than token lifetime)
     */
    public function testLargeExpirationWarningThreshold(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 90000 // 25 hours (more than token lifetime)
        ]);

        $fs->setAccessToken('large-threshold-token');

        // With threshold larger than token lifetime, all tokens should be considered expired
        $this->assertTrue($fs->isTokenExpired());
    }

    /**
     * Test that invalid expirationWarningThreshold types are ignored
     */
    public function testInvalidExpirationWarningThresholdType(): void
    {
        // String should be ignored, default should be used
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 'invalid'
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test negative expiresIn value for setAccessToken
     */
    public function testSetAccessTokenWithNegativeExpiresIn(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        // Negative expiresIn means token already expired
        $fs->setAccessToken('expired-token', -3600);

        // Token should be considered expired
        $details = $fs->getAccessToken(true);
        $this->assertTrue($details['is_expired']);
    }

    /**
     * Test that setting token multiple times updates timestamps
     */
    public function testSetAccessTokenMultipleTimes(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $fs->setAccessToken('first-token');
        $firstDetails = $fs->getAccessToken(true);
        $firstCreated = $firstDetails['created'];

        // Wait a moment and set new token
        sleep(1);

        $fs->setAccessToken('second-token');
        $secondDetails = $fs->getAccessToken(true);
        $secondCreated = $secondDetails['created'];

        $this->assertEquals('second-token', $secondDetails['token']);
        $this->assertGreaterThan($firstCreated, $secondCreated);
    }
}
