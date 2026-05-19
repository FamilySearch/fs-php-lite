<?php

namespace FamilySearch\Tests\Integration;

use PHPUnit\Framework\TestCase;
use FamilySearch;

abstract class ApiTestCase extends TestCase
{
    protected FamilySearch $client;
    private static ?array $personData = null;

    /**
     * Get credentials from environment or SandboxCredentials class
     */
    protected function getCredentials(): array
    {
        // Try environment variables first (recommended)
        $username = getenv('FAMILYSEARCH_USERNAME');
        $password = getenv('FAMILYSEARCH_PASSWORD');
        $apiKey = getenv('FAMILYSEARCH_API_KEY');
        $redirectUri = getenv('FAMILYSEARCH_REDIRECT_URI');

        // Fall back to SandboxCredentials if it exists and has values
        if (empty($apiKey) && class_exists('FamilySearch\Tests\Integration\SandboxCredentials')) {
            $username = $username ?: SandboxCredentials::USERNAME;
            $password = $password ?: SandboxCredentials::PASSWORD;
            $apiKey = $apiKey ?: SandboxCredentials::API_KEY;
            $redirectUri = $redirectUri ?: SandboxCredentials::REDIRECT_URI;
        }

        return [
            'username' => $username ?: '',
            'password' => $password ?: '',
            'api_key' => $apiKey ?: '',
            'redirect_uri' => $redirectUri ?: 'http://example.com/redirect',
        ];
    }

    /**
     * Check if credentials are available for testing
     */
    protected function hasCredentials(): bool
    {
        $creds = $this->getCredentials();
        return !empty($creds['api_key']) && !empty($creds['username']) && !empty($creds['password']);
    }

    /**
     * Automatically called by PHPUnit before each test is run
     */
    protected function setUp(): void
    {
        $creds = $this->getCredentials();
        $this->client = new FamilySearch([
            'appKey' => $creds['api_key']
        ]);
    }

    /**
     * Authenticate with sandbox via the OAuth2 password flow
     */
    protected function login(): object
    {
        $creds = $this->getCredentials();
        return $this->client->oauthPassword(
            $creds['username'],
            $creds['password']
        );
    }

    /**
     * Create a person and return the person's ID
     */
    protected function createPerson(): ?string
    {
        $response = $this->client->post('/platform/tree/persons', [
            'body' => $this->personData()
        ]);
        // HTTP/2 lowercases headers, so check both uppercase and lowercase variants
        return $response->headers['X-ENTITY-ID'] ?? $response->headers['x-entity-id'] ?? null;
    }

    /**
     * Assert that the response has a status code less than 400
     */
    protected function assertResponseOK(object $response): void
    {
        $this->assertObjectHasProperty('statusCode', $response);
        $this->assertLessThan(400, $response->statusCode);
    }

    /**
     * Assert that response has data parsed from the body
     */
    protected function assertResponseData(object $response): void
    {
        $this->assertObjectHasProperty('data', $response);
    }

    /**
     * Get person data
     */
    protected function personData(): array
    {
        if (self::$personData === null) {
            self::$personData = $this->loadPersonData();
        }
        return self::$personData;
    }

    /**
     * Load person data from disk
     */
    private function loadPersonData(): array
    {
        $json = file_get_contents(__DIR__ . '/../fixtures/person.json');
        return json_decode($json, true);
    }
}
