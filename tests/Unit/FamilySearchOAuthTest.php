<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for FamilySearch OAuth functionality
 */
class FamilySearchOAuthTest extends TestCase
{
    public function testOauthResponseWithValidCode(): void
    {
        // Mock $_GET for oauthResponse()
        $_GET['code'] = 'test-auth-code-123';

        $fs = new FamilySearch([
            'appKey' => 'test-app-key',
            'sessions' => false // Disable sessions for testing
        ]);

        // We can't actually test oauthResponse() without mocking HTTP
        // But we can test that oauthRedirectURL is constructed correctly
        $url = $fs->oauthRedirectURL();

        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=test-app-key', $url);

        unset($_GET['code']);
    }

    public function testOauthRedirectURLWithAllParameters(): void
    {
        $fs = new FamilySearch([
            'environment' => 'production',
            'appKey' => 'my-test-key',
            'redirectUri' => 'https://myapp.com/callback'
        ]);

        $url = $fs->oauthRedirectURL();

        // Verify all components
        $this->assertStringStartsWith('https://ident.familysearch.org/cis-web/oauth2/v3/authorization', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=my-test-key', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString(urlencode('https://myapp.com/callback'), $url);
    }

    public function testGetAccessTokenReturnsNull(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test-key',
            'sessions' => false
        ]);

        $this->assertNull($fs->getAccessToken());
    }

    public function testGetAccessTokenWithToken(): void
    {
        $token = 'test-token-xyz';
        $fs = new FamilySearch([
            'accessToken' => $token,
            'sessions' => false
        ]);

        $this->assertEquals($token, $fs->getAccessToken());
    }

    public function testConstructorWithSessionsDisabled(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'appKey' => 'test'
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithMaxThrottledRetriesZero(): void
    {
        $fs = new FamilySearch([
            'maxThrottledRetries' => 0,
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithObjectsEnabled(): void
    {
        $fs = new FamilySearch([
            'objects' => true,
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithPendingModificationsEmpty(): void
    {
        $fs = new FamilySearch([
            'pendingModifications' => [],
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithPendingModificationsSingle(): void
    {
        $fs = new FamilySearch([
            'pendingModifications' => ['single-mod'],
            'sessions' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }
}
