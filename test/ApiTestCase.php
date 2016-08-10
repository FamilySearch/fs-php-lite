<?php

namespace FamilySearch\Tests;

abstract class ApiTestCase extends \PHPUnit_Framework_TestCase
{
  
    /**
     * @var FamilySearch
     */
    protected $client;
    
    public function setUp()
    {
        $this->client = new \FamilySearch([
            'appKey' => SandboxCredentials::API_KEY
        ]);
    }
    
    public function login()
    {
        return $this->client->oauthPassword(SandboxCredentials::USERNAME, SandboxCredentials::PASSWORD);
    }
  
}