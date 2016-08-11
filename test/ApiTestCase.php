<?php

namespace FamilySearch\Tests;

abstract class ApiTestCase extends \PHPUnit_Framework_TestCase
{
  
    /**
     * @var FamilySearch
     */
    protected $client;
    
    /**
     * @var array
     */
    private static $personData;
    
    /**
     * Automatically called by PHPUnit before each test is run. This resets
     * the client to a fresh non-authenticated state.
     */
    public function setUp()
    {
        $this->client = new \FamilySearch([
            'appKey' => SandboxCredentials::API_KEY
        ]);
    }
    
    /**
     * Authenticate with sandbox via the OAuth2 password flow
     * 
     * @return object response
     */
    public function login()
    {
        return $this->client->oauthPassword(SandboxCredentials::USERNAME, SandboxCredentials::PASSWORD);
    }
    
    /**
     * Create a person and return the person's ID
     *
     * @return string person ID
     */
    public function createPerson()
    {
        $response = $this->client->post('/platform/tree/persons', [
            'body' => $this->personData()    
        ]);
        return $response->headers['X-ENTITY-ID'];
    }
    
    /**
     * Assert that the response has a status code less than 400
     * 
     * @param objct $response
     */
    public function assertResponseOK($response)
    {
        $this->assertObjectHasAttribute('statusCode', $response);
        $this->assertLessThan(400, $response->statusCode);
    }
    
    /**
     * Assert the that response has data parsed from the body
     * 
     * @param object $response
     */
    public function assertResponseData($response)
    {
        $this->assertObjectHasAttribute('data', $response);
    }
    
    /**
     * Get person data
     * 
     * @return array person data
     */
    private function personData()
    {
        if(!isset(self::$personData)){
            self::$personData = $this->loadPersonData();
        }
        return self::$personData;
    }
    
    /**
     * Load person data from disk
     * 
     * @return array person data
     */
    private function loadPersonData()
    {
        return json_decode(file_get_contents(__DIR__ . '/person.json'), true);
    }
}