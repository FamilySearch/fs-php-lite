# Token Expiration Handling

## Overview

The FamilySearch PHP Lite SDK provides three flexible approaches to handle token expiration:

1. **Proactive Expiration Checking** - Check token status before making requests
2. **Authentication Failure Callbacks** - Automatically handle 401 responses
3. **Enhanced Token Info** - Access detailed token metadata for custom logic

## FamilySearch Token Behavior

**FamilySearch access tokens expire based on TWO conditions (whichever comes first):**

1. **Absolute Expiration**: 24 hours from token creation
2. **Inactivity Expiration**: 60 minutes since the last successful API call

**Important Characteristics:**
- Each successful API call resets the 60-minute inactivity timer
- No refresh tokens available - you must re-authenticate to get a new token
- No `expires_in` field in OAuth responses - the SDK tracks expiration client-side
- 401 responses don't distinguish between expired, invalid, or revoked tokens

## Approach 1: Proactive Expiration Checking

Check token expiration **before** making API requests to re-authenticate proactively.

### Basic Usage

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'expirationWarningThreshold' => 300 // 5 minutes (default)
]);

// Check if token is expired or expiring soon
if ($fs->isTokenExpired()) {
    $fs->oauthPassword($username, $password);
}

$response = $fs->get('/platform/tree/persons/PPPP-PPP');
```

### Display Expiration Time

```php
$expirationTime = $fs->getTokenExpirationTime();

if ($expirationTime) {
    $minutesRemaining = floor(($expirationTime - time()) / 60);
    echo "Your session will expire in {$minutesRemaining} minutes\n";
}
```

## Approach 2: Authentication Failure Callback

Automatically detect and handle 401 responses with a callback.

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
            // Re-authenticate with password grant
            $fs->oauthPassword($username, $password);
        } else {
            // Token was revoked or is invalid
            throw new Exception('Authentication token is invalid');
        }
    }
]);

// Make requests normally - re-authentication happens automatically
$response = $fs->get('/platform/tree/persons/PPPP-PPP');

// Check if the request was replayed after re-authentication
if ($response->replayed ?? false) {
    echo "Request was automatically retried after re-authentication\n";
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
        $_SESSION['original_request'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $fs->oauthRedirectURL());
        exit;
    }
]);
```

### Callback Reason Parameter

The callback receives a `$reason` parameter:
- **`'expired'`**: Token expired due to 24-hour or 60-minute limit (re-authenticate)
- **`'invalid'`**: Token is invalid - may have been revoked or never valid

## Approach 3: Enhanced Token Info Retrieval

Access detailed token metadata for custom management logic.

### Get Token Information

```php
// Standard: getAccessToken() returns string
$token = $fs->getAccessToken();

// Enhanced: getAccessToken(true) returns array with metadata
$tokenInfo = $fs->getAccessToken(true);
/*
Array (
    [token] => abc123...
    [created] => 1234567890          // Unix timestamp when token was created
    [last_activity] => 1234567900    // Unix timestamp of last successful API call
    [expires_at] => 1234571490       // Unix timestamp when token will expire
    [is_expired] => false            // Whether token is expired or expiring soon
)
*/

if ($tokenInfo['is_expired']) {
    echo "Token is expired or expiring soon\n";
}
```

### Manual Token Management

```php
$fs = new FamilySearch(['appKey' => $_ENV['FS_APP_KEY']]);

// Set token without expiration info (treated as freshly issued)
$fs->setAccessToken('your-access-token-here');

// Set token with known remaining lifetime
$fs->setAccessToken('your-access-token-here', 3600); // 1 hour remaining
```

## Activity Tracking

The SDK automatically tracks successful API calls (2xx, 3xx responses) to manage the 60-minute inactivity window. Each successful API call resets the inactivity timer, keeping the token alive for up to 24 hours of continued use.


## Configuration Options

### Key Token Expiration Options

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    
    // Token expiration
    'expirationWarningThreshold' => 300, // Seconds before expiration to warn (default: 5 min)
    
    // Authentication failure handling
    'onAuthenticationFailure' => function($response, $reason) {
        // Your callback logic here
    },
    'replayFailedRequestsAfterAuth' => true, // Automatically retry after re-auth (default)
]);
```

## Request Replay

When `replayFailedRequestsAfterAuth` is enabled (default), failed requests are automatically retried after successful re-authentication. The SDK retries once per request to prevent infinite loops. Check `$response->replayed` to see if a request was retried.


## Quick Reference

```php
// Approach 1: Proactive Checking
if ($fs->isTokenExpired()) {
    $fs->oauthPassword($username, $password);
}

// Approach 2: Automatic Callback
$fs = new FamilySearch([
    'onAuthenticationFailure' => function($response, $reason) use (&$fs, $username, $password) {
        if ($reason === 'expired') {
            $fs->oauthPassword($username, $password);
        }
    }
]);

// Approach 3: Enhanced Token Info
$tokenInfo = $fs->getAccessToken(true);
echo "Expires: " . date('Y-m-d H:i:s', $tokenInfo['expires_at']);
```

## Additional Resources

- [README.md](../README.md) - Getting started and basic usage
- [SECURITY.md](../SECURITY.md) - Session encryption and security best practices
- [FamilySearch API Documentation](https://developers.familysearch.org/)
