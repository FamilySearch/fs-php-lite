<?php

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for FamilySearch SDK configuration
 */
class FamilySearchConfigTest extends TestCase
{
    public function testConstructorWithDefaultOptions(): void
    {
        $fs = new FamilySearch();

        $this->assertInstanceOf(FamilySearch::class, $fs);
        $this->assertNull($fs->getAccessToken());
    }

    public function testConstructorWithAccessToken(): void
    {
        $token = 'test-access-token-123';
        $fs = new FamilySearch(['accessToken' => $token]);

        $this->assertEquals($token, $fs->getAccessToken());
    }

    public function testConstructorWithAppKey(): void
    {
        $appKey = 'test-app-key';
        $fs = new FamilySearch(['appKey' => $appKey]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testConstructorWithProductionEnvironment(): void
    {
        $fs = new FamilySearch(['environment' => 'production']);

        $redirectUrl = $fs->oauthRedirectURL();
        $this->assertStringContainsString('ident.familysearch.org', $redirectUrl);
    }

    public function testConstructorWithBetaEnvironment(): void
    {
        $fs = new FamilySearch(['environment' => 'beta']);

        $redirectUrl = $fs->oauthRedirectURL();
        $this->assertStringContainsString('identbeta.familysearch.org', $redirectUrl);
    }

    public function testConstructorWithIntegrationEnvironment(): void
    {
        $fs = new FamilySearch(['environment' => 'integration']);

        $redirectUrl = $fs->oauthRedirectURL();
        $this->assertStringContainsString('integration.familysearch.org', $redirectUrl);
    }

    public function testConstructorWithInvalidEnvironmentDefaultsToIntegration(): void
    {
        $fs = new FamilySearch(['environment' => 'invalid']);

        $redirectUrl = $fs->oauthRedirectURL();
        $this->assertStringContainsString('integration.familysearch.org', $redirectUrl);
    }

    public function testConstructorWithRedirectUri(): void
    {
        $redirectUri = 'https://example.com/callback';
        $appKey = 'test-key';
        $fs = new FamilySearch([
            'appKey' => $appKey,
            'redirectUri' => $redirectUri
        ]);

        $redirectUrl = $fs->oauthRedirectURL();
        $this->assertStringContainsString(urlencode($redirectUri), $redirectUrl);
        $this->assertStringContainsString('client_id=' . $appKey, $redirectUrl);
    }

    public function testOauthRedirectUrlFormat(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test-app-key',
            'redirectUri' => 'https://example.com/callback'
        ]);

        $redirectUrl = $fs->oauthRedirectURL();

        $this->assertStringContainsString('response_type=code', $redirectUrl);
        $this->assertStringContainsString('client_id=test-app-key', $redirectUrl);
        $this->assertStringContainsString('redirect_uri=', $redirectUrl);
    }

    public function testVersionConstant(): void
    {
        $this->assertIsString(FamilySearch::VERSION);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', FamilySearch::VERSION);
    }
}
