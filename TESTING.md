# Testing Guide for fs-php-lite

This guide explains how to run and write tests for the FamilySearch PHP Lite SDK.

## Testing Strategy

The SDK uses a multi-layered testing approach:

1. **Unit Tests** - Test SDK methods in isolation without making HTTP requests
2. **Integration Tests** - Test SDK against live FamilySearch sandbox API
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
├── bootstrap.php           # Test bootstrapping
├── Unit/                   # Unit tests (no HTTP requests)
│   ├── FamilySearchConfigTest.php
│   └── FamilySearchHttpMethodsTest.php
├── Integration/            # Integration tests (live API)
│   ├── ApiTestCase.php
│   ├── SandboxCredentials.php
│   └── FamilySearchIntegrationTest.php
└── fixtures/               # Test data
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

Integration tests make real HTTP calls to the FamilySearch sandbox API:

```php
<?php

namespace FamilySearch\Tests\Integration;

class MyIntegrationTest extends ApiTestCase
{
    public function testApiCall(): void
    {
        $response = $this->login();
        $this->assertResponseOK($response);

        $response = $this->client->get('/platform/users/current');
        $this->assertResponseOK($response);
    }
}
```

Integration tests require valid FamilySearch sandbox credentials (see credentials section below).

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

## Integration Tests Against Live API

Integration tests make real HTTP requests to the FamilySearch sandbox API.

### How It Works

Tests authenticate with the sandbox API using OAuth2 password flow and perform real operations:
- Create/read/update/delete persons
- Test redirects
- Verify headers and response structure
- Test authentication flows

### Benefits

- Tests always reflect current API behavior
- No recording/replay complexity
- Headers, redirects, and dynamic IDs work correctly
- Simpler test code and maintenance

### Requirements

- Valid FamilySearch sandbox credentials
- Network connectivity
- Sandbox API availability

## Common Issues

### Test Failures

**"Class 'FamilySearch' not found"**
- Run `composer install` to generate autoload files
- Verify `vendor/autoload.php` exists

**"PHP Fatal error: Class 'PHPUnit\Framework\TestCase' not found"**
- Ensure you're using PHPUnit 9+: `composer require --dev phpunit/phpunit:^9.5`

**Integration tests fail with authentication errors**
- Ensure credentials are set via environment variables or `SandboxCredentials.php`
- Verify credentials are valid for the FamilySearch sandbox environment

### Code Coverage

**"No code coverage driver available"**
- Install Xdebug: `pecl install xdebug`
- Or use PCOV: `pecl install pcov`
- Enable extension in php.ini

**Coverage report is empty**
- Ensure Xdebug is enabled: `php -v` should show "with Xdebug"
- Run with: `XDEBUG_MODE=coverage composer test:coverage`

## Credentials for Integration Tests

Integration tests require credentials from the [FamilySearch Developer Program](https://www.familysearch.org/developers/).

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

GitHub Actions CI requires credentials to be set as repository secrets:
- `FAMILYSEARCH_USERNAME`
- `FAMILYSEARCH_PASSWORD`
- `FAMILYSEARCH_API_KEY`

Tests run against the live sandbox API in CI.

## Best Practices

1. **Write unit tests first** - They're fast and don't require network access
2. **Use descriptive test names** - `testGetPersonReturnsValidResponse` not `testGet`
3. **One assertion per test** - Makes failures easier to diagnose
4. **Never commit credentials** - Always use environment variables or git-ignored files. Credentials are NOT stored in this repository.
5. **Test edge cases** - Error conditions, empty responses, malformed data
6. **Integration tests hit live API** - Ensure credentials are set before running

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
4. Run tests against all supported PHP versions

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [FamilySearch API Documentation](https://www.familysearch.org/developers/docs/api)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
