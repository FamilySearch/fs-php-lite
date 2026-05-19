# Testing Guide for fs-php-lite

This guide explains how to run and write tests for the FamilySearch PHP Lite SDK.

## Testing Strategy

The SDK uses a multi-layered testing approach:

1. **Unit Tests** - Test SDK methods in isolation without making HTTP requests
2. **Integration Tests** - Test SDK against recorded FamilySearch API responses using php-vcr
3. **Example Applications** - Working demos that serve as smoke tests

## Prerequisites

- PHP 8.1, 8.2, or 8.3
- Composer
- Xdebug (for code coverage)

## Installation

Install development dependencies:

```bash
composer install
```

## Running Tests

### Run All Tests
```bash
composer test
```

### Run Unit Tests Only
```bash
composer test:unit
```

### Run Integration Tests Only
```bash
composer test:integration
```

### Run with Code Coverage
```bash
composer test:coverage
```

This generates an HTML coverage report in the `coverage/` directory.

### Run Specific Test File
```bash
vendor/bin/phpunit tests/Unit/FamilySearchConfigTest.php
```

### Run Specific Test Method
```bash
vendor/bin/phpunit --filter testConstructorWithAccessToken
```

## Test Structure

```
tests/
├── bootstrap.php           # Test bootstrapping and VCR configuration
├── Unit/                   # Unit tests (no HTTP requests)
│   ├── FamilySearchConfigTest.php
│   └── FamilySearchHttpMethodsTest.php
├── Integration/            # Integration tests (with VCR recordings)
│   ├── ApiTestCase.php
│   ├── SandboxCredentials.php
│   └── FamilySearchIntegrationTest.php
└── fixtures/               # VCR cassettes and test data
    ├── testAuthenticate.json
    ├── testGet.json
    └── person.json
```

## Writing Tests

### Unit Tests

Unit tests should test SDK logic without making actual HTTP requests:

```php
<?php

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

class MyTest extends TestCase
{
    public function testSomething(): void
    {
        $fs = new FamilySearch(['accessToken' => 'test-token']);
        
        $this->assertEquals('test-token', $fs->getAccessToken());
    }
}
```

### Integration Tests

Integration tests use php-vcr to record and replay HTTP interactions:

```php
<?php

namespace FamilySearch\Tests\Integration;

use VCR\VCR;

class MyIntegrationTest extends ApiTestCase
{
    /**
     * @vcr myTest.json
     */
    public function testApiCall(): void
    {
        VCR::turnOn();
        VCR::insertCassette('myTest.json');

        $response = $this->client->get('/platform/users/current');
        
        $this->assertResponseOK($response);

        VCR::eject();
        VCR::turnOff();
    }
}
```

The first time this test runs, it makes a real API call and records the response in `tests/fixtures/myTest.json`. Subsequent runs replay the recorded response.

## Code Coverage Target

The SDK aims for **70-80% code coverage** for core functionality:

- `src/FamilySearch.php` - Target: 75%+

To view coverage:
```bash
composer test:coverage
open coverage/index.html
```

## Continuous Integration

Tests run automatically on every push and pull request via GitHub Actions.

The CI pipeline:
- Tests against PHP 8.1, 8.2, and 8.3
- Runs both unit and integration tests
- Generates code coverage reports (PHP 8.3 only)
- Uploads coverage to Codecov

See [.github/workflows/tests.yml](.github/workflows/tests.yml) for configuration.

## php-vcr (HTTP Recording)

