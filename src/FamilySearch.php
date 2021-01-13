<?php

/**
 * Basic PHP SDK for the FamilySearch API.
 */
class FamilySearch 
{
    
    const VERSION = '1.2.0';
    
    /**
     * The FamilySearch reference or environment to target. Valid values are
     * 'integration', 'beta', and 'production'.
     * 
     * @var string
     */
    private $environment = 'integration';
    
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
     * Maximum number of times to retry when being throttled
     * 
     * @var integer
     */
    private $maxThrottledRetries = 5;
    
    /**
     * Pending modifications
     * 
     * @var string
     */
    private $pendingModifications;
    
    /**
     * User agent
     * 
     * @var string
     */
    private $userAgent;
    
    /*
     * Whether we will use gedcomx-php objects for requests and responses
     * 
     * @var boolean
     */
    private $objects = false;
    
    /**
     * Construct a new FamilySearch Client
     * 
     * @param array $options
     */
    public function __construct($options = array())
    {
        if (isset($options['environment']) && in_array($options['environment'], ['production','beta','integration'])) {
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
        
        if (isset($options['pendingModifications'])) {
            $this->pendingModifications = implode(',', $options['pendingModifications']);
        }
        
        $this->userAgent = self::defaultUseragent();
        if (isset($options['userAgent'])) {
            $this->userAgent .= ' ' . $options['userAgent'];
        }
        
        // Load the access token from the session first so that it can be
        // overwritten by the accessToken option
        if ($this->sessions && isset($_SESSION[$this->sessionVariable])) {
            $this->accessToken = $_SESSION[$this->sessionVariable];
        }
        
        if (isset($options['accessToken'])) {
            $this->accessToken = $options['accessToken'];
        }
        
        if (isset($options['maxThrottledRetries'])) {
            $this->maxThrottledRetries = $options['maxThrottledRetries'];
        }
        
        if (isset($options['objects']) && is_bool($options['objects'])) {
            $this->objects = $options['objects'];
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
     * Handle an OAuth redirect response, exchanging a code for an access token.
     * 
     * @return object response
     */
    public function oauthResponse()
    {
        $response = $this->post($this->identHost() . '/cis-web/oauth2/v3/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $_GET['code'],
                'client_id' => $this->appKey
            ]
        ]);
        
        return $this->oauthResponseHandler($response);
    }
    
    /**
     * Authenticate using the OAuth2 password grant type.
     * 
     * @param string $username
     * @param string $password
     * @return object response
     */
    public function oauthPassword($username, $password)
    {
        $response = $this->post($this->identHost() . '/cis-web/oauth2/v3/token', [
            'body' => [
                'grant_type' => 'password',
                'client_id' => $this->appKey,
                'username' => $username,
                'password' => $password
            ],
            'headers' => [
                'content-type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        return $this->oauthResponseHandler($response);
    }
    
    /**
     * Common handler for a successful OAuth2 access token response
     * 
     * @param object $response
     * @returns object response
     */
    private function oauthResponseHandler($response){
        if ($response->statusCode === 200) {
            $this->accessToken = $response->data['access_token'];
            if ($this->sessions) {
                $_SESSION[$this->sessionVariable] = $this->accessToken;
            }
        }
        return $response;
    }
    
    /**
     * Get the access token, if it exists.
     * 
     * @return string access token
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }
    
    /**
     * Check whether the client has an active session. This first checks for the
     * existence of an access token. If one is found then it sends a request to
     * the server to validate the access token.
     * 
     * @return boolean Whether an active session exists
     */
    public function isAuthenticated()
    {
        if (!$this->getAccessToken()) {
            return false;
        }
        $response = $this->get('/platform/collection');
        return $response->statusCode === 200;
    }
    /**
     * Execute an HTTP GET request
     * 
     * @param string $url URL
     * @param array $options
     * @param array $options['query'] Query parameters
     * @param array $options['headers'] HTTP Request headers
     */
    public function get($url, $options = array())
    {
        $options['method'] = 'GET';
        return $this->request($url, $options);
    }
    
    /**
     * Execute an HTTP POST request
     * 
     * @param string $url URL
     * @param array $options
     * @param array $options['query'] Query parameters
     * @param array $options['headers'] HTTP Request headers
     * @param string $options['body'] Request body data
     */
    public function post($url, $options = array())
    {
        $options['method'] = 'POST';
        return $this->request($url, $options);
    }
    
    /**
     * Execute an HTTP HEAD request
     * 
     * @param string $url URL
     * @param array $options
     * @param array $options['query'] Query parameters
     * @param array $options['headers'] HTTP Request headers
     */
    public function head($url, $options = array())
    {
        $options['method'] = 'HEAD';
        return $this->request($url, $options);
    }
    
    /**
     * Execute an HTTP DELETE request
     * 
     * @param string $url URL
     * @param array $options
     * @param array $options['query'] Query parameters
     * @param array $options['headers'] HTTP Request headers
     */
    public function delete($url, $options = array())
    {
        $options['method'] = 'DELETE';
        return $this->request($url, $options);
    }
    
    /**
     * Execute an HTTP request.
     * 
     * @param string $url URL
     * @param array $options
     * @param string $options['method'] HTTP method
     * @param array $options['query'] Query parameters
     * @param array $options['headers'] HTTP Request headers
     * @param string $options['body'] Request body data
     * 
     * @throws Exception if curl fails
     * 
     * @return Response
     */
    private function request($url, $options = array())
    {
        $options = array_merge([
            'method' => 'GET',
            'query' => array(),
            'headers' => array(),
            'body' => null,
            '_retries' => 0
        ], $options);
        
        $request = curl_init();
        
        // HTTP Method
        $this->setRequestMethod($request, $options['method']);
        
        // Build the URL
        $requestUrl = $this->buildRequestUrl($url, $options['query']);
        curl_setopt($request, CURLOPT_URL, $requestUrl);
        
        // Default HTTP headers
        if (!is_array($options['headers'])) {
            $options['headers'] = [];
        }
        if (!isset($options['headers']['Authorization']) && $this->getAccessToken()) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->getAccessToken();
        }
        if (!isset($options['headers']['Accept']) && strpos($requestUrl, '/platform/') !== false) {
            $options['headers']['Accept'] = 'application/x-fs-v1+json';
        }
        if (isset($this->pendingModifications)) {
            $options['headers']['X-FS-Feature-Tag'] = $this->pendingModifications;
        }
        
        $options['headers']['User-Agent'] = $this->userAgent;
        
        // Set the body
        $body = null;
        if ($options['body'] && ($options['method'] === 'POST' || $options['method'] === 'PUT')) {
            
            // PHP array
            if (is_array($options['body']) && strpos($requestUrl, '/platform/') !== false) {
               $options['headers']['content-type'] = 'application/x-fs-v1+json';
               $body = json_encode($options['body']);
            } 
            
            // gedcomx-php object
            else if ($this->objects && is_object($options['body']) && method_exists($options['body'], 'toArray')) {
                $options['headers']['content-type'] = 'application/x-fs-v1+json';
                $body = json_encode($options['body']->toArray());
            } 
            
            // This is currently only used for OAuth
            else {
               $body = http_build_query($options['body'], '', '&');
            }
            
            if ($body) {
                curl_setopt($request, CURLOPT_POSTFIELDS, $body);
            }
        }
        
        // Process the HTTP headers.
        // We set the headers after the body so that we can overwride the default
        // Content-Type of application/x-www-form-urlencoded setting the POST
        // body as a string
        $headersList = [];
        foreach ($options['headers'] as $key => $value) {
            $headersList[] = $key.': '.$value;
        }
        curl_setopt($request, CURLOPT_HTTPHEADER, $headersList); 
        
        // Other curl options
        curl_setopt($request, CURLOPT_HEADER, true);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, false);
        
        // Finally execute the curl request
        $curlResponse = curl_exec($request);
        
        // Process the curl response into a PHP response object
        if ($curlResponse) {
            $response = new stdClass;
            $response->curl = $request;
            $response->requestMethod = $options['method'];
            $response->requestHeaders = $options['headers'];
            $response->requestBody = $body;
            $response->headers = array();
            $response->effectiveUrl = $requestUrl;
            $response->redirected = false;
            $response->throttled = false;

            // Extract headers from response
            $responseParts = explode("\r\n\r\n", $curlResponse);
            $responseHeaders = explode("\r\n", $responseParts[0]);

            // Set response body;
            $response->body = $responseParts[1];

            // Convert headers into an associative array
            foreach ($responseHeaders as $header) {
                preg_match('#(.*?)\:\s(.*)#', $header, $matches);
                if (count($matches)) {
                    $response->headers[$matches[1]] = $matches[2];
                }
            }

            // Get status code
            $response->statusCode = $http_code = curl_getinfo($request, CURLINFO_HTTP_CODE);

            // Follow redirects. We don't use the curl opt to do this because it
            // appends all response headers into the final response which makes
            // parsing practically impossible. So we just recursively follow
            // redirects ourself.
            if ($response->statusCode >= 300 && $response->statusCode < 400 && $response->headers['location']) {
                
                // We don't include the body param because POSTs should never redirect
                $redirectResponse = $this->request($response->headers['location'], $options);
                $redirectResponse->redirected = true;
                $redirectResponse->originalUrl = $requestUrl;
                return $redirectResponse;
            }
            
            // Throttling
            if ($response->statusCode === 429 && ++$options['_retries'] < $this->maxThrottledRetries) {
                if ($response->headers['retry-after']) {
                    sleep(intval($response->headers['retry-after']));
                }
                $throttledResponse = $this->request($url, $options);
                $throttledResponse->throttled = true;
                if (!isset($throttledResponse->retries)) {
                    $throttledResponse->retries = $options['_retries'];
                }
                return $throttledResponse;
            }
            
            // Process JSON, if possible
            if (isset($response->headers['content-type']) && strpos($response->headers['content-type'], 'json') !== false) {
                try {
                    $response->data = json_decode($response->body, true);
                    
                    // Instantiate objects via gedcomx-php when configured
                    if ($response->data && $this->objects){
                        
                        // Atom Feed
                        if (isset($response->data['entries'])){
                            $response->gedcomx = new \Gedcomx\Atom\Feed($response->data);
                        } 
                        
                        // OAuth token success response
                        else if (isset($response->data['access_token']) || isset($response->data['error'])) {
                            $response->gedcomx = new \Gedcomx\Extensions\FamilySearch\OAuth2($response->data);
                        } 
                        
                        // GedcomX
                        else {
                            $response->gedcomx = new \Gedcomx\Extensions\FamilySearch\FamilySearchPlatform($response->data);
                        }
                    }
                } catch (Exception $e) { }
            }
            
            return $response;
        } else {
            throw new Exception(curl_errno($request).' - '.curl_error($request));
        }
    }
    
    /**
     * Set the HTTP method of a curl resource
     * 
     * @param resource $resource cURL resource
     * @param string $method HTTP Method
     */
    private function setRequestMethod($resource, $method)
    {
        switch (strtoupper($method)) {
            case 'HEAD':
                curl_setopt($resource, CURLOPT_NOBODY, true);
                break;
            case 'GET':
                curl_setopt($resource, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($resource, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($resource, CURLOPT_CUSTOMREQUEST, $method);
        }
    }
    
    /**
     * Build the URL for an HTTP request.
     * Process and attach query parameters.
     * Autofill the domain if it isn't set.
     * 
     * @param string $url URL
     * @param array $queryParams Query parameters
     * @return string URL
     */
    private function buildRequestUrl($url, $queryParams)
    {
        $urlParts = parse_url($url);
        
        if (!isset($urlParts['host']) || !isset($urlParts['scheme'])) {
            $url = $this->platformHost() . $url;
        }
        
        if (count($queryParams) > 0) {
            
            $queryString = http_build_query($queryParams, '', '&');
            
            // If the URL already contains a query, append the new query params
            // with a preceding & separator
            if (isset($urlParts['query'])) {
                $url .= '&' . $queryString;
            }
            
            // Add the ? if a query wasn't already present
            else {
                $url .= '?' . $queryString;
            }
        }
        
        return $url;
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
                return 'https://integration.familysearch.org';
        }
    }
    
    /**
     * Get the host name for the platform API
     * 
     * @return string
     */
    private function platformHost()
    {
        switch ($this->environment) {
            case 'production':
                return 'https://api.familysearch.org';
            case 'beta':
                return 'https://beta.familysearch.org';
            default:
                return 'https://api-integ.familysearch.org';
        }
    }
    
    /**
     * Calculate the default user agent
     * 
     * @return string
     */
    private static function defaultUseragent()
    {
        return 'FS-PHP-Lite/' . self::VERSION . ' curl/' . \curl_version()['version'] . ' PHP/' . PHP_VERSION;
    }
    
}