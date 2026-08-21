# Token Expiration Handling

## Overview

The FamilySearch PHP Lite SDK provides comprehensive token expiration tracking and automatic re-authentication capabilities, addressing [Issue #2](https://github.com/FamilySearch/fs-php-lite/issues/2) from 2016. Prior to this enhancement, developers had to manually detect 401 responses and implement their own re-authentication logic. The SDK now provides three flexible approaches to handle token expiration transparently.

## FamilySearch Token Behavior

Before diving into implementation approaches, it's critical to understand how FamilySearch access tokens work:

### Token Lifetime Characteristics

**FamilySearch access tokens expire based on TWO conditions (whichever comes first):**

1. **Absolute Expiration**: 24 hours from token creation
2. **Inactivity Expiration**: 60 minutes since the last successful API call

### Key Behaviors

- ✅ **Each successful API call resets the 60-minute inactivity timer**
  - Making an API call within 60 minutes keeps the token alive
  - The token can remain valid for the full 24 hours if used regularly

- ❌ **No refresh tokens available**
  - FamilySearch does not support OAuth refresh token grants
  - When a token expires, you must re-authenticate to obtain a completely new token
  - This is different from many OAuth providers that support refresh tokens

- 🔍 **No `expires_in` field in OAuth responses**
  - The FamilySearch API returns: `{"access_token": "...", "token_type": "family_search"}`
  - Unlike standard OAuth, there is no `expires_in` field
  - The SDK tracks expiration **client-side** using timestamps

- 🤝 **401 responses don't distinguish expiration causes**
  - The API returns 401 for: expired tokens, invalid tokens, revoked tokens, missing tokens
  - The SDK uses client-side tracking to determine the likely reason

### Token Lifetime Calculation

The SDK calculates token expiration as:

```php
$expirationTime = min(
    $tokenCreationTime + 86400,      // 24 hours from creation
    $tokenLastActivityTime + 3600    // 60 minutes from last activity
);
```

**Examples:**

- **Fresh token, no activity**: Expires in 60 minutes (inactivity limit)
- **Token created 23 hours ago, used 30 minutes ago**: Expires in 1 hour (absolute limit)
- **Token created 2 hours ago, used 10 minutes ago**: Expires in 50 minutes (inactivity limit)

## Approach 1: Proactive Expiration Checking

The SDK provides methods to check token expiration **before** making API requests, allowing you to re-authenticate proactively.

### Basic Expiration Check

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'expirationWarningThreshold' => 300 // 5 minutes (default)
]);

// Check if token is expired or expiring soon
if ($fs->isTokenExpired()) {
    // Token is expired or within 5 minutes of expiration
    // Re-authenticate before making requests
    $fs->oauthPassword($username, $password);
}

// Now safe to make API requests
$response = $fs->get('/platform/tree/persons/PPPP-PPP');
```

### Display Expiration Time to User

```php
$expirationTime = $fs->getTokenExpirationTime();

if ($expirationTime) {
    $timeUntilExpiration = $expirationTime - time();
    $minutesRemaining = floor($timeUntilExpiration / 60);
    
    echo "Your session will expire in {$minutesRemaining} minutes\n";
    
    if ($minutesRemaining < 10) {
        echo "Warning: Your session is about to expire!\n";
    }
}
```

### Near-Expiration Detection

```php
// Configure a custom warning threshold (e.g., 10 minutes)
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'expirationWarningThreshold' => 600 // 10 minutes
]);

if ($fs->isTokenExpired()) {
    // Token is expired OR within 10 minutes of expiration
    echo "Time to re-authenticate!\n";
    $fs->oauthPassword($username, $password);
}
```

### Expiration Calculation Details

The SDK determines expiration based on **the sooner** of the two limits:

```php
// Get detailed token information
$tokenInfo = $fs->getAccessToken(true);

echo "Token created: " . date('Y-m-d H:i:s', $tokenInfo['created']) . "\n";
echo "Last activity: " . date('Y-m-d H:i:s', $tokenInfo['last_activity']) . "\n";
echo "Expires at: " . date('Y-m-d H:i:s', $tokenInfo['expires_at']) . "\n";
echo "Is expired: " . ($tokenInfo['is_expired'] ? 'Yes' : 'No') . "\n";

