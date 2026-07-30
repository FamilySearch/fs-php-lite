# FamilySearch PHP Lite SDK

[![Packagist](https://img.shields.io/packagist/v/familysearch/fs-php-lite.svg)](https://packagist.org/packages/familysearch/fs-php-lite)
![Tests](https://github.com/FamilySearch/fs-php-lite/workflows/Tests/badge.svg?branch=master)
[![PHP Version](https://img.shields.io/badge/php-8.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-blue.svg)](https://github.com/FamilySearch/fs-php-lite)

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
  'appKey' => 'ahfud9Adjfia',
  'redirectUri' => 'https://example.com/fs-redirect',
  
  // Tell it to automatically save and load the access token from $_SESSION. 
  'sessions' => true, // This defaults to true
  'sessionVariable' => 'FS_ACCESS_TOKEN',
  
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

The SDK includes comprehensive unit and integration tests with **79.89% code coverage**.

### Quick Start

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run only unit tests (fast, ~0.01s)
composer test:unit

# Run only integration tests (with VCR, ~9s)
composer test:integration

# Generate code coverage report
composer test:coverage
```

### Test Suite Statistics

- **60 tests** with 123 assertions
- **79.89% line coverage**, 72.22% method coverage
- **52 unit tests** - Fast, no HTTP requests
- **11 integration tests** - Using recorded API responses (php-vcr)
- **2 skipped tests** - Due to VCR limitations (documented)

### Test Structure

```
tests/
├── Unit/                    # 52 tests - SDK logic without HTTP
├── Integration/             # 11 tests - Full SDK with recorded HTTP
├── fixtures/                # VCR cassettes (HTTP recordings)
└── bootstrap.php            # Test configuration
```

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

### Re-recording VCR Cassettes

VCR cassettes record API responses for fast, deterministic integration tests.

```bash
# Set credentials (required for re-recording)
export FAMILYSEARCH_USERNAME="your-sandbox-username"
export FAMILYSEARCH_PASSWORD="your-sandbox-password"
export FAMILYSEARCH_API_KEY="your-api-key"

# Delete old cassettes
rm tests/fixtures/test*.json

# Re-run integration tests (records new cassettes)
composer test:integration
```

**Note:** Cassettes should be re-recorded quarterly or when API changes.

See [TESTING.md](TESTING.md) for detailed testing documentation, and [RERECORD_VCR_GUIDE.md](RERECORD_VCR_GUIDE.md) for complete cassette re-recording instructions.

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
- **PHP 8.0, 8.1, 8.2, and 8.3** (full matrix)
- **Every push** to master/main branches
- **Every pull request**
- **Code coverage** generated for PHP 8.3
- **Coverage reports** uploaded to Codecov

#### CI Status
- ✅ All PHP versions passing (8.0-8.3)
- ✅ 60 tests, 123 assertions
- ✅ 79.89% code coverage
- ✅ No deprecation warnings

See [.github/workflows/tests.yml](.github/workflows/tests.yml) for CI configuration.

### PHP Version Compatibility

**Minimum:** PHP 8.0  
**Tested:** PHP 8.0, 8.1, 8.2, 8.3, 8.5  
**Recommended:** PHP 8.2+ for security updates

All tests pass on PHP 8.0-8.5 with zero deprecation warnings.