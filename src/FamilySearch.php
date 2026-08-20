<?php

/**
 * Basic PHP SDK for the FamilySearch API.
 */
class FamilySearch
{

    /**
     * SDK version number (Semantic Versioning)
     *
     * Version 1.3.0: Added optional AES-256-GCM session token encryption
     * - New feature: sessionEncryption and sessionEncryptionKey configuration options
     * - Backward compatible: encryption defaults to disabled (no breaking changes)
     * - Security enhancement: protects OAuth tokens from filesystem disclosure
     */
    const VERSION = '1.3.0';
    
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
     * Whether to encrypt access tokens stored in $_SESSION using AES-256-GCM.
     *
     * When enabled, OAuth access tokens are encrypted using AES-256-GCM authenticated
     * encryption before being stored in PHP session files. This protects tokens from
     * unauthorized filesystem access, backup exposure, and disk forensics.
     *
     * Default: false (for backward compatibility with existing applications)
     *
     * Security Implications:
     * - When false: Tokens stored in plaintext (INSECURE for production)
     * - When true: Tokens encrypted with AES-256-GCM (RECOMMENDED for production)
     *
     * What encryption protects against:
     * - Filesystem access to session files
     * - Backup file exposure
     * - Disk forensics after deletion
     * - Accidental logging of session data
     *
     * What encryption does NOT protect against:
     * - Active server compromise (attacker can access encryption key)
     * - Memory dumps (tokens are plaintext in memory during request)
     * - XSS attacks (client-side attacks)
     * - Session hijacking (valid session ID grants access)
     *
     * @var bool
     * @see $sessionEncryptionKey for key requirements
     * @see SECURITY.md for comprehensive security guidance
     */
    private $sessionEncryption = false;

    /**
     * Encryption key for session token encryption.
     *
     * This key is used to encrypt and decrypt OAuth access tokens stored in PHP sessions.
     * AES-256-GCM requires exactly 32 bytes (256 bits) for the encryption key.
     *
     * Required: Yes, when $sessionEncryption is enabled
     *
     * Key Format Support:
     * - Raw binary: 32 bytes (used directly)
     * - Base64: 44 characters (decoded to 32 bytes)
     * - Hexadecimal: 64 characters (decoded to 32 bytes)
     * - Passphrase: Any other length (hashed with SHA-256 to 32 bytes)
     *
     * Key Generation (Recommended):
     * ```php
     * // Method 1: Base64-encoded key (recommended)
     * $key = base64_encode(random_bytes(32));
     *
     * // Method 2: Hex-encoded key
     * $key = bin2hex(random_bytes(32));
     *
     * // Method 3: Using OpenSSL
     * $key = base64_encode(openssl_random_pseudo_bytes(32));
     * ```
     *
     * Key Storage Best Practices:
     * - NEVER hardcode keys in source code
     * - Store in environment variables: $_ENV['FS_SESSION_ENCRYPTION_KEY']
     * - Use secrets manager in production (AWS Secrets Manager, HashiCorp Vault)
     * - Use different keys per environment (dev/staging/production)
     * - Rotate keys every 90 days
     * - Backup keys securely (encrypted backups only)
     *
     * Security Warning:
     * If this key is compromised, all encrypted session tokens can be decrypted.
     * Treat this key with the same security level as the tokens it protects.
     *
     * @var string|null 32-byte encryption key (may be encoded as base64/hex)
     * @see $sessionEncryption to enable encryption
     * @see normalizeEncryptionKey() for key format handling
     * @see SECURITY.md for key management best practices
     */
    private $sessionEncryptionKey;

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
     * @param array $options Configuration options
     * @param string $options['environment'] Environment: 'production', 'beta', or 'integration' (default: 'integration')
     * @param string $options['appKey'] Application key from FamilySearch developer portal
     * @param string $options['redirectUri'] OAuth redirect URI for authorization flow
     * @param bool $options['sessions'] Enable automatic session storage of access token (default: true)
     * @param string $options['sessionVariable'] Session variable name for token storage (default: 'FS_ACCESS_TOKEN')
     * @param bool $options['sessionEncryption'] Enable AES-256-GCM encryption for session tokens (default: false)
     * @param string $options['sessionEncryptionKey'] Encryption key for session tokens (required if sessionEncryption=true)
     *                                                 - If exactly 32 bytes: used directly as AES-256 key
     *                                                 - If 44 characters: decoded as base64 to 32 bytes
     *                                                 - If 64 characters: decoded as hex to 32 bytes
     *                                                 - Other lengths: derived using hash('sha256', $key, true)
     *                                                 Generate secure key: base64_encode(random_bytes(32))
     * @param string $options['accessToken'] Manually provide access token (bypasses session storage)
     * @param int $options['maxThrottledRetries'] Maximum retry attempts for throttled requests (default: 5)
     * @param array $options['pendingModifications'] Array of pending modification feature flags
     * @param string $options['userAgent'] Additional user agent string to append to default
     * @param bool $options['objects'] Enable gedcomx-php object serialization/deserialization (default: false)
     *
     * @throws \InvalidArgumentException if sessionEncryption is enabled but sessionEncryptionKey is missing
     * @throws \Exception if OpenSSL extension is not available when encryption is enabled
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

