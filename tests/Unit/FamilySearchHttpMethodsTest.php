<?php

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for HTTP methods using mocked responses
 */
class FamilySearchHttpMethodsTest extends TestCase
{
    private FamilySearch $client;

    protected function setUp(): void
    {
        $this->client = new FamilySearch([
            'appKey' => 'test-key',
            'accessToken' => 'test-token'
        ]);
    }

    public function testGetAccessToken(): void
    {
        $this->assertEquals('test-token', $this->client->getAccessToken());
    }

    public function testGetAccessTokenWhenNotSet(): void
    {
        $client = new FamilySearch(['appKey' => 'test-key']);
        $this->assertNull($client->getAccessToken());
    }

    public function testOauthRedirectURLStructure(): void
    {
        $client = new FamilySearch([
            'appKey' => 'my-app-key',
            'redirectUri' => 'https://myapp.com/callback',
            'environment' => 'production'
        ]);

        $url = $client->oauthRedirectURL();

        // Parse the URL
        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $this->assertEquals('ident.familysearch.org', $parts['host']);
        $this->assertEquals('code', $query['response_type']);
        $this->assertEquals('my-app-key', $query['client_id']);
        $this->assertEquals('https://myapp.com/callback', $query['redirect_uri']);
    }

    public function testEnvironmentUrls(): void
    {
        $environments = [
            'production' => 'ident.familysearch.org',
            'beta' => 'identbeta.familysearch.org',
            'integration' => 'integration.familysearch.org'
        ];

        foreach ($environments as $env => $expectedHost) {
            $client = new FamilySearch([
                'environment' => $env,
                'appKey' => 'test-key',
                'redirectUri' => 'https://example.com'
            ]);

            $url = $client->oauthRedirectURL();
            $this->assertStringContainsString($expectedHost, $url, "Failed for environment: $env");
        }
    }
}