// Calculate which expiration limit applies
$absoluteExpiration = $tokenInfo['created'] + 86400;      // 24 hours
$inactivityExpiration = $tokenInfo['last_activity'] + 3600; // 60 minutes
$actualExpiration = min($absoluteExpiration, $inactivityExpiration);

if ($actualExpiration === $absoluteExpiration) {
    echo "Token will expire due to 24-hour absolute limit\n";
} else {
    echo "Token will expire due to 60-minute inactivity limit\n";
}
```

## Approach 2: Authentication Failure Callback

The SDK can automatically detect 401 responses and invoke a callback, allowing you to handle re-authentication transparently.

### Basic Callback Configuration

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'onAuthenticationFailure' => function($response, $reason) {
        // Called when a 401 response is received
        // $response: Full response object with statusCode 401
        // $reason: 'expired' or 'invalid'
        
        error_log("Authentication failed: {$reason}");
        
        if ($reason === 'expired') {
            // Token expired - re-authenticate
        } else {
            // Token is invalid - may have been revoked
        }
    }
]);
```

### Automatic Re-authentication (Password Grant)

**Best for:** Background processes, cron jobs, API integrations

```php
$username = $_ENV['FS_USERNAME'];
$password = $_ENV['FS_PASSWORD'];

$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        if ($reason === 'expired') {
            error_log('Token expired, re-authenticating automatically...');
            
            // Re-authenticate with password grant
            $authResponse = $fs->oauthPassword($username, $password);
            
            if ($authResponse->statusCode === 200) {
                error_log('Re-authentication successful');
            } else {
                error_log('Re-authentication failed');
                // Handle re-authentication failure
            }
        } else {
            error_log('Token invalid: ' . $reason);
            // Token was revoked or is otherwise invalid
            throw new Exception('Authentication token is invalid');
        }
    }
]);

// Make requests normally - re-authentication happens automatically
$response = $fs->get('/platform/tree/persons/PPPP-PPP');

// With automatic replay enabled (default), the request succeeds transparently
if ($response->statusCode === 200) {
    echo "Request succeeded!\n";
    
    // Check if the request was replayed after re-authentication
    if ($response->replayed ?? false) {
        echo "Note: Request was automatically retried after re-authentication\n";
    }
}
```

### Redirect to Login (Authorization Code Flow)

**Best for:** Web applications with user interaction

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'redirectUri' => 'https://myapp.com/oauth/callback',
    'environment' => 'production',
    'onAuthenticationFailure' => function($response, $reason) use (&$fs) {
        // Store the original request URL in session for post-login redirect
        $_SESSION['original_request'] = $_SERVER['REQUEST_URI'];
        
        if ($reason === 'expired') {
            error_log('Session expired, redirecting to login...');
        } else {
            error_log('Authentication invalid, redirecting to login...');
        }
        
        // Redirect user to FamilySearch OAuth authorization page
        header('Location: ' . $fs->oauthRedirectURL());
        exit;
    }
]);

// Your OAuth callback handler:
// oauth/callback.php
$fs->oauthResponse(); // Exchanges code for token

// Redirect back to original request
$originalRequest = $_SESSION['original_request'] ?? '/';
header('Location: ' . $originalRequest);
exit;
```

### Unauthenticated Session Renewal

**Best for:** Public API access, read-only operations

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'onAuthenticationFailure' => function($response, $reason) use (&$fs) {
        if ($reason === 'expired') {
            error_log('Unauthenticated session expired, requesting new session...');
            
            // Request a new unauthenticated session token
            // Note: This endpoint may vary - check FamilySearch API docs
            $response = $fs->post('/platform/authentication/unauthenticated-session', [
                'body' => ['client_id' => $_ENV['FS_APP_KEY']]
            ]);
            
            if ($response->statusCode === 200) {
                $fs->setAccessToken($response->data['access_token']);
                error_log('New unauthenticated session obtained');
            }
        }
    }
]);
```

### Understanding Failure Reasons