        // =====================================================================
        // Session Encryption Configuration
        // =====================================================================
        // Load encryption settings from options array
        if (isset($options['sessionEncryption']) && is_bool($options['sessionEncryption'])) {
            $this->sessionEncryption = $options['sessionEncryption'];
        }

        if (isset($options['sessionEncryptionKey'])) {
            $this->sessionEncryptionKey = $options['sessionEncryptionKey'];
        }

        // =====================================================================
        // Validate Encryption Configuration at Construction Time
        // =====================================================================
        // If encryption is enabled, validate requirements immediately to fail fast
        // and provide clear error messages to developers during setup
        if ($this->sessionEncryption) {
            // SECURITY CHECK: Ensure OpenSSL extension is available
            // AES-256-GCM encryption requires the OpenSSL PHP extension
            if (!extension_loaded('openssl')) {
                throw new \Exception(
                    'Session token encryption requires the OpenSSL PHP extension. ' .
                    'Please install or enable the OpenSSL extension, or disable sessionEncryption.'
                );
            }

            // SECURITY CHECK: Ensure encryption key is provided
            // Without a key, we cannot perform encryption - fail immediately
            if (empty($this->sessionEncryptionKey)) {
                throw new \InvalidArgumentException(
                    'sessionEncryptionKey is required when sessionEncryption is enabled. ' .
                    'Generate a secure key using: base64_encode(random_bytes(32))'
                );
            }

            // KEY NORMALIZATION: Convert key to standard 32-byte format
            // Supports multiple input formats: raw binary, base64, hex, or passphrase
            // After normalization, key is always exactly 32 bytes for AES-256
            $this->sessionEncryptionKey = $this->normalizeEncryptionKey($this->sessionEncryptionKey);
        }

        if (isset($options['pendingModifications'])) {
            $this->pendingModifications = implode(',', $options['pendingModifications']);
        }
        
        $this->userAgent = self::defaultUseragent();
        if (isset($options['userAgent'])) {
            $this->userAgent .= ' ' . $options['userAgent'];
        }
        
