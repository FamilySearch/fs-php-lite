# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- PHP 7.4 minimum version support with modern type hints
- Support for PHP 8.0, 8.1, 8.2, 8.3, and 8.4
- Return type declarations to helper functions in examples
- Comprehensive TESTING.md documentation for application testing
- GitHub Actions workflow for automated testing across PHP 7.4-8.4
- Code coverage reporting (76.33% coverage)

### Changed
- Replaced all references to "sandbox" environment with "integration" terminology
- Updated composer.json to require PHP >=7.4 (previously >=8.0)
- Modernized build system with GitHub Actions (replaced Travis CI)
- Updated FamilySearch API endpoint URLs to current subdomains
- Enhanced test suite with 102 tests (74 unit, 28 integration)
- Removed VCR (Video Cassette Recorder) dependencies from testing
- Improved README.md with clearer security warnings and modern PHP examples
- Fixed testPendingModification() to target real endpoints

### Fixed
- PHP 8.4 compatibility in test suite
- Array syntax error in README.md code example (changed `{}` to `[]`)

### Security
- Clarified that environment variables must be used for credentials (never hardcode)
- Enhanced security documentation in examples/_includes.php

## [1.3.0] - 2024 (Master Branch - Not Yet Released)

### Added
- **Optional AES-256-GCM session token encryption** for production deployments
  - New `sessionEncryption` configuration option (defaults to `false` for backward compatibility)
  - New `sessionEncryptionKey` configuration parameter for encryption key
  - Automatic key normalization supporting base64, hex, and raw binary formats
  - Backward-compatible migration from plaintext to encrypted tokens
  - Fail-secure behavior (encryption failures never fall back to plaintext)
- Comprehensive SECURITY.md documentation (750+ lines)
  - Detailed threat model and security considerations
  - Key generation and storage best practices
  - Server configuration guidelines
  - Production deployment checklist
  - Key rotation procedures
  - Encryption limitations and use cases
- New security test suite for encryption functionality
- Encryption key validation and error handling

### Changed
- Updated testing strategy with comprehensive integration and unit tests
- Modernized GitHub Actions CI/CD pipeline (replaced Travis CI)
- Updated documentation links to current FamilySearch developer portal
- Improved test organization (separated unit and integration tests)
- Enhanced code coverage reporting with Codecov integration
- Updated phpunit to version ^9.5

### Fixed
- Response parsing to handle multiple header sections (e.g., 100 Continue followed by 201 Created)
- Removed accidentally committed credentials (security fix)

### Security
- **Major security enhancement:** Session tokens can now be encrypted at rest
- Protects against filesystem access, backup exposure, and disk forensics
- Uses authenticated encryption (AES-256-GCM) to detect tampering

## [1.3.3] - 2017-03-31

### Fixed
- PHP 7 support in composer.json configuration

## [1.3.2] - 2017-03-31

### Changed
- Updated API subdomain endpoints to new FamilySearch infrastructure

## [1.3.1] - 2016-11-29

### Added
- Apache 2.0 license to composer.json metadata

## [1.3.0] - 2016-11-29

### Added
- Optional integration with gedcomx-php library for object serialization
- `objects` configuration option to enable gedcomx-php serialization/deserialization
- Support for gedcomx-php objects in request bodies
- Automatic deserialization of responses into Atom Feed or FamilySearchPlatform objects

### Changed
- Updated documentation with gedcomx-php usage examples

## [1.2.0] - 2016-09-21

### Added
- Custom User-Agent support via `userAgent` configuration option
- SDK version number appended to default User-Agent string
- Pending modifications support via `pendingModifications` configuration option

### Changed
- Default User-Agent format: `FS-PHP-Lite/{VERSION} curl/{VERSION} PHP/{VERSION}`

## [1.1.0] - 2016-09-09

### Changed
- **BREAKING:** Replaced "sandbox" environment references with "integration"
- Updated environment configuration to use 'integration' instead of 'sandbox'
- Updated documentation and examples to reflect integration environment

## [1.0.0] - 2016-07-29

Initial release of FamilySearch PHP Lite SDK.

### Added
- Core SDK functionality for FamilySearch API integration
- OAuth 2.0 authentication flow (redirect and response handling)
- HTTP methods: GET, POST, HEAD, DELETE
- Session-based access token storage
- Automatic JSON parsing of API responses
- Response object with comprehensive metadata
- Throttling handling with automatic retry logic
- Query parameter support
- Custom header support
- Redirect following
- Request/response debugging capabilities
- Working example application
- PHPUnit test suite with Travis CI integration
- Basic documentation in README.md

### Security
- Session-based token storage (tokens stored in `$_SESSION`)
- Support for manual token management via `accessToken` option

## Version History Summary

- **[Unreleased]** - PHP 7.4+ support, terminology updates, enhanced testing
- **[1.3.0]** - Session encryption, comprehensive security documentation (master branch)
- **1.3.3** - PHP 7 composer fix
- **1.3.2** - API subdomain updates
- **1.3.1** - License metadata
- **1.3.0** - gedcomx-php integration
- **1.2.0** - Custom user agent and pending modifications
- **1.1.0** - Integration environment terminology
- **1.0.0** - Initial release

---

## Notes

### Versioning Conflict

There is a version numbering conflict in the repository:
- Git tag `1.3.0` was used for the gedcomx-php integration (2016)
- Code constant `VERSION = '1.3.0'` was updated for the encryption feature (2024)

The master branch changes (encryption feature) should be released as **2.0.0** or **1.4.0** to avoid confusion.

### Migration Guide

#### From 1.3.x to 2.0.0 (Encryption Update)

**No breaking changes** - The encryption feature is opt-in:

```php
// Existing code continues to work unchanged
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production'
]);

// Enable encryption (recommended for production)
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production',
    'sessionEncryption' => true,
    'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY']
]);
```

Generate encryption key:
```bash
php -r "echo base64_encode(random_bytes(32));"
```

See [SECURITY.md](SECURITY.md) for comprehensive encryption setup guide.

#### From 1.0.x/1.1.x to 1.2.0+

- Update environment references from `'sandbox'` to `'integration'`
- Update API endpoint references in documentation

---

[Unreleased]: https://github.com/FamilySearch/fs-php-lite/compare/1.3.3...HEAD
[1.3.0]: https://github.com/FamilySearch/fs-php-lite/compare/1.3.3...master
[1.3.3]: https://github.com/FamilySearch/fs-php-lite/compare/1.3.2...1.3.3
[1.3.2]: https://github.com/FamilySearch/fs-php-lite/compare/1.3.1...1.3.2
[1.3.1]: https://github.com/FamilySearch/fs-php-lite/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/FamilySearch/fs-php-lite/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/FamilySearch/fs-php-lite/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/FamilySearch/fs-php-lite/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/FamilySearch/fs-php-lite/releases/tag/1.0.0