The callback receives a `$reason` parameter indicating why authentication failed:

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'onAuthenticationFailure' => function($response, $reason) {
        // $reason can be:
        // - 'expired': SDK's client-side tracking indicates token should be expired
        // - 'invalid': 401 occurred but token shouldn't be expired per tracking
        
        switch ($reason) {
            case 'expired':
                // Token expired due to:
                // - 24 hours passed since creation, OR
                // - 60 minutes passed since last activity
                // 
                // Action: Re-authenticate to get new token
                error_log('Token expired normally - re-authenticating');
                break;
                
            case 'invalid':
                // Token is invalid but not expired according to tracking.
                // Possible causes:
                // - Token was manually revoked
                // - Token was never valid (wrong token set)
                // - Server rejected token for other reasons
                // - Clock skew between client and server
                //
                // Action: Depends on your application logic
                error_log('Token invalid - may have been revoked');
                break;
        }
    }
]);
```

**Important:** The FamilySearch API always returns 401 for any authentication failure. The SDK uses **client-side token tracking** to determine whether the failure was due to expiration (`'expired'`) or another reason (`'invalid'`). This is the SDK's best guess based on timestamps, not information from the API.

## Approach 3: Enhanced Token Info Retrieval

The SDK provides enhanced methods to retrieve detailed token information for manual management.

### Get Detailed Token Information

```php
// Backward compatible: getAccessToken() returns string
$token = $fs->getAccessToken();
echo "Token: {$token}\n"; // String: "abc123..."

// Enhanced: getAccessToken(true) returns array with metadata
$tokenInfo = $fs->getAccessToken(true);

print_r($tokenInfo);
/*
Array (
    [token] => abc123...
    [created] => 1234567890          // Unix timestamp when token was created
    [last_activity] => 1234567900    // Unix timestamp of last successful API call
    [expires_at] => 1234571490       // Unix timestamp when token will expire
    [is_expired] => false            // Whether token is expired or expiring soon
)
*/

// Use detailed info for custom logic
if ($tokenInfo['is_expired']) {
    echo "Token is expired or expiring soon\n";
} else {
    $timeRemaining = $tokenInfo['expires_at'] - time();
    echo "Token valid for {$timeRemaining} more seconds\n";
}
```

### Manual Token Management with `setAccessToken()`

```php
// When manually setting a token, the SDK tracks expiration automatically
$fs = new FamilySearch(['appKey' => $_ENV['FS_APP_KEY']]);

// Option 1: Set token without expiration info (common with FamilySearch)
// SDK treats it as freshly issued: creation=now, last_activity=now
$fs->setAccessToken('your-access-token-here');

// Option 2: Set token with known remaining lifetime
// Use this when restoring a token from storage and you know how long it has left
$fs->setAccessToken('your-access-token-here', 3600); // Token has 1 hour remaining

// The SDK calculates:
// - Creation time: now - (86400 - $expiresIn)
// - Last activity: now
// - Expiration: min(creation + 24hrs, last_activity + 60min)
```

### Custom Token Storage and Retrieval

```php
// Example: Store token in database with expiration info
class TokenStorage {
    public static function saveToken($token, $created, $lastActivity) {
        $db = getDatabase();
        $db->query(
            "INSERT INTO tokens (token, created_at, last_activity) VALUES (?, ?, ?)",
            [$token, $created, $lastActivity]
        );
    }
    