Integration tests use [php-vcr](https://github.com/php-vcr/php-vcr) to record and replay HTTP interactions.

### How It Works

1. First test run: Makes real HTTP request, saves response to cassette file
2. Subsequent runs: Replays response from cassette file (no network call)

### Cassette Files

Located in `tests/fixtures/`, these JSON files contain:
- Request details (method, URL, headers, body)
- Response details (status, headers, body)

### Known VCR Limitations

**Header Extraction Issues**: VCR cassettes contain the full HTTP response headers (including `X-ENTITY-ID`), but when VCR replays responses, some headers may not be properly accessible to the SDK. This is a known limitation of how VCR intercepts curl_exec() calls. Tests that depend on response headers may fail when run with VCR cassettes but pass against the live API.

**Redirect Handling**: The SDK manually follows HTTP redirects to work around curl_exec() limitations. VCR's interception interferes with this redirect handling, causing redirect tests to fail during playback. The `testRedirect` test is intentionally skipped for this reason, and redirect functionality is verified through manual testing.

**Dynamic Person IDs**: The `testPendingModification` test is skipped because VCR does not reliably replay workflows involving dynamically created resources. Each live API request creates a new person with a unique ID that doesn't match pre-recorded cassette URLs, causing VCR to make unintended live requests that result in 404 errors. This is a VCR infrastructure limitation, not an SDK defect. The pending modifications functionality (X-FS-Feature-Tag header) is working correctly and can be verified through manual testing against the live API.

**Workarounds**:
- For development and CI, unit tests provide deterministic, fast validation without these issues
- Integration tests with VCR validate request/response structure and JSON parsing
- Manual testing against the live API (see below) validates full end-to-end behavior including headers, redirects, and dynamic workflows

### Re-recording Cassettes

To update cassettes with fresh API responses:

```bash
# Delete old cassettes
rm tests/fixtures/*.json

# Re-run tests to record new cassettes
composer test:integration
```

### VCR Configuration

See `tests/bootstrap.php` for VCR configuration:
- Request matching rules
- Cassette storage location
- Library hooks

## Common Issues

### Test Failures

**"Class 'FamilySearch' not found"**
- Run `composer install` to generate autoload files
- Verify `vendor/autoload.php` exists

**"VCR cassette not found"**
- Ensure cassette file exists in `tests/fixtures/`
- Check the `@vcr` annotation matches the filename

**"PHP Fatal error: Class 'PHPUnit\Framework\TestCase' not found"**
- Ensure you're using PHPUnit 10+: `composer require --dev phpunit/phpunit:^10.5`

### Code Coverage

**"No code coverage driver available"**
- Install Xdebug: `pecl install xdebug`
- Or use PCOV: `pecl install pcov`
- Enable extension in php.ini

**Coverage report is empty**
- Ensure Xdebug is enabled: `php -v` should show "with Xdebug"
- Run with: `XDEBUG_MODE=coverage composer test:coverage`

## Credentials for Integration Tests

### Default: VCR Cassettes (No Credentials Needed)

Integration tests use pre-recorded VCR cassettes by default. No credentials are required for normal testing:

```bash
composer test:integration  # Uses recorded API responses
```

### Optional: Testing Against Live API

To test against the live FamilySearch sandbox API, you need credentials obtained through the [FamilySearch Developer Program](https://www.familysearch.org/developers/).

**Important**: Credentials are NOT stored in this repository and must be provided externally.

#### Option 1: Environment Variables (Recommended)

Set environment variables before running tests:

```bash
export FAMILYSEARCH_USERNAME="your-username"
export FAMILYSEARCH_PASSWORD="your-password"
export FAMILYSEARCH_API_KEY="your-api-key"
export FAMILYSEARCH_REDIRECT_URI="http://example.com/redirect"  # optional

composer test:integration
```

#### Option 2: Local Configuration File

Copy the example file and fill in your credentials:

```bash
cp tests/Integration/SandboxCredentials.example.php tests/Integration/SandboxCredentials.php
# Edit SandboxCredentials.php with your credentials
```

**Note**: `SandboxCredentials.php` is git-ignored and will never be committed.

### CI/CD Behavior

GitHub Actions CI runs integration tests using **only** pre-recorded VCR cassettes. No live credentials are used in CI to ensure:
- Tests are fast and deterministic
- No risk of rate limiting
- No secrets management required
- Tests work even if the sandbox API is unavailable

### Re-recording Cassettes

To update cassettes with fresh API responses (requires credentials):

```bash
# Set credentials via environment variables
export FAMILYSEARCH_USERNAME="your-username"
export FAMILYSEARCH_PASSWORD="your-password"
export FAMILYSEARCH_API_KEY="your-api-key"

# Delete old cassettes
rm tests/fixtures/*.json

# Re-run tests to record new responses
composer test:integration
```

**Note**: Live API testing can be affected by rate limiting and requires valid sandbox credentials.

## Best Practices

1. **Write unit tests first** - They're fast and don't require network access
2. **Use descriptive test names** - `testGetPersonReturnsValidResponse` not `testGet`
3. **One assertion per test** - Makes failures easier to diagnose
4. **Keep cassettes up to date** - Re-record when API changes
5. **Never commit credentials** - Always use environment variables or git-ignored files. Credentials are NOT stored in this repository.
6. **Test edge cases** - Error conditions, empty responses, malformed data
7. **Use VCR cassettes in CI** - Integration tests should run on recorded responses, not live API calls

## PHP Version Testing

To test against specific PHP versions locally using Docker:

```bash
# PHP 8.1
docker run --rm -v $(pwd):/app -w /app php:8.1-cli composer test

# PHP 8.2
docker run --rm -v $(pwd):/app -w /app php:8.2-cli composer test

# PHP 8.3
docker run --rm -v $(pwd):/app -w /app php:8.3-cli composer test
```

## Contributing

When submitting pull requests:

1. Ensure all tests pass: `composer test`
2. Add tests for new functionality
3. Maintain or improve code coverage
4. Update cassettes if API interactions changed
5. Run tests against all supported PHP versions

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [php-vcr Documentation](https://github.com/php-vcr/php-vcr)
- [FamilySearch API Documentation](https://www.familysearch.org/developers/docs/api)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
