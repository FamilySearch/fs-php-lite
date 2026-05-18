<?php

namespace FamilySearch\Tests\Integration;

use PHPUnit\Framework\TestCase;
use FamilySearch;

abstract class ApiTestCase extends TestCase
{
    protected FamilySearch $client;
    private static ?array $personData = null;

    /**
     * Automatically called by PHPUnit before each test is run
     */
    protected function setUp(): void
    {
        $this->client = new FamilySearch([
            'appKey' => SandboxCredentials::API_KEY
        ]);
    }

    /**
     * Authenticate with sandbox via the OAuth2 password flow
     */
    protected function login(): object
    {
        return $this->client->oauthPassword(
            SandboxCredentials::USERNAME,
            SandboxCredentials::PASSWORD
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
        return $response->headers['X-ENTITY-ID'] ?? null;
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
