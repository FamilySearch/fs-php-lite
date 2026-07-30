<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for FamilySearch edge cases and error handling
 */
class FamilySearchEdgeCasesTest extends TestCase
{
    public function testConstructorWithAllEnvironments(): void
    {
        $environments = ['production', 'beta', 'integration'];

        foreach ($environments as $env) {
            $fs = new FamilySearch([
                'environment' => $env,
                'sessions' => false
            ]);

            $url = $fs->oauthRedirectURL();

            // Each environment should have its own URL
            $this->assertIsString($url);
            $this->assertStringContainsString('cis-web/oauth2/v3/authorization', $url);
        }
    }

    public function testConstructorWithInvalidEnvironment(): void
    {
        $fs = new FamilySearch([
            'environment' => 'invalid-env',
            'sessions' => false
        ]);

        // Should default to integration
        $url = $fs->oauthRedirectURL();
        $this->assertStringContainsString('integration.familysearch.org', $url);
    }

    public function testConstructorWithEmptyOptions(): void
    {
        $fs = new FamilySearch();

        // Should work with defaults
        $this->assertInstanceOf(FamilySearch::class, $fs);
        $this->assertNull($fs->getAccessToken());
    }

    public function testConstructorWithNullValues(): void
    {
        $fs = new FamilySearch([
            'accessToken' => null,
            'appKey' => null,
            'redirectUri' => null,
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithSessionsTrue(): void
    {
        // Start a session for this test
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $fs = new FamilySearch([
            'sessions' => true,
            'sessionVariable' => 'TEST_TOKEN',
            'accessToken' => 'test-token-123'
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
        $this->assertEquals('test-token-123', $fs->getAccessToken());

        // Clean up
        unset($_SESSION['TEST_TOKEN']);
    }

    public function testConstructorRetrievesTokenFromSession(): void
    {
        // Start a session and set a token
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['FS_ACCESS_TOKEN'] = 'session-token-456';

        $fs = new FamilySearch([
            'sessions' => true
        ]);

        $this->assertEquals('session-token-456', $fs->getAccessToken());

        // Clean up
        unset($_SESSION['FS_ACCESS_TOKEN']);
    }

    public function testConstructorWithCustomUserAgentEmpty(): void
    {
        $fs = new FamilySearch([
            'userAgent' => '',
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithMaxThrottledRetriesVeryHigh(): void
    {
        $fs = new FamilySearch([
            'maxThrottledRetries' => 100,
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testVersionConstantFormat(): void
    {
        // Test that VERSION constant exists and is in correct format
        $this->assertIsString(FamilySearch::VERSION);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', FamilySearch::VERSION);

        // Verify version number makes sense (not empty, not 0.0.0)
        $this->assertNotEquals('0.0.0', FamilySearch::VERSION);
    }

    public function testOauthRedirectURLFormat(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test123',
            'redirectUri' => 'https://example.com/callback',
            'sessions' => false
        ]);

        $url = $fs->oauthRedirectURL();

        // Parse URL
        $parsed = parse_url($url);

        $this->assertEquals('https', $parsed['scheme']);
        $this->assertNotEmpty($parsed['host']);
        $this->assertNotEmpty($parsed['path']);
        $this->assertNotEmpty($parsed['query']);

        // Parse query string
        parse_str($parsed['query'], $query);

        $this->assertEquals('code', $query['response_type']);
        $this->assertEquals('test123', $query['client_id']);
        $this->assertEquals('https://example.com/callback', $query['redirect_uri']);
    }

    public function testConstructorWithBooleanFalseOptions(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'objects' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithNonBooleanSessionsValue(): void
    {
        // sessions should only be set if it's a boolean
        $fs = new FamilySearch([
            'sessions' => 'true', // string, not boolean
            'appKey' => 'test'
        ]);

        // Should still construct successfully
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithNonBooleanObjectsValue(): void
    {
        // objects should only be set if it's a boolean
        $fs = new FamilySearch([
            'objects' => 'true', // string, not boolean
            'sessions' => false
        ]);

        // Should still construct successfully
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testOauthRedirectURLWithSpecialCharactersInRedirectUri(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test-key',
            'redirectUri' => 'https://example.com/callback?foo=bar&baz=qux',
            'sessions' => false
        ]);

        $url = $fs->oauthRedirectURL();

        // Should properly encode the redirect URI
        $this->assertStringContainsString(urlencode('https://example.com/callback?foo=bar&baz=qux'), $url);
    }

    public function testConstructorWithEmptyPendingModifications(): void
    {
        $fs = new FamilySearch([
            'pendingModifications' => [],
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithMultiplePendingModifications(): void
    {
        $fs = new FamilySearch([
            'pendingModifications' => ['mod1', 'mod2', 'mod3', 'mod4'],
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }
}
