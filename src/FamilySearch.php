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
    
    public $_lastResponse;
    
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
        
        // Load the access token from the session first so that it can be
        // overwritten by the accessToken option
        if ($this->sessions && isset($_SESSION[$this->sessionVariable])) {
            $this->accessToken = $_SESSION[$this->sessionVariable];
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
     * Handle an OAuth response, exchanging a code for an access token.
     * 
     * @return string access token
     */
    public function oauthResponse()
    {
        $response = $this->post($this->identHost() . '/cis-web/oauth2/v3/token', null, null, [
            'grant_type' => 'authorization_code',
            'code' => $_GET['code'],
            'client_id' => $this->appKey
        ]);
        
        if ($response->statusCode === 200) {
            $this->accessToken = $response->data->access_token;
            if ($this->sessions) {
                $_SESSION[$this->sessionVariable] = $this->accessToken;
            }
            return $this->accessToken;
        }
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
     * Execute an HTTP GET
     * 
     * @param string $url URL
     * @param array $queryParams Query parameters
     * @param array $headers HTTP Request headers
     */
    public function get($url, $queryParams = array(), $headers = array())
    {
        return $this->request('GET', $url, $queryParams, $headers);
    }
    
    /**
     * Execute an HTTP POST
     * 
     * @param string $url URL
     * @param array $queryParams Query parameters
     * @param array $headers HTTP Request headers
     * @param string $body Request body data
     */
    public function post($url, $queryParams = array(), $headers = array(), $body = '')
    {
        return $this->request('POST', $url, $queryParams, $headers, $body);
    }
    
    /**
     * Execute an HTTP request.
     * 
     * @param string $method HTTP method
     * @param string $url URL
     * @param array $queryParams Query parameters
     * @param array $headers HTTP Request headers
     * @param string $body Request body data
     * 
     * @throws Exception if curl fails
     * 
     * @return Response
     */
    private function request($method, $url, $queryParams = array(), $headers = array(), $body = '')
    {
        $request = curl_init();
        
        // HTTP Method
        $this->setRequestMethod($request, $method);
        
        // Build the URL
        $url = $this->buildRequestUrl($url, $queryParams);
        curl_setopt($request, CURLOPT_URL, $url);
        
        // Set the HTTP headers
        if (!is_array($headers)) {
            $headers = [];
        }
        if (!isset($headers['Authorization']) && $this->getAccessToken()) {
            $headers['Authorization'] = 'Bearer ' . $this->getAccessToken();
        }
        if (!isset($headers['Content-Type'])) {
            // $headers['Content-Type'] = 'application/x-fs-v1+json';
        }
        $this->setRequestHeaders($request, $headers);
        
        // Set the body
        if (is_array($body)) {
           $body = http_build_query($body, '', '&');
        }
        if ($body) {
            curl_setopt($request, CURLOPT_POSTFIELDS, $body);
        }
        
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);
        $curlResponse = curl_exec($request);
        
        // Process the curl response into a PHP response object
        if ($curlResponse) {
            $response = new stdClass;
            $response->headers = array();
            $response->requestUrl = $url;
            $response->finalUrl = curl_getinfo($request, CURLINFO_EFFECTIVE_URL);
            
            // Headers regex
            $pattern = '#HTTP/\d\.\d.*?$.*?\r\n\r\n#ims';
            
            // Extract headers from response
            preg_match_all($pattern, $curlResponse, $matches);
            $headers_string = array_pop($matches[0]);
            $headers = explode("\r\n", str_replace("\r\n\r\n", '', $headers_string));
            
            // Remove headers from the response body
            $response->body = str_replace($headers_string, '', $curlResponse);
            
            // Extract the version and status from the first header
            $version_and_status = array_shift($headers);
            preg_match('#HTTP/(\d\.\d)\s(\d\d\d)\s(.*)#', $version_and_status, $matches);
            $response->statusCode = intval($matches[2]);
            $response->statusText = $matches[3];
            $response->status = $matches[2].' '.$matches[3];
            
            // Convert headers into an associative array
            foreach ($headers as $header) {
                preg_match('#(.*?)\:\s(.*)#', $header, $matches);
                $response->headers[$matches[1]] = $matches[2];
            }
            
            // Process JSON, if possible
            if( isset($response->headers['Content-Type']) && strpos($response->headers['Content-Type'], 'json') !== false) {
                try {
                    $response->data = json_decode($response->body);
                } catch (Exception $e) { }
            }
            
            $this->_lastResponse = $response;
            
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
     * Set the HTTP headers for a curl resource
     * 
     * @param resource $resource cURL resource
     * @param array $headers HTTP Headers
     */
    private function setRequestHeaders($resource, $headers)
    {
        $headersList = [];
        foreach ($headers as $key => $value) {
            $headersList[] = $key.': '.$value;
        }
        curl_setopt($resource, CURLOPT_HEADER, true);
        curl_setopt($resource, CURLOPT_HTTPHEADER, $headersList);
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
    
    /**
     * Get the host name for the platform API
     * 
     * @return string
     */
    private function platformHost()
    {
        switch ($this->environment) {
            case 'production':
                return 'https://familysearch.org';
            case 'beta':
                return 'https://beta.familysearch.org';
            default:
                return 'https://sandbox.familysearch.org';
        }
    }
    
}