    public static function loadToken() {
        $db = getDatabase();
        $row = $db->query("SELECT * FROM tokens WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        
        if ($row) {
            // Calculate remaining time
            $created = strtotime($row['created_at']);
            $lastActivity = strtotime($row['last_activity']);
            $absoluteExpiration = $created + 86400;
            $inactivityExpiration = $lastActivity + 3600;
            $expirationTime = min($absoluteExpiration, $inactivityExpiration);
            $remainingTime = $expirationTime - time();
            
            if ($remainingTime > 0) {
                return [
                    'token' => $row['token'],
                    'expires_in' => $remainingTime
                ];
            }
        }
        
        return null;
    }
}

// Use custom storage with SDK
$fs = new FamilySearch(['appKey' => $_ENV['FS_APP_KEY']]);

$tokenData = TokenStorage::loadToken();
if ($tokenData) {
    // Restore token with remaining lifetime
    $fs->setAccessToken($tokenData['token'], $tokenData['expires_in']);
} else {
    // No valid token - authenticate
    $response = $fs->oauthPassword($username, $password);
    
    // Save new token
    $tokenInfo = $fs->getAccessToken(true);
    TokenStorage::saveToken(
        $tokenInfo['token'],
        $tokenInfo['created'],
        $tokenInfo['last_activity']
    );
}
```

## Activity Tracking

The SDK automatically tracks API activity to manage the 60-minute inactivity window.

### Automatic Activity Tracking

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production'
]);

// Authenticate
$fs->oauthPassword($username, $password);

// Each successful API call resets the 60-minute inactivity timer
$fs->get('/platform/tree/persons/PPPP-PPP');  // Last activity updated
sleep(1800); // Wait 30 minutes
$fs->get('/platform/collection');              // Last activity updated again
sleep(1800); // Wait another 30 minutes
$fs->get('/platform/users/current');           // Last activity updated again

// Token remains valid because we used it within 60 minutes each time
// Without this activity, token would expire after 60 minutes of inactivity
```

### Check Time Until Expiration

```php
$fs = new FamilySearch(['appKey' => $_ENV['FS_APP_KEY']]);
$fs->oauthPassword($username, $password);

function checkExpiration($fs) {
    $expirationTime = $fs->getTokenExpirationTime();
    $now = time();
    
    $secondsRemaining = $expirationTime - $now;
    $minutesRemaining = floor($secondsRemaining / 60);
    
    echo "Token expires in: {$minutesRemaining} minutes\n";
    
    // Get details about which limit applies
    $tokenInfo = $fs->getAccessToken(true);
    $absoluteExpiration = $tokenInfo['created'] + 86400;
    $inactivityExpiration = $tokenInfo['last_activity'] + 3600;
    
    if ($expirationTime === $absoluteExpiration) {
        echo "Expiring due to: 24-hour absolute limit\n";
        $hoursFromCreation = floor((time() - $tokenInfo['created']) / 3600);
        echo "Token age: {$hoursFromCreation} hours\n";
    } else {
        echo "Expiring due to: 60-minute inactivity limit\n";
        $minutesSinceActivity = floor((time() - $tokenInfo['last_activity']) / 60);
        echo "Minutes since last activity: {$minutesSinceActivity}\n";
    }
}

checkExpiration($fs);

// Make an API call
$fs->get('/platform/users/current');

checkExpiration($fs); // Expiration extended due to activity
```

### Activity Tracking Behavior

**What updates last activity:**
- ✅ Successful API responses (2xx status codes)
- ✅ Redirect responses (3xx status codes)

**What does NOT update last activity:**
- ❌ 401 Unauthorized responses (authentication failures)
- ❌ 4xx Client errors (except redirects)
- ❌ 5xx Server errors
- ❌ Network failures or timeouts

**Important Notes:**
- Activity tracking extends the **inactivity window** (60 minutes)
- Activity tracking does **NOT extend** the **absolute expiration** (24 hours)
- A token created 23 hours ago will expire in 1 hour, regardless of activity

## Re-authentication Methods (Not Refresh)

FamilySearch does not support refresh tokens. When a token expires, you must **re-authenticate** to obtain a completely new token.

### Re-authentication vs. Refresh

**❌ What FamilySearch does NOT have:**
```php
// This does NOT work with FamilySearch (no refresh tokens)
$newToken = $fs->refreshToken($refreshToken); // ❌ Not supported
```

**✅ What you must do instead:**
```php
// Re-authenticate to get a completely new token ✅
$response = $fs->oauthPassword($username, $password);
$newToken = $response->data['access_token'];
```

### Password Grant Re-authentication

**Use when:** You have stored user credentials (backend services, cron jobs)

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production'
]);

// Initial authentication
$response = $fs->oauthPassword($username, $password);

if ($response->statusCode === 200) {
    echo "Authenticated successfully\n";
} else {
    die("Authentication failed\n");
}

// Later, when token expires...
if ($fs->isTokenExpired()) {
    echo "Token expired, re-authenticating...\n";
    
    // Re-authenticate with same credentials
    $response = $fs->oauthPassword($username, $password);
    
    if ($response->statusCode === 200) {
        echo "Re-authenticated successfully\n";
        // New token automatically stored in SDK
    }
}
```

### Authorization Code Flow Re-authentication

**Use when:** Building web applications with user interaction

```php
// When token expires, redirect user to authorization
if ($fs->isTokenExpired()) {
    $_SESSION['post_auth_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . $fs->oauthRedirectURL());
    exit;
}

// In your OAuth callback handler:
// callback.php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'redirectUri' => 'https://myapp.com/callback.php'
]);

$response = $fs->oauthResponse(); // Exchanges authorization code for token

