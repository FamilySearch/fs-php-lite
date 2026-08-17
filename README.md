# FamilySearch PHP Lite SDK

[![Packagist](https://img.shields.io/packagist/v/familysearch/fs-php-lite.svg)](https://packagist.org/packages/familysearch/fs-php-lite)
![Tests](https://github.com/FamilySearch/fs-php-lite/workflows/Tests/badge.svg?branch=master)
[![PHP Version](https://img.shields.io/badge/php-8.0%20%7C%208.1%20%7C%208.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-blue.svg)](https://github.com/FamilySearch/fs-php-lite)

> **⚠️ Security Notice:** Access tokens are stored in plaintext by default. Enable encryption in production. See [Security Considerations](#security-considerations).

Lite PHP SDK for the [FamilySearch API](https://familysearch.org/developers/).

__Warning__: this SDK requires hard-coding the API endpoint URLs. That is
considered bad practice when using the API. In most cases, FamilySearch does not
consider URL changes as breaking changes. Read more about 
[dealing with change](https://familysearch.org/developers/docs/guides/evolution).

There is a sample app in the `/examples` directory that is deployed to 
http://fs-php-lite-sdk.herokuapp.com/examples/.

## Usage

```php

include_once('FamilySearch.php');

// Create the SDK instance
$fs = new FamilySearch([
  'environment' => 'production',
  'appKey' => $_ENV['FS_APP_KEY'], // NEVER hardcode credentials - use environment variables
  'redirectUri' => 'https://example.com/fs-redirect',
  
  // Tell it to automatically save and load the access token from $_SESSION. 
  'sessions' => true, // This defaults to true
  'sessionVariable' => 'FS_ACCESS_TOKEN',
  
  // RECOMMENDED: Enable AES-256-GCM encryption for session tokens in production
  'sessionEncryption' => true,
  'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY'], // NEVER hardcode the key!
  
  // Necessary for when the developer wants to store the accessToken somewhere
  // besides $_SESSION
  'accessToken' => '',
  
  // How many times should a throttled response be retried? Defaults to 5
  'maxThrottledRetries' => 5,
  
  // Activate pending modifications
  'pendingModifications' => ['consolidate-redundant-resources', 'current-person-401'],
  
  // Modify the default user agent by appending this value
  'userAgent' => 'myApp/1.2.3',
  
  // Enable optional serialization and deserialization with objects via gedcomx-php
  'objects' => true
]);

// OAuth step 1: Redirect
$fs->oauthRedirect();

// OAuth step 2: Exchange the code for an access token.
//
// This will automatically retrieve the code from $_GET and exchange it for
// an access token. The access token is contained in the response object if the
// request was successful. The token doesn't need to be saved to a variable if
// sessions are enabled because the SDK will automatically save it.
$response = $fs->oauthResponse();

// Get the current user
$response = $fs->get('/platform/users/current');

// All response objects have the following properties
$response->statusCode;     // Integer
$response->statusText;     // String
$response->headers;        // Array
$response->effectiveUrl;   // String
$response->body;           // String
$response->requestMethod;  // String
$response->requestHeaders; // Array
$response->requestBody;    // String
$response->redirected;     // Boolean; defaults to false
$response->throttled;      // Boolean; defaults to false
$response->curl;           // A reference to the curl resource for the request

// If the response included JSON in the body then it will be parsed into an
// associative array and be available via the `data` property.
$response->data; 

// If a request is forwarded then the response will contain the original URL
$response->originalUrl;

// If a request is throttled then the response will tell how many times it was
// throttled until it finally succeeded.
$response->retries;

// You can POST too. The body may be an array or a string.
$response = $fs->post('/platform/tree/persons/PPPP-PPP', [
  'body' => $personData
]);

// The SDK defaults the Accept and Content-Type headers to application/x-fs-v1+json
// for all /platform/ URLs. But that doesn't work for some endpoints that require
// the atom data format so you'll need to set the headers yourself.
$response = $fs->get('/platform/tree/persons/PPPP-PPP/matches?collection=records', [
  'headers' => [
    'Accept' => 'application/x-gedcomx-atom+json'  
  ]
]);

// You can also pass the query parameters to the HTTP methods if you don't want
// to construct the URL yourself.
$response = $fs->get('/platform/tree/persons/PPPP-PPP/matches', [
  'query' => [
    'collection' => 'records'
  ],
  'headers' => [
    'Accept' => 'application/x-gedcomx-atom+json'  
  ]
]);

// Supported HTTP methods are `get()`, `post()`, `head()`, and `delete()`. They
// all call the core `request()` method which has the same signature.
$response = $fs->request('/platform/tree/persons/PPPP-PPP', [
  'method' => 'POST',
  'body' => $personData
]);
```

## Security Considerations

### Session Token Encryption

**⚠️ Important:** By default, OAuth access tokens are stored in PHP `$_SESSION` in **plaintext**. This means tokens can be read by anyone with filesystem access to your server's session directory (typically `/var/lib/php/sessions`).

**For production deployments**, enable optional **AES-256-GCM encryption** to protect tokens at rest:

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    
    // Enable session encryption (RECOMMENDED for production)
    'sessionEncryption' => true,
    'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY']
]);
```

#### Generating an Encryption Key

Generate a secure 32-byte encryption key:

```bash
# Generate a base64-encoded key (recommended)
php -r "echo base64_encode(random_bytes(32));"
# Output: WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo=

# Or generate a hex-encoded key
php -r "echo bin2hex(random_bytes(32));"
# Output: 4c0bd859f72d55003baa72e76fea385e599c9562b1b75a1fec0831b19f04118a
```

#### Key Storage Best Practices

**✅ DO:**
- Store encryption keys in **environment variables**
- Use a secrets manager (AWS Secrets Manager, HashiCorp Vault, Azure Key Vault)
- Use different keys for different environments (dev, staging, production)
- Rotate keys periodically (every 90 days recommended)

**❌ DO NOT:**
- Hardcode keys in source code
- Commit keys to version control
- Reuse the same key across environments
- Use weak or predictable keys

**Example with environment variable:**

```bash
# Set environment variable
export FS_SESSION_ENCRYPTION_KEY="WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo="

# Or in .env file (excluded from Git)
echo "FS_SESSION_ENCRYPTION_KEY=WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo=" >> .env
```

#### What Encryption Protects

Session token encryption protects against:

- ✅ **Filesystem access** - Attackers who gain read access to session files
- ✅ **Backup exposure** - Tokens remain protected in backups
- ✅ **Disk forensics** - Deleted session files cannot reveal plaintext tokens
- ✅ **Accidental logging** - Encrypted values logged instead of plaintext
- ✅ **Shared hosting risks** - Other tenants cannot read your tokens

#### What Encryption Does NOT Protect Against

Encryption is **not a silver bullet**. It does **not** protect against:

- ❌ **Active server compromise** - Attackers with code execution can access keys
- ❌ **Memory dumps** - Tokens are plaintext in memory during request processing
- ❌ **XSS attacks** - Client-side attacks bypass server-side encryption
- ❌ **Session hijacking** - Valid session IDs grant access regardless of encryption
- ❌ **Network interception** - HTTPS is required separately

**Bottom Line:** Encryption protects data **at rest**. You also need HTTPS, secure session management, XSS protection, and proper server hardening.

### Enabling Encryption on Existing Deployments

Enabling encryption on an existing application is **seamless and backward-compatible**. No downtime or manual migration required.

#### Step 1: Generate Encryption Key

```bash
php -r "echo base64_encode(random_bytes(32));"
# Copy the output: WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo=
```

#### Step 2: Store in Environment Variable

```bash
# Development/staging
export FS_SESSION_ENCRYPTION_KEY="your-generated-key-here"

# Production (use your deployment platform's secrets management)
# Heroku: heroku config:set FS_SESSION_ENCRYPTION_KEY="your-key"
# AWS: Store in Parameter Store or Secrets Manager
# Docker: Use Docker secrets
```

#### Step 3: Update SDK Configuration

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'sessionEncryption' => true,  // Add this line
    'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY']  // Add this line
]);
```

#### Step 4: Deploy Changes

Deploy your updated application. **No manual intervention needed.**

#### Step 5: Automatic Migration

The migration happens automatically:

1. **Existing sessions** with plaintext tokens continue to work (backward compatible)
2. **New OAuth flows** store tokens encrypted
3. When users re-authenticate, their tokens are encrypted automatically
4. After natural session expiration (~24 hours), all tokens are encrypted

**No forced logout. No disruption. No manual migration scripts required.**

#### Verification

Verify encryption is working:

```bash
# Check session files (tokens should look encrypted)
sudo cat /var/lib/php/sessions/sess_* | grep FS_ACCESS_TOKEN

# Encrypted format looks like: s:120:"base64data:base64data:base64data";
# Plaintext format looks like: s:45:"actual-token-value-here";
```

### Additional Security Recommendations

1. **Enable HTTPS** - Always use HTTPS in production
2. **Secure session cookies** - Set `session.cookie_secure = 1` in `php.ini`
3. **HTTPOnly cookies** - Set `session.cookie_httponly = 1` to prevent XSS
4. **SameSite cookies** - Set `session.cookie_samesite = "Strict"` for CSRF protection
5. **Session directory permissions** - Ensure session files are not world-readable:
   ```bash
   sudo chmod 700 /var/lib/php/sessions
   ```
6. **Regular key rotation** - Rotate encryption keys every 90 days

For comprehensive security guidance, see **[SECURITY.md](SECURITY.md)** which includes:
- Detailed threat model
- Server configuration best practices
- Key rotation procedures
- Production deployment checklist
- Incident response guidelines

## Serialization with gedcomx-php

When the `objects` configuration option is set to true, the 
[gedcomx-php](https://github.com/FamilySearch/gedcomx-php) library can be used
for serialization from objects for requests and deserialization into objects
for responses.

```php
$fs = new FamilySearch({
    'objects' => true
});

$response = $fs->post('/platform/tree/persons', [
    'body' => new \Gedcomx\Extensions\FamilySearch\FamilySearchPlatform([
        'persons' => [ $personData ]
    ])
]);

$persons = $response->gedcomx->getPersons();
```

When a response body is present, it will be deserialized as either an 
[Atom Feed](http://familysearch.github.io/gedcomx-php/class-Gedcomx.Atom.Feed.html)
or a [FamilySearchPlatform](http://familysearch.github.io/gedcomx-php/class-Gedcomx.Extensions.FamilySearch.FamilySearchPlatform.html)
object.

gedcomx-php must be installed and included separately. gedcomx-php version 3.1.2
or later is required.

## Testing

The SDK includes comprehensive unit and integration tests with **76.33% code coverage**.

### Quick Start

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run only unit tests (fast, ~0.01s)
composer test:unit

# Run integration tests against live FamilySearch integration API
# Requires credentials (see below)
composer test:integration

# Generate code coverage report
composer test:coverage
```

### Test Suite Statistics

- **102 tests** with 232 assertions
- **76.33% line coverage**, 50.00% method coverage
- **74 unit tests** - Fast, no HTTP requests
- **28 integration tests** - Test against live FamilySearch integration API

### Test Structure

```
tests/
├── Unit/                    # 52 tests - SDK logic without HTTP
├── Integration/             # 11 tests - Full SDK against live API
├── fixtures/                # Test data (person.json)
└── bootstrap.php            # Test configuration
```

### Integration Test Credentials

Integration tests require FamilySearch integration environment credentials. Set these environment variables:

```bash
export FAMILYSEARCH_USERNAME="your-integration-username"
export FAMILYSEARCH_PASSWORD="your-integration-password"
export FAMILYSEARCH_API_KEY="your-api-key"
export FAMILYSEARCH_REDIRECT_URI="http://example.com/redirect"  # optional
```

**How to get credentials:**
1. Visit https://developers.familysearch.org/
2. Create an account and register an application
3. Request integration environment access
4. Use your integration credentials for testing

### Running Tests on Specific PHP Versions

```bash
# Using Docker
docker run --rm -v $(pwd):/app -w /app php:8.0-cli composer test
docker run --rm -v $(pwd):/app -w /app php:8.3-cli composer test

# Using phpenv (if installed)
phpenv local 8.0 && composer test
phpenv local 8.3 && composer test
```

### Viewing Coverage Reports

```bash
# Generate HTML coverage report
composer test:coverage

# Open in browser
open coverage/index.html
```

See [TESTING.md](TESTING.md) for detailed testing documentation.

## Requirements

- PHP 8.0 or higher
- ext-curl
- ext-json

## Development

### Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass: `composer test`
5. Submit a pull request

### CI/CD

Tests run automatically via GitHub Actions on:
- **PHP 8.0, 8.1, 8.2, 8.3, 8.4, and 8.5**
- **Every push** to master/main branches
- **Every pull request**
- **Code coverage** generated for PHP 8.3
- **Coverage reports** uploaded to Codecov

#### CI Status
- ✅ All PHP versions passing (8.0-8.5)
- ✅ 102 tests, 232 assertions
- ✅ 76.33% code coverage (258/338 lines)

See [.github/workflows/tests.yml](.github/workflows/tests.yml) for CI configuration.

### PHP Version Compatibility

**Minimum:** PHP 8.0  
**Tested:** PHP 8.0, 8.1, 8.2, 8.3, 8.4, 8.5  
**Recommended:** PHP 8.2+ for security updates

All tests pass on PHP 8.0-8.5 with zero deprecation warnings.