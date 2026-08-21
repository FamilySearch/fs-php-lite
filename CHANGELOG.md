# Changelog

All notable changes to this project will be documented in this file.

## [1.5.0] - 2026-08-21

### Added
- **Client-side token expiration tracking** for FamilySearch OAuth tokens
  - New `isTokenExpired()` method returns true/false for token expiration status
  - New `getTokenExpirationTime()` method returns Unix timestamp when token expires
  - New `setAccessToken($token, $expiresIn)` method with optional expiration parameter
  - Enhanced `getAccessToken($detailed)` with optional detailed mode returning array with:
    - `token`: OAuth access token string
    - `created`: Unix timestamp when token was created
    - `last_activity`: Unix timestamp of last successful API call
    - `expires_at`: Unix timestamp when token will expire
    - `is_expired`: Boolean indicating if token is expired or expiring soon
  - New `expirationWarningThreshold` configuration option (default: 300 seconds / 5 minutes)
  - Automatic activity tracking - updates last activity timestamp on successful API calls
  - Session format now stores token with metadata (creation time, last activity)
  - Tracks two expiration conditions:
    - Absolute expiration: 24 hours from token creation
    - Inactivity expiration: 60 minutes from last API call
- **Authentication failure callback system** for handling 401 responses
  - New `onAuthenticationFailure` configuration option accepts callable/callback
  - Callback receives response object and failure reason (`'expired'` or `'invalid'`)
  - Allows automatic re-authentication on token expiration
  - Supports password grant re-authentication in callback
  - Supports redirect to login page for authorization code flow
  - Callback can use `setAccessToken()` or `oauthPassword()` to obtain new token
  - Backward compatible - callback is optional, existing error handling preserved
- **Automatic request replay after re-authentication**
  - New `replayFailedRequestsAfterAuth` configuration option (defaults to `true`)
  - Automatically retries failed requests after successful re-authentication via callback
  - Single retry only to prevent infinite loops
  - Response includes `replayed` flag and `originalResponse` when request was retried
  - Can be disabled for manual retry handling
- Comprehensive documentation in `docs/TOKEN_EXPIRATION.md`
  - Three approaches to token expiration handling
  - Proactive expiration checking examples
  - Authentication failure callback patterns
  - Enhanced token info retrieval examples
- Complete test coverage for token expiration scenarios
  - `tests/Unit/FamilySearchTokenExpirationTest.php` - 28 unit tests
  - `tests/Unit/FamilySearchAuthCallbackTest.php` - 18 unit tests for callback system
  - `tests/Unit/FamilySearchRequestReplayTest.php` - 17 unit tests for replay functionality
  - `tests/Integration/AuthenticationCallbackTest.php` - Integration tests
  - `tests/Integration/RequestReplayTest.php` - Integration tests
  - `tests/Integration/TokenExpirationComprehensiveTest.php` - End-to-end tests

### Changed
- Session storage format enhanced with token metadata (backward compatible)
- Existing sessions auto-migrate to new format with timestamp initialization
- OAuth response handler now initializes token timestamps
- Successful API calls (status code < 400, not 401) automatically update last activity
- Internal token storage includes creation time and last activity tracking
- Internal request handling enhanced to invoke callback before returning 401 responses
- Callback exceptions are caught and logged to prevent SDK instability
- Enhanced internal request handling to support retry logic

### Fixed
- Token expiration now properly detected before API calls fail
- 401 responses can be handled gracefully via callback instead of throwing exceptions

### Security
- Client-side expiration tracking prevents unnecessary 401 errors
- Proactive re-authentication improves security posture
- No tokens transmitted for validation - all expiration logic is client-side
- Transparent recovery from token expiration improves user experience
- Prevents exposure of 401 errors to end users when tokens can be refreshed

## [1.4.0] - 2026-08-18

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

## [1.3.0] - 2026-08-07

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

- **1.5.0** - Token expiration tracking, authentication callbacks, and automatic request replay
- **1.4.0** - PHP 7.4+ support, terminology updates, enhanced testing
- **1.3.0** - Session encryption, comprehensive security documentation
- **1.3.3** - PHP 7 composer fix
- **1.3.2** - API subdomain updates
- **1.3.1** - License metadata
- **1.3.0** - gedcomx-php integration
- **1.2.0** - Custom user agent and pending modifications
- **1.1.0** - Integration environment terminology
- **1.0.0** - Initial release

---

## Notes

### Migration Guide

#### From 1.3.x to 1.4.0 (PHP Version and Testing Updates)

**No breaking changes** - Version 1.4.0 adds support for PHP 7.4-8.4:

```php
// Existing code continues to work unchanged
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'environment' => 'production'  // Use 'integration' for testing
]);
```

**Changes:**
- PHP 7.4+ is now supported (previously required PHP 8.0+)
- All "sandbox" references replaced with "integration" terminology
- Enhanced test suite and documentation

#### From 1.3.x to 1.5.0 (Token Expiration Features)

**New Configuration Options:**
- `expirationWarningThreshold` - Seconds before expiration to warn (default: 300)
- `onAuthenticationFailure` - Callback for handling 401 responses (optional)
- `replayFailedRequestsAfterAuth` - Auto-retry after re-auth (default: true)

See [docs/TOKEN_EXPIRATION.md](docs/TOKEN_EXPIRATION.md) for comprehensive usage guide.

#### From 1.0.x/1.1.x to 1.2.0+

- Update environment references from `'sandbox'` to `'integration'`
- Update API endpoint references in documentation