        // =====================================================================
        // Session Token Retrieval with Encryption Support
        // =====================================================================
        // Load the access token from the session first so that it can be
        // overwritten by the accessToken option
        //
        // This logic handles four scenarios for backward compatibility:
        // 1. Encrypted token + encryption enabled (normal operation)
        // 2. Encrypted token + encryption disabled (downgrade - clear session)
        // 3. Plaintext token + encryption enabled (migration - accept temporarily)
        // 4. Plaintext token + encryption disabled (legacy - normal operation)
        if ($this->sessions && isset($_SESSION[$this->sessionVariable])) {
            $sessionValue = $_SESSION[$this->sessionVariable];
            $isEncrypted = $this->isEncryptedToken($sessionValue);

            // ================================================================
            // SECURITY: Determine whether to decrypt based on token format and configuration
            // This provides backward compatibility and graceful migration handling
            // ================================================================

            if ($isEncrypted) {
                // =============================================================
                // SCENARIO 1 & 2: Token appears to be encrypted
                // =============================================================
                if ($this->sessionEncryption) {
                    // SCENARIO 1: Encryption enabled + encrypted token (EXPECTED)
                    // This is normal operation - decrypt the token
                    try {
                        $decrypted = $this->decryptToken($sessionValue);
                        if ($decrypted !== false) {
                            $this->accessToken = $decrypted;
                        } else {
                            // SECURITY FAILURE: Decryption returned false
                            // Possible causes:
                            // - Wrong encryption key
                            // - Corrupted ciphertext
                            // - Tampered authentication tag
                            // - Invalid encrypted format
                            //
                            // FAIL SECURE: Clear session and require re-authentication
                            unset($_SESSION[$this->sessionVariable]);
                            $this->accessToken = null;
                            trigger_error(
                                'Failed to decrypt session token. Session cleared. ' .
                                'This may indicate wrong encryption key or corrupted session data.',
                                E_USER_WARNING
                            );
                        }
                    } catch (\Exception $e) {
                        // SECURITY EXCEPTION: Decryption threw exception
                        // This indicates a serious error (missing OpenSSL, invalid key, etc.)
                        //
                        // FAIL SECURE: Clear session and fail closed
                        // NEVER expose the token or key in error messages
                        unset($_SESSION[$this->sessionVariable]);
                        $this->accessToken = null;
                        trigger_error(
                            'Session token decryption error: ' . $e->getMessage() . '. Session cleared.',
                            E_USER_WARNING
                        );
                    }
                } else {
                    // SCENARIO 2: Encryption disabled + encrypted token (DOWNGRADE)
                    // User disabled encryption but session contains encrypted token
                    // This happens when encryption is turned off after being enabled
                    //
                    // SECURITY POLICY: Cannot decrypt without key
                    // FAIL SECURE: Clear session and require re-authentication
                    // This prevents accidental exposure if encryption was disabled by mistake
                    unset($_SESSION[$this->sessionVariable]);
                    $this->accessToken = null;
                    trigger_error(
                        'Encrypted session token found but encryption is disabled. ' .
                        'Session cleared. Re-authentication required.',
                        E_USER_WARNING
                    );
                }
            } else {
                // =============================================================
                // SCENARIO 3 & 4: Token appears to be plaintext
                // =============================================================
                if ($this->sessionEncryption) {
                    // SCENARIO 3: Encryption enabled + plaintext token (MIGRATION)
                    // User enabled encryption but session contains plaintext token
                    // This is expected during migration from plaintext to encrypted storage
                    //
                    // MIGRATION STRATEGY: Accept plaintext token temporarily
                    // On next OAuth response, token will be encrypted automatically
                    // This provides zero-downtime migration without forcing logout
                    $this->accessToken = $sessionValue;
                } else {
                    // SCENARIO 4: Encryption disabled + plaintext token (LEGACY)
                    // This is normal legacy operation before encryption was enabled
                    // Token is stored in plaintext in session (not recommended for production)
                    $this->accessToken = $sessionValue;
                }
            }
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
     * Common handler for a successful OAuth2 access token response.
     *
     * This method is called after successfully exchanging an OAuth authorization code
     * or password credentials for an access token. It stores the token in memory and
     * optionally persists it to the PHP session with optional encryption.
     *
     * Token Storage Strategy:
     * - If encryption enabled: Token is encrypted with AES-256-GCM before storage
     * - If encryption disabled: Token is stored in plaintext (not recommended for production)
     * - If encryption fails: Token is NOT stored (fail-secure), available for current request only
     *
     * Security Considerations:
     * - Token is always stored in memory ($this->accessToken) regardless of session storage
     * - If encryption fails, we do NOT fall back to plaintext storage (fail-secure principle)
     * - User must re-authenticate on next request if encryption fails
     *
     * @param object $response The OAuth token response from FamilySearch API
     * @return object The same response object (for method chaining or inspection)
     */
    private function oauthResponseHandler($response){
        if ($response->statusCode === 200) {
            // Extract and store access token in memory (always available for current request)
            $this->accessToken = $response->data['access_token'];

            // ================================================================
            // Session Token Storage with Optional Encryption
            // ================================================================
            if ($this->sessions) {
                if ($this->sessionEncryption) {
                    // ========================================================
                    // SECURE PATH: Encryption enabled - encrypt before storing
                    // ========================================================
                    try {
                        // Encrypt the access token using AES-256-GCM authenticated encryption
                        // Format: base64(iv):base64(tag):base64(ciphertext)
                        // - IV (12 bytes): Unique random initialization vector
                        // - Tag (16 bytes): Authentication tag for tamper detection
                        // - Ciphertext: Encrypted token data
                        $encryptedToken = $this->encryptToken($this->accessToken);
                        $_SESSION[$this->sessionVariable] = $encryptedToken;
                    } catch (\Exception $e) {
                        // ====================================================
                        // FAIL-SECURE: Encryption failed - DO NOT store plaintext
                        // ====================================================
                        // If encryption fails (missing OpenSSL, invalid key, etc.),
                        // we NEVER fall back to storing the token in plaintext.
                        //
                        // This is a critical security decision:
                        // - Better to require re-authentication than expose tokens
                        // - Token remains available in memory for current request
                        // - User must authenticate again on next request
                        //
                        // This ensures we fail secure rather than degrading to insecure storage
                        trigger_error(
                            'Failed to encrypt session token: ' . $e->getMessage() . '. ' .
                            'Token not stored in session (available for current request only).',
                            E_USER_WARNING
                        );
                        // Explicitly clear any existing session value to prevent confusion
                        unset($_SESSION[$this->sessionVariable]);
                    }
                } else {
                    // ========================================================
                    // LEGACY PATH: Encryption disabled - store plaintext
                    // ========================================================
                    // This is the legacy behavior before encryption was implemented
                    // NOT RECOMMENDED for production environments
                    $_SESSION[$this->sessionVariable] = $this->accessToken;
                }
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
            // Handle multiple header sections (e.g., 100 Continue followed by 201 Created)
            $responseParts = explode("\r\n\r\n", $curlResponse);

            // The body is always the last part
            $response->body = array_pop($responseParts);

            // The actual response headers are in the last header section
            // (skip informational responses like 100 Continue)
            $lastHeaderSection = array_pop($responseParts);
            $responseHeaders = explode("\r\n", $lastHeaderSection);

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
            $locationHeader = $response->headers['Location'] ?? $response->headers['location'] ?? null;
            if ($response->statusCode >= 300 && $response->statusCode < 400 && $locationHeader) {

                // We don't include the body param because POSTs should never redirect
                $redirectResponse = $this->request($locationHeader, $options);
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
                return 'https://api.familysearch.org';
            case 'beta':
                return 'https://apibeta.familysearch.org';
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

    /**
     * Detect if a session value appears to be encrypted.
     *
     * This method checks if a session token value is in the encrypted format used by
     * encryptToken(). The encrypted format consists of three colon-separated base64
     * segments: IV:tag:ciphertext. This detection is used for backward compatibility
     * to handle mixed scenarios (encrypted tokens with encryption disabled, or
     * plaintext tokens with encryption enabled during migration).
     *
     * Detection Strategy:
     * - Encrypted tokens have exactly 2 colons (3 segments)
     * - Each segment should be valid base64
     * - Plaintext tokens typically don't contain colons or have different structure
     *
     * Security Considerations:
     * - False positives are acceptable: attempting to decrypt a plaintext token will fail
     *   safely and the token will be treated as invalid
     * - False negatives are critical: missing an encrypted token could expose it
     * - The format is intentionally distinctive to minimize detection errors
     *
     * @param mixed $value Session value to check
     * @return bool True if value appears to be encrypted, false otherwise
     */
    private function isEncryptedToken($value)
    {
        // Must be a string to be encrypted
        if (!is_string($value) || empty($value)) {
            return false;
        }

        // Encrypted format has exactly 3 segments separated by colons (2 colons total)
        // Format: base64(iv):base64(tag):base64(ciphertext)
        if (substr_count($value, ':') !== 2) {
            return false;
        }

        // Split and verify we have exactly 3 parts
        $parts = explode(':', $value);
        if (count($parts) !== 3) {
            return false;
        }

        // All parts should be non-empty base64 strings
        // We don't strictly validate base64 here as decryptToken() will handle that
        foreach ($parts as $part) {
            if (empty($part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate a cryptographically secure initialization vector (IV) for AES-GCM encryption.
     *
     * The IV is used to ensure that the same plaintext encrypted multiple times produces
     * different ciphertexts. For AES-GCM, the optimal IV length is 96 bits (12 bytes) per
     * NIST SP 800-38D. Each encryption operation MUST use a unique IV to maintain security.
     *
     * Security Considerations:
     * - Uses openssl_random_pseudo_bytes() for cryptographically secure randomness
     * - IV does not need to be secret and is stored alongside the ciphertext
     * - NEVER reuse an IV with the same encryption key
     *
     * @return string 12-byte binary IV
     * @throws \Exception if secure random bytes cannot be generated
     */
    private function generateIV()
    {
        $iv = openssl_random_pseudo_bytes(12, $cryptoStrong);

        if ($iv === false || !$cryptoStrong) {
            throw new \Exception(
                'Failed to generate cryptographically secure IV. ' .
                'OpenSSL random number generator may not be properly seeded.'
            );
        }

        return $iv;
    }

    /**
     * Normalize an encryption key to exactly 32 bytes for AES-256.
     *
     * This method accepts encryption keys in multiple formats and normalizes them to the
     * 32-byte (256-bit) format required by AES-256. This provides flexibility in how keys
     * are provided while ensuring cryptographic compatibility.
     *
     * Key Format Handling:
     * - Raw binary (32 bytes): Used directly without modification
     * - Base64 (44 characters): Decoded to 32 bytes (e.g., output of base64_encode(random_bytes(32)))
     * - Hexadecimal (64 characters): Decoded to 32 bytes (e.g., output of bin2hex(random_bytes(32)))
     * - Other lengths: Derived using SHA-256 hash to produce exactly 32 bytes
     *
     * Key Derivation for Non-Standard Lengths:
     * When a key is not in one of the standard formats, SHA-256 hashing is used to derive
     * a 32-byte key. This allows passphrases or keys of arbitrary length to be converted
     * into a valid AES-256 key. However, for maximum security, prefer using properly
     * generated 32-byte random keys rather than relying on hash derivation.
     *
     * Security Considerations:
     * - Prefer pre-generated 32-byte keys: Use random_bytes(32) for maximum entropy
     * - Hash derivation reduces entropy: Passphrases have lower entropy than random keys
     * - Use different keys per environment: Never share keys between dev/staging/production
     * - Store keys securely: Use environment variables or secure vaults, never in source code
     *
     * Recommended Key Generation:
     * ```php
     * $key = base64_encode(random_bytes(32));  // Generates 44-character base64 key
     * ```
     *
     * @param string $key Encryption key in any supported format
     * @return string Normalized 32-byte binary encryption key
     */
    private function normalizeEncryptionKey($key)
    {
        // Check if already 32 bytes (raw binary format)
        if (strlen($key) === 32) {
            return $key;
        }

        // Attempt to decode from base64 (44 characters produces 32 bytes)
        if (strlen($key) === 44) {
            $decoded = base64_decode($key, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }

        // Attempt to decode from hexadecimal (64 characters produces 32 bytes)
        if (strlen($key) === 64 && ctype_xdigit($key)) {
            $decoded = hex2bin($key);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }

        // For any other length, derive a 32-byte key using SHA-256
        // This allows passphrases and arbitrary-length keys to be used
        return hash('sha256', $key, true);
    }

    /**
     * Validate that the encryption key meets AES-256 requirements.
     *
     * This method validates that the configured encryption key is present and exactly
     * 32 bytes in length. The key should already be normalized by normalizeEncryptionKey()
     * during construction, so this method primarily serves as a runtime assertion.
     *
     * Note: This method is called by encryptToken() and decryptToken() to ensure the
     * encryption key is available and valid before performing cryptographic operations.
     * The key normalization (format conversion) happens in the constructor via
     * normalizeEncryptionKey(), so this method expects a ready-to-use 32-byte key.
     *
     * Security Considerations:
     * - Key must have high entropy (use random_bytes() or openssl_random_pseudo_bytes())
     * - Never hardcode keys in source code
     * - Store keys in environment variables or secure configuration files
     * - Use different keys for different environments (dev/staging/production)
     *
     * @return string Validated 32-byte binary key
     * @throws \InvalidArgumentException if key is missing or not exactly 32 bytes
     */
    private function validateEncryptionKey()
    {
        if (empty($this->sessionEncryptionKey)) {
            throw new \InvalidArgumentException(
                'sessionEncryptionKey is required when sessionEncryption is enabled. ' .
                'Generate a secure key using: base64_encode(random_bytes(32))'
            );
        }

        $key = $this->sessionEncryptionKey;

        // Key should already be normalized to 32 bytes in constructor
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException(
                'sessionEncryptionKey must be exactly 32 bytes (256 bits) for AES-256. ' .
                'Received: ' . strlen($key) . ' bytes. ' .
                'This indicates the key was not properly normalized during construction.'
            );
        }

        return $key;
    }

    /**
     * Encrypt an access token using AES-256-GCM authenticated encryption.
     *
     * This method encrypts OAuth access tokens before storing them in $_SESSION to protect
     * against file disclosure attacks, unauthorized disk access, and accidental logging.
     * AES-256-GCM provides both confidentiality (encryption) and authenticity (prevents tampering).
     *
     * Encryption Process:
     * 1. Generate a unique random IV (12 bytes) for this encryption
     * 2. Encrypt the token using AES-256-GCM with the configured key
     * 3. Obtain the authentication tag (16 bytes) for integrity verification
     * 4. Combine IV, tag, and ciphertext into a single encoded string
     * 5. Return base64-encoded format: base64(IV):base64(tag):base64(ciphertext)
     *
     * Security Considerations:
     * - Each encryption uses a unique IV (never reused)
     * - Authentication tag prevents ciphertext tampering
     * - Encrypted data is self-contained (includes all components needed for decryption)
     * - Format is easily distinguishable from plaintext tokens (contains colons)
     *
     * Threat Model:
     * - PROTECTS AGAINST: File system disclosure, disk forensics, session storage dumps
     * - DOES NOT PROTECT: Memory dumps, active code execution, compromised encryption key
     *
     * @param string $token Plaintext OAuth access token to encrypt
     * @return string Encrypted token in format: base64(iv):base64(tag):base64(ciphertext)
     * @throws \Exception if encryption fails or OpenSSL is not available
     */
    private function encryptToken($token)
    {
        if (!extension_loaded('openssl')) {
            throw new \Exception(
                'OpenSSL extension is required for session token encryption. ' .
                'Please install or enable the OpenSSL PHP extension.'
            );
        }

        // =====================================================================
        // STEP 1: Validate encryption key is present and correct length
        // =====================================================================
        $keyBinary = $this->validateEncryptionKey();

        // =====================================================================
        // STEP 2: Generate unique cryptographically secure IV
        // =====================================================================
        // CRITICAL: Each encryption MUST use a unique IV with the same key
        // Reusing IVs with GCM mode completely breaks security
        // IV is 12 bytes (96 bits) - optimal for AES-GCM per NIST SP 800-38D
        $iv = $this->generateIV();

        // =====================================================================
        // STEP 3: Perform AES-256-GCM authenticated encryption
        // =====================================================================
        // AES-256-GCM provides both:
        // - CONFIDENTIALITY: Token is encrypted (unreadable without key)
        // - AUTHENTICITY: Authentication tag detects any tampering
        //
        // Why GCM mode?
        // - Built-in authentication (no separate HMAC needed)
        // - AEAD (Authenticated Encryption with Associated Data)
        // - No padding oracle vulnerabilities (unlike CBC mode)
        // - Hardware acceleration on modern CPUs (AES-NI)
        //
        // The $tag parameter is passed by reference and populated by openssl_encrypt
        $tag = '';
        $ciphertext = openssl_encrypt(
            $token,                  // Plaintext token to encrypt
            'aes-256-gcm',          // Algorithm: AES-256 in GCM mode
            $keyBinary,             // 32-byte encryption key
            OPENSSL_RAW_DATA,       // Return raw binary (not base64)
            $iv,                    // 12-byte initialization vector
            $tag,                   // Output: 16-byte authentication tag (by reference)
            '',                     // Additional authenticated data (AAD) - not used
            16                      // Tag length: 16 bytes (128 bits) - maximum for GCM
        );

        // Validate encryption succeeded
        if ($ciphertext === false) {
            throw new \Exception(
                'Failed to encrypt access token. OpenSSL error: ' . openssl_error_string()
            );
        }

        // Validate authentication tag was generated (paranoid check)
        if (empty($tag)) {
            throw new \Exception(
                'Failed to generate authentication tag during encryption. ' .
                'This should not happen with AES-GCM mode.'
            );
        }

        // =====================================================================
        // STEP 4: Combine IV, tag, and ciphertext into self-contained format
        // =====================================================================
        // All three components are needed for decryption:
        // - IV (12 bytes): Must be unique per encryption, not secret
        // - Tag (16 bytes): Authentication tag for tamper detection
        // - Ciphertext (variable): Encrypted token data
        //
        // Format: base64(iv):base64(tag):base64(ciphertext)
        // Base64 encoding makes it safe to store in session (no binary issues)
        // Colon separators make format easily distinguishable from plaintext tokens
        $encryptedData = base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);

        return $encryptedData;
    }

    /**
     * Decrypt an access token that was encrypted using AES-256-GCM.
     *
     * This method decrypts OAuth access tokens that were previously encrypted by encryptToken().
     * It validates the authentication tag to ensure the ciphertext has not been tampered with,
     * then decrypts the token using the configured encryption key.
     *
     * Decryption Process:
     * 1. Parse the encrypted data format: base64(IV):base64(tag):base64(ciphertext)
     * 2. Decode each component from base64 to binary
     * 3. Validate the authentication tag (detects tampering)
     * 4. Decrypt the ciphertext using AES-256-GCM with the configured key and IV
     * 5. Return the plaintext token or false on failure
     *
     * Security Considerations:
     * - Authentication tag is validated before decryption (prevents tampering)
     * - Returns false on ANY decryption failure (wrong key, corrupted data, tampered ciphertext)
     * - Does not leak information about WHY decryption failed (timing-safe failure)
     * - Failed decryption should trigger session clearing and re-authentication
     *
     * Failure Scenarios:
     * - Wrong encryption key
     * - Corrupted ciphertext
     * - Modified authentication tag
     * - Invalid format (not 3 colon-separated segments)
     * - Malformed base64 encoding
     *
     * @param string $encryptedData Encrypted token in format: base64(iv):base64(tag):base64(ciphertext)
     * @return string|false Plaintext access token on success, false on decryption failure
     */
    private function decryptToken($encryptedData)
    {
        // =====================================================================
        // PRE-CHECK: Ensure OpenSSL extension is available
        // =====================================================================
        if (!extension_loaded('openssl')) {
            // Cannot decrypt without OpenSSL - return false (fail safely)
            return false;
        }

        // =====================================================================
        // STEP 1: Validate encryption key
        // =====================================================================
        try {
            $keyBinary = $this->validateEncryptionKey();
        } catch (\InvalidArgumentException $e) {
            // Invalid key configuration - cannot decrypt
            // Return false instead of throwing to fail gracefully
            return false;
        }

        // =====================================================================
        // STEP 2: Parse encrypted data format
        // =====================================================================
        // Expected format: base64(iv):base64(tag):base64(ciphertext)
        // Example: "mXzK9PqW3hN8fG2D:aG4k...J9mQ==:pL8nM...vR4=="
        $parts = explode(':', $encryptedData);

        if (count($parts) !== 3) {
            // Invalid format - must have exactly 3 colon-separated segments
            // Could be plaintext token or corrupted encrypted data
            return false;
        }

        // =====================================================================
        // STEP 3: Decode base64 components to binary
        // =====================================================================
        // Strict mode (true) ensures proper base64 validation
        $iv = base64_decode($parts[0], true);         // 12-byte IV
        $tag = base64_decode($parts[1], true);        // 16-byte authentication tag
        $ciphertext = base64_decode($parts[2], true); // Encrypted token (variable length)

        // Validate base64 decoding succeeded
        if ($iv === false || $tag === false || $ciphertext === false) {
            // Malformed base64 encoding - corrupted data
            return false;
        }

        // =====================================================================
        // STEP 4: Validate component lengths
        // =====================================================================
        // GCM mode requires specific IV and tag lengths
        if (strlen($iv) !== 12 || strlen($tag) !== 16) {
            // Invalid IV (expected: 12 bytes) or tag (expected: 16 bytes)
            // This indicates corrupted or tampered data
            return false;
        }

        // =====================================================================
        // STEP 5: Perform AES-256-GCM authenticated decryption
        // =====================================================================
        // GCM mode validates the authentication tag BEFORE decryption
        // If tag doesn't match, decryption fails (tamper detection)
        //
        // Decryption can fail for multiple reasons:
        // - Wrong encryption key
        // - Tampered ciphertext
        // - Modified authentication tag
        // - Corrupted data
        //
        // SECURITY: We intentionally return the same error (false) for ALL failures
        // This prevents attackers from distinguishing between failure causes
        // (timing-safe error handling)
        $plaintext = openssl_decrypt(
            $ciphertext,        // Encrypted token data
            'aes-256-gcm',      // Algorithm: AES-256 in GCM mode
            $keyBinary,         // 32-byte decryption key
            OPENSSL_RAW_DATA,   // Input/output is raw binary (not base64)
            $iv,                // 12-byte initialization vector (from encrypted data)
            $tag,               // 16-byte authentication tag (validates integrity)
            ''                  // Additional authenticated data (AAD) - must match encryption (empty)
        );

        // =====================================================================
        // STEP 6: Validate decryption succeeded
        // =====================================================================
        // openssl_decrypt returns false on ANY failure:
        // - Wrong key → false
        // - Tampered ciphertext → false (tag validation fails)
        // - Corrupted data → false
        // - Modified tag → false
        //
        // SECURITY: Same error response for all failure types (timing-safe)
        if ($plaintext === false) {
            // Decryption failed - return false to trigger session clearing
            // Caller will handle this by clearing session and requiring re-auth
            return false;
        }

        // Success: Return plaintext token
        return $plaintext;
    }

}