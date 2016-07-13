<?php

/**
 * Basic PHP SDK for the FamilySearch API.
 */
class FamilySearch 
{
    
    /**
     * The FamilySearch reference or environment to target. Valid values are
     * 'sandbox', 'beta', and 'production'.
     * 
     * @var string
     */
    private $environment = 'sandbox';
    
    /**
     * The application key assigned when your application was registered.
     * 
     * @var string
     */
    private $appKey;
    
    /**
     * The redirect URI that will be used for OAuth.
     * 
     * @var string
     */
    private $redirectUri;
    
    /**
     * Whether the access token will be stored in and retrieved from $_SESSION.
     * Defaults to true.
     * 
     * @var boolean
     */
    private $sessions = true;
    
    /**
     * Name of the session variable that the access token will be saved in.
     * Defaults to 'FS_ACCESS_TOKEN'
     * 
     * @var string
     */
    private $sessionVariable = 'FS_ACCESS_TOKEN';
    
    /**
     * Access token returned by OAuth
     * 
     * @var string
     */
    private $accessToken;
    
    /**
     * Construct a new FamilySearch Client
     * 
     * @param array $options
     */
    public function __construct($options = array())
    {
        if (isset($options['environment']) && in_array($options['environment'], ['production','beta','sandbox'])) {
            $this->environment = $options['environment'];
        }
        
        if (isset($options['appKey'])) {
            $this->appKey = $options['appKey'];
        }
        
        if (isset($options['redirectUri'])) {
            $this->redirectUri = $options['redirectUri'];
        }
        
        if (isset($options['sessions']) && is_bool($options['sessions'])) {
            $this->sessions = $options['sessions'];
        }
        
        if (isset($options['sessionVariable'])) {
            $this->sessionVariable = $options['sessionVariable'];
        }
        
        if (isset($options['accessToken'])) {
            $this->accessToken = $options['accessToken'];
        }
    }
    
    /**
     * Get the OAuth authorize URL that the user should be redirected to.
     * 
     * @return string
     */
    public function oauthRedirectURL()
    {
        return $this->identHost() . '/cis-web/oauth2/v3/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->appKey,
            'redirect_uri' => $this->redirectUri
        ], '', '&');
    }
    
    /**
     * Begin OAuth by automatically forwarding the user to FamilySearch.
     */
    public function oauthRedirect()
    {
        header('Location: ' . $this->oauthRedirectURL());
        die();
    }
    
    /**
     * Get the ident host name for OAuth
     * 
     * @return string
     */
    private function identHost()
    {
        switch ($this->environment) {
            case 'production':
                return 'https://ident.familysearch.org';
            case 'beta':
                return 'https://identbeta.familysearch.org';
            default:
                return 'https://identint.familysearch.org';
        }
    }
    
}