if ($response->statusCode === 200) {
    // New token obtained and stored automatically
    $redirect = $_SESSION['post_auth_redirect'] ?? '/';
    header('Location: ' . $redirect);
    exit;
}
```

### Unauthenticated Session Re-authentication

**Use when:** Accessing public data without user credentials

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production'
]);

function ensureAuthenticated($fs) {
    if ($fs->isTokenExpired() || !$fs->getAccessToken()) {
        // Request new unauthenticated session
        $response = $fs->post('/path/to/unauthenticated-endpoint', [
            'body' => ['client_id' => $_ENV['FS_APP_KEY']]
        ]);
        
        if ($response->statusCode === 200) {
            $fs->setAccessToken($response->data['access_token']);
        }
    }
}

// Use before making API requests
ensureAuthenticated($fs);
$response = $fs->get('/platform/collection');
```

### Combining Re-authentication with Callback

```php
$username = $_ENV['FS_USERNAME'];
$password = $_ENV['FS_PASSWORD'];

$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'replayFailedRequestsAfterAuth' => true, // Enable automatic replay (default)
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        if ($reason === 'expired') {
            // Token expired - re-authenticate automatically
            $authResponse = $fs->oauthPassword($username, $password);
            
            if ($authResponse->statusCode === 200) {
                error_log('Automatically re-authenticated after token expiration');
                // SDK will automatically retry the original request
            } else {
                error_log('Re-authentication failed: ' . $authResponse->statusCode);
            }
        }
    }
]);

// Make requests normally - re-authentication happens transparently
$response = $fs->get('/platform/tree/persons/PPPP-PPP');

// If token was expired:
// 1. Request fails with 401
// 2. Callback re-authenticates
// 3. Request is automatically retried
// 4. Response is successful
if ($response->statusCode === 200) {
    echo "Request succeeded\n";
    
    if ($response->replayed ?? false) {
        echo "Request was automatically retried after re-authentication\n";
    }
}
```

## Configuration Options

### All Available Configuration Options

```php
$fs = new FamilySearch([
    // Basic OAuth configuration
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production', // 'integration', 'beta', or 'production'
    'redirectUri' => 'https://myapp.com/callback',
    
    // Session configuration
    'sessions' => true, // Enable automatic session storage (default: true)
    'sessionVariable' => 'FS_ACCESS_TOKEN', // Session variable name (default)
    
    // Encryption configuration (optional, recommended for production)
    'sessionEncryption' => true, // Encrypt session tokens (default: false)
    'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY'],
    
    // Token expiration configuration
    'expirationWarningThreshold' => 300, // Seconds before expiration to warn (default: 300 = 5 minutes)
    
    // Authentication failure handling
    'onAuthenticationFailure' => function($response, $reason) {
        // Your callback logic here
    },
    'replayFailedRequestsAfterAuth' => true, // Automatically retry after re-auth (default: true)
    
    // Other options
    'accessToken' => null, // Manually provide token (bypasses session)
    'maxThrottledRetries' => 5, // Retry limit for 429 responses (default: 5)
    'userAgent' => 'MyApp/1.0', // Additional user agent string
    'objects' => false, // Enable gedcomx-php objects (default: false)
    'pendingModifications' => ['feature-flag'] // Feature flags for pending API changes
]);
```

### Expiration Warning Threshold

The `expirationWarningThreshold` determines when `isTokenExpired()` returns `true`:

```php
// Default: 5 minutes
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'expirationWarningThreshold' => 300
]);

// Token expires at 10:00:00
// At 09:55:00 (5 minutes before), isTokenExpired() returns true
// At 09:54:59, isTokenExpired() returns false

// Custom: 10 minutes
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'expirationWarningThreshold' => 600
]);

// Token expires at 10:00:00
// At 09:50:00 (10 minutes before), isTokenExpired() returns true

// Exact expiration (no warning threshold)
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'expirationWarningThreshold' => 0
]);

// isTokenExpired() only returns true when token has actually expired
```

### Request Replay Configuration

Control automatic request replay after re-authentication:

```php
// Enabled (default): Failed requests automatically retried after re-auth
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'replayFailedRequestsAfterAuth' => true, // Default
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        // Re-authenticate
        $fs->oauthPassword($username, $password);
        // SDK automatically retries the original request
    }
]);

// Disabled: Manual retry required
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'replayFailedRequestsAfterAuth' => false,
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        // Re-authenticate
        $fs->oauthPassword($username, $password);
        // Application must manually retry the request
    }
]);

$response = $fs->get('/platform/users/current');
if ($response->statusCode === 401) {
    // Callback was invoked and re-authenticated, but no automatic retry
    // Manually retry the request
    $response = $fs->get('/platform/users/current');
}
```

