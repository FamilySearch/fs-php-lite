<?php

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for FamilySearch request building and configuration
 */
class FamilySearchRequestTest extends TestCase
{
    public function testSessionsEnabledByDefault(): void
    {
        $fs = new FamilySearch(['appKey' => 'test']);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testSessionsCanBeDisabled(): void
    {
        $fs = new FamilySearch(['sessions' => false]);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testCustomSessionVariable(): void
    {
        $fs = new FamilySearch([
            'sessionVariable' => 'MY_CUSTOM_TOKEN'
        ]);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testPendingModificationsArray(): void
    {
        $fs = new FamilySearch([
            'pendingModifications' => ['mod1', 'mod2', 'mod3']
        ]);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testCustomUserAgent(): void
    {
        $fs = new FamilySearch([
            'userAgent' => 'MyCustomApp/2.0.0'
        ]);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testMaxThrottledRetries(): void
    {
        $fs = new FamilySearch([
            'maxThrottledRetries' => 10
        ]);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testObjectsOption(): void
    {
        $fs = new FamilySearch([
            'objects' => true
        ]);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testMultipleConfigurationOptions(): void
    {
        $fs = new FamilySearch([
            'environment' => 'production',
            'appKey' => 'my-app-key',
            'redirectUri' => 'https://example.com/callback',
            'accessToken' => 'my-token',
            'sessions' => false,
            'maxThrottledRetries' => 3,
            'pendingModifications' => ['feature1'],
            'userAgent' => 'TestApp/1.0',
            'objects' => false
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
        $this->assertEquals('my-token', $fs->getAccessToken());
    }

    public function testEmptyOptionsArray(): void
    {
        $fs = new FamilySearch([]);
        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    public function testVersionIsValid(): void
    {
        $version = FamilySearch::VERSION;
        $this->assertIsString($version);
        $this->assertNotEmpty($version);

        // Version should match semver pattern
        $pattern = '/^\d+\.\d+\.\d+$/';
        $this->assertMatchesRegularExpression($pattern, $version);
    }
}
