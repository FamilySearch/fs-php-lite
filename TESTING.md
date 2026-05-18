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

## Testing Against Live API

To test against live FamilySearch API (not recommended for regular testing):

1. Disable VCR in integration test:
```php
public function testLiveApi(): void
{
    // VCR disabled - makes real HTTP request
    $response = $this->client->get('/platform/users/current');
    $this->assertResponseOK($response);
}
```

2. Ensure valid credentials in `SandboxCredentials.php`

**Note**: Live API testing requires valid sandbox credentials and can be affected by rate limiting.

## Best Practices

1. **Write unit tests first** - They're fast and don't require network access
2. **Use descriptive test names** - `testGetPersonReturnsValidResponse` not `testGet`
3. **One assertion per test** - Makes failures easier to diagnose
4. **Keep cassettes up to date** - Re-record when API changes
5. **Don't commit credentials** - Use environment variables for sensitive data
6. **Test edge cases** - Error conditions, empty responses, malformed data

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