## Request Replay Behavior

When automatic replay is enabled (default), failed requests are transparently retried after successful re-authentication.

### How Replay Works

1. **Original request fails with 401**
2. **Callback invoked** with response and reason ('expired' or 'invalid')
3. **Callback re-authenticates** (calls `oauthPassword()` or `setAccessToken()`)
4. **SDK detects token change** (compares token before/after callback)
5. **Request automatically retried** with new token
6. **Successful response returned** (or second 401 if new token also fails)

### Replay Metadata

Responses from replayed requests include additional metadata:

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        $fs->oauthPassword($username, $password);
    }
]);

$response = $fs->get('/platform/tree/persons/PPPP-PPP');

// Check if request was replayed
if ($response->replayed ?? false) {
    echo "This request was automatically retried after re-authentication\n";
    
    // Access the original 401 response
    echo "Original status: {$response->originalResponse->statusCode}\n";
    echo "New status: {$response->statusCode}\n";
}
```

### Replay Safety: Single Retry Only

To prevent infinite loops, replay only happens **once per request**:

```php
$callbackCount = 0;

$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, &$callbackCount) {
        $callbackCount++;
        
        // Set another invalid token (this is intentionally wrong for demonstration)
        $fs->setAccessToken('still-invalid-token-' . $callbackCount);
    }
]);

$response = $fs->get('/platform/users/current');

// Callback is invoked twice:
// 1. For original 401
// 2. For replay 401
// But no third retry occurs (prevents infinite loop)
echo "Callback invoked: {$callbackCount} times\n"; // Output: 2
echo "Final status: {$response->statusCode}\n";    // Output: 401
```

### When Replay Occurs

Replay happens when **ALL** of these conditions are met:

1. ✅ Response status is 401
2. ✅ `onAuthenticationFailure` callback is configured
3. ✅ Callback obtains a new token (token changes)
4. ✅ `replayFailedRequestsAfterAuth` is `true` (default)
5. ✅ This is not already a retry (prevents loops)

### When Replay Does NOT Occur

No replay in these cases:

- ❌ No callback configured
- ❌ Callback doesn't obtain new token
- ❌ `replayFailedRequestsAfterAuth` is `false`
- ❌ Response status is not 401 (other errors)
- ❌ This is already a retry attempt

## Backward Compatibility

The token expiration features are **100% backward compatible**. Existing code continues to work without any changes.

### No Changes Required

```php
// Existing code works unchanged
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production'
]);

$fs->oauthPassword($username, $password);
$token = $fs->getAccessToken(); // Still returns string
$response = $fs->get('/platform/users/current');
```

### Opt-In Enhancement

New features are opt-in and don't affect existing behavior:

```php
// Old way (still works)
$token = $fs->getAccessToken();
if (is_string($token)) {
    echo "Token: $token\n"; // ✅ Works
}

// New way (opt-in)
$tokenInfo = $fs->getAccessToken(true); // Pass true for detailed info
if (is_array($tokenInfo)) {
    echo "Token: {$tokenInfo['token']}\n";
    echo "Expires at: {$tokenInfo['expires_at']}\n";
}
```

### Session Format Migration

The SDK automatically migrates old session formats:

```php
// Old session format (plaintext token string)
$_SESSION['FS_ACCESS_TOKEN'] = 'abc123';

// SDK automatically detects and migrates:
$fs = new FamilySearch(['appKey' => $_ENV['FS_APP_KEY']]);
$token = $fs->getAccessToken(); // ✅ Works

// Next OAuth authentication stores new format with metadata
$fs->oauthPassword($username, $password);
// Session now contains: {"token":"xyz789","created":...,"last_activity":...}
```

### Default Behavior

All new features have sensible defaults that maintain existing behavior:

| Feature | Default | Impact |
|---------|---------|--------|
| `expirationWarningThreshold` | 300 seconds | Conservative threshold |
| `onAuthenticationFailure` | `null` | No callback (existing behavior) |
| `replayFailedRequestsAfterAuth` | `true` | Replay enabled (if callback configured) |
| `getAccessToken()` | Returns string | Backward compatible |
| Token tracking | Automatic | Transparent (no code changes needed) |

## Migration Guide

### Adding Expiration Tracking to Existing Code

**Step 1: Assess Current Implementation**

```php
// Current code (before migration)
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production'
]);

$response = $fs->get('/platform/tree/persons/PPPP-PPP');

if ($response->statusCode === 401) {
    // Manual re-authentication
    $fs->oauthPassword($username, $password);
    
    // Manual retry
    $response = $fs->get('/platform/tree/persons/PPPP-PPP');
}
```

**Step 2: Add Callback for Automatic Handling**

```php
// After migration (automatic handling)
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        if ($reason === 'expired') {
            $fs->oauthPassword($username, $password);
            // Automatic retry happens (no manual retry needed)
        }
    }
]);

// Simplified code - no manual 401 handling needed
$response = $fs->get('/platform/tree/persons/PPPP-PPP');
// Request succeeds even if token was expired (automatic re-auth + retry)
```

**Step 3 (Optional): Add Proactive Checking**

```php
// Add proactive expiration checking for better UX
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'expirationWarningThreshold' => 600, // Check 10 minutes before expiration
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        $fs->oauthPassword($username, $password);
    }
]);

// Check before making requests
if ($fs->isTokenExpired()) {
    echo "Token is expiring soon, re-authenticating proactively...\n";
    $fs->oauthPassword($username, $password);
}

// Make requests
$response = $fs->get('/platform/tree/persons/PPPP-PPP');
```

### Migration Checklist

- [ ] **Review current 401 handling** - Identify where you manually handle authentication failures
- [ ] **Add callback** - Implement `onAuthenticationFailure` callback with re-authentication logic
- [ ] **Remove manual 401 checks** - Let callback handle 401s automatically (optional - both approaches work)
- [ ] **Test re-authentication** - Verify callback re-authenticates correctly
- [ ] **Add proactive checking** - Use `isTokenExpired()` before long-running operations (optional)
- [ ] **Configure threshold** - Adjust `expirationWarningThreshold` for your use case (optional)
- [ ] **Enable encryption** - Add `sessionEncryption` for production (recommended, see SECURITY.md)

### Zero-Downtime Migration

The SDK supports gradual migration without downtime:

```php
// Phase 1: Keep existing 401 handling, add callback for logging
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'replayFailedRequestsAfterAuth' => false, // Disable replay initially
    'onAuthenticationFailure' => function($response, $reason) {
        // Just log for now, don't re-authenticate yet
        error_log("Would have re-authenticated: {$reason}");
    }
]);

// Existing manual handling still works
$response = $fs->get('/platform/tree/persons/PPPP-PPP');
if ($response->statusCode === 401) {
    $fs->oauthPassword($username, $password);
    $response = $fs->get('/platform/tree/persons/PPPP-PPP');
}

// Phase 2: Enable automatic re-authentication in callback
// (Update callback to actually re-authenticate)

// Phase 3: Enable automatic replay
// (Set replayFailedRequestsAfterAuth to true)

// Phase 4: Remove manual 401 handling
// (Callback handles everything automatically)
```

## Addressing Issue #2 (2016)

This token expiration handling feature directly addresses [Issue #2](https://github.com/FamilySearch/fs-php-lite/issues/2) opened in 2016.

### The Original Problem

Before this enhancement (issue opened 2016-09-09):

```php
// Developers had to manually detect 401s and re-authenticate
$response = $fs->get('/platform/tree/persons/PPPP-PPP');

if ($response->statusCode === 401) {
    // Manual detection required
    // Manual re-authentication required
    // Manual retry required
    $fs->oauthPassword($username, $password);
    $response = $fs->get('/platform/tree/persons/PPPP-PPP');
}

// Problems:
// - No visibility into token expiration
// - No proactive handling possible
// - Repetitive boilerplate code
// - No automatic retry mechanism
// - No distinction between expired vs. invalid tokens
```

### The Solution (2024)

After this enhancement (versions 1.4.0 - 1.6.0):

```php
// Three flexible approaches with automatic handling
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        $fs->oauthPassword($username, $password);
        // Automatic retry - no manual code needed
    }
]);

// Simplified code - no manual 401 handling needed
$response = $fs->get('/platform/tree/persons/PPPP-PPP');
// Works transparently even if token expired

// Benefits:
// ✅ Proactive expiration checking (isTokenExpired)
// ✅ Expiration time visibility (getTokenExpirationTime)
// ✅ Automatic callback-based re-authentication
// ✅ Automatic request replay
// ✅ Clear distinction: 'expired' vs 'invalid'
// ✅ Activity tracking (60-minute window)
// ✅ Absolute expiration tracking (24-hour window)
```

### Why It Took 8 Years

The original issue required understanding FamilySearch's unique token behavior:

1. **No `expires_in` field** - Required client-side tracking implementation
2. **Dual expiration conditions** - Both 24-hour absolute AND 60-minute inactivity
3. **Activity resets timer** - Each API call extends the 60-minute window
4. **No refresh tokens** - Must re-authenticate, not refresh
5. **Backward compatibility** - Existing implementations must continue working

This enhancement was carefully designed to address all these requirements while maintaining full backward compatibility.

## Examples Summary

### Quick Reference

```php
// Approach 1: Proactive Checking
if ($fs->isTokenExpired()) {
    $fs->oauthPassword($username, $password);
}

// Approach 2: Automatic Callback
$fs = new FamilySearch([
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        $fs->oauthPassword($username, $password);
    }
]);

// Approach 3: Enhanced Token Info
$tokenInfo = $fs->getAccessToken(true);
echo "Expires: " . date('Y-m-d H:i:s', $tokenInfo['expires_at']);
```

### Complete Working Example

```php
<?php
require 'vendor/autoload.php';

$username = $_ENV['FS_USERNAME'];
$password = $_ENV['FS_PASSWORD'];

// Create SDK with automatic re-authentication
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'sessions' => true,
    'sessionEncryption' => true,
    'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY'],
    'expirationWarningThreshold' => 300, // Warn 5 minutes before expiration
    'replayFailedRequestsAfterAuth' => true, // Enable automatic replay
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        error_log("Authentication failed: {$reason}");
        
        if ($reason === 'expired') {
            // Re-authenticate automatically
            $authResponse = $fs->oauthPassword($username, $password);
            
            if ($authResponse->statusCode === 200) {
                error_log('Successfully re-authenticated');
            } else {
                error_log('Re-authentication failed');
                throw new Exception('Unable to re-authenticate');
            }
        } else {
            // Token is invalid (possibly revoked)
            throw new Exception('Authentication token is invalid');
        }
    }
]);

// Initial authentication
$response = $fs->oauthPassword($username, $password);
if ($response->statusCode !== 200) {
    die("Initial authentication failed\n");
}

// Display token information
$tokenInfo = $fs->getAccessToken(true);
echo "Authenticated successfully\n";
echo "Token expires: " . date('Y-m-d H:i:s', $tokenInfo['expires_at']) . "\n";

// Make API requests - re-authentication happens automatically if needed
$response = $fs->get('/platform/users/current');
if ($response->statusCode === 200) {
    echo "Request successful\n";
    
    if ($response->replayed ?? false) {
        echo "Note: Request was automatically retried after token expiration\n";
    }
}

// Check expiration status
if ($fs->isTokenExpired()) {
    echo "Warning: Token is expired or expiring soon\n";
} else {
    $timeRemaining = $fs->getTokenExpirationTime() - time();
    $minutesRemaining = floor($timeRemaining / 60);
    echo "Token valid for {$minutesRemaining} more minutes\n";
}
```

## Additional Resources

- **Main README**: [README.md](../README.md) - Getting started and basic usage
- **Security Guide**: [SECURITY.md](../SECURITY.md) - Session encryption and security best practices
- **Testing Guide**: [TESTING.md](../TESTING.md) - Writing tests for your application
- **FamilySearch API Docs**: https://developers.familysearch.org/
- **Issue #2 (2016)**: https://github.com/FamilySearch/fs-php-lite/issues/2

## Support

For questions, issues, or feature requests, please:
- Open an issue on GitHub: https://github.com/FamilySearch/fs-php-lite/issues
- Check the FamilySearch Developer Forum: https://developers.familysearch.org/

---

**Version Requirements**: Token expiration tracking requires fs-php-lite v1.4.0 or higher.

- v1.4.0: Token expiration tracking (`isTokenExpired()`, `getTokenExpirationTime()`)
- v1.5.0: Authentication failure callback (`onAuthenticationFailure`)
- v1.6.0: Automatic request replay (`replayFailedRequestsAfterAuth`)
