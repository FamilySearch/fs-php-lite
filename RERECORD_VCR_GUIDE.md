# VCR Cassette Re-recording Guide

## Overview

This guide provides step-by-step instructions for re-recording VCR cassettes with fresh API responses from the FamilySearch integration environment.

## Prerequisites

### 1. FamilySearch Developer Account
You need credentials from the FamilySearch Developer Program:
- Username and password for sandbox environment
- API key (app key) from registered application

**How to get credentials:**
1. Visit https://developers.familysearch.org/
2. Create an account or sign in
3. Register a new application
4. Request sandbox access
5. If approved, note your API key and sandbox credentials

### 2. Required Environment Variables

Set these before re-recording:

```bash
export FAMILYSEARCH_USERNAME="your-sandbox-username"
export FAMILYSEARCH_PASSWORD="your-sandbox-password"
export FAMILYSEARCH_API_KEY="your-app-key"
export FAMILYSEARCH_REDIRECT_URI="http://example.com/redirect"
```

**Verify credentials are set:**
```bash
env | grep FAMILYSEARCH
```

### 3. Dependencies Installed

```bash
composer install
```

## Current Cassette Status

### Active Cassettes (tests/fixtures/)
- ✅ `testAuthenticate.json` - Recent (June 2026)
- ✅ `testPost.json` - Recent (June 2026)
- ✅ `testGet.json` - Recent (June 2026)
- ✅ `testHead.json` - Recent (June 2026)
- ✅ `testDelete.json` - Recent (June 2026)
- ✅ `testUserAgent.json` - Recent (June 2026)
- ⚠️ `testRedirect.json` - Older (May 2026)
- ⚠️ `testPendingModification.json` - Older (May 2026)
- ⚠️ `person.json` - Older (May 2026)

### Unused Cassettes (tests/fixtures/gedcomx/)
These cassettes are **NOT used** by any tests:
- `testAuthenticate.json` - From 2017
- `testPost.json` - From 2017
- `testGet.json` - From 2017
- `testHead.json` - From 2017
- `testDelete.json` - From 2017
- `testRedirect.json` - From 2017

**Recommendation:** Delete gedcomx/ directory (not used)

## Re-recording Methods

### Method 1: Re-record All Cassettes (Recommended)

**Best for:** Fresh start, consistency across all cassettes

```bash
# 1. Set credentials
export FAMILYSEARCH_USERNAME="your-username"
export FAMILYSEARCH_PASSWORD="your-password"
export FAMILYSEARCH_API_KEY="your-api-key"

# 2. Backup existing cassettes (optional)
mkdir -p tests/fixtures_backup
cp tests/fixtures/*.json tests/fixtures_backup/

# 3. Delete old cassettes
rm tests/fixtures/*.json

# 4. Re-run integration tests (will record fresh cassettes)
composer test:integration

# 5. Verify all tests pass
composer test:integration

# 6. Check cassettes were created
ls -lh tests/fixtures/*.json

# 7. Inspect a cassette to verify content
head -50 tests/fixtures/testAuthenticate.json
```

**Expected output:**
```
PHPUnit 9.6.35 by Sebastian Bergmann and contributors.

.....SS.                                                            8 / 8 (100%)

Time: 00:XX.XXX, Memory: 6.00 MB

OK, but incomplete, skipped, or risky tests!
Tests: 8, Assertions: 36, Skipped: 2.
```

**New cassettes created:**
- ✅ testAuthenticate.json
- ✅ testPost.json
- ✅ testGet.json
- ✅ testHead.json
- ✅ testDelete.json
- ⏭️ testRedirect.json (test skipped, no cassette created)
- ⏭️ testPendingModification.json (test skipped, no cassette created)
- ✅ testUserAgent.json

### Method 2: Re-record Specific Cassettes

**Best for:** Updating individual cassettes without changing others

```bash
# 1. Set credentials (as above)

# 2. Delete specific cassette
rm tests/fixtures/testGet.json

# 3. Run specific test to re-record
vendor/bin/phpunit --filter testGet tests/Integration/FamilySearchIntegrationTest.php

# 4. Verify test passes
vendor/bin/phpunit --filter testGet tests/Integration/FamilySearchIntegrationTest.php
```

**Available test filters:**
- `testAuthenticate` - OAuth authentication
- `testPost` - Create person
- `testGet` - Read person
- `testHead` - HEAD request
- `testDelete` - Delete person
- `testUserAgent` - User agent test

### Method 3: Re-record Stale Cassettes Only

**Best for:** Updating only older cassettes

```bash
# 1. Set credentials

# 2. Delete only stale cassettes
rm tests/fixtures/testRedirect.json
rm tests/fixtures/testPendingModification.json
rm tests/fixtures/person.json

# 3. Re-run integration tests
composer test:integration

# Note: testRedirect and testPendingModification are skipped,
# so those cassettes won't be re-created
```

## Handling Skipped Tests

### testRedirect (Always Skipped)
**Reason:** VCR doesn't properly replay redirect responses

**Re-recording:** Will NOT create cassette (test is marked as skipped)

**Manual testing required:**
```bash
# Test redirects against live API (without VCR)
# Edit test to temporarily remove markTestSkipped() call
# Run against live API
# Verify redirect behavior works
# Restore markTestSkipped() call
```

### testPendingModification (Always Skipped)
**Reason:** Dynamic person IDs don't match pre-recorded cassette URLs

**Re-recording:** Will NOT create cassette (test is marked as skipped)

**Manual testing required:**
```bash
# Similar to testRedirect - test manually without VCR
```

## Verifying Re-recorded Cassettes

### 1. Check Cassette Content

**Verify User-Agent is current:**
```bash
jq -r '.[0].request.headers["User-Agent"]' tests/fixtures/testAuthenticate.json
```

**Expected:**
```
FS-PHP-Lite/1.3.0 curl/8.x PHP/8.x
```

**NOT (old):**
```
FS-PHP-Lite/1.2.0 curl/7.35.0 PHP/5.5.9-1ubuntu4.17
```

### 2. Check Timestamps

**Verify recent modification dates:**
```bash
ls -lh tests/fixtures/*.json
```

All cassettes should show today's date.

### 3. Run Tests Multiple Times

**First run (using fresh cassettes):**
```bash
composer test:integration
```

**Second run (should be faster, using cassettes):**
```bash
composer test:integration
```

Second run should be very fast (no network calls) and produce identical results.

### 4. Verify Response Structure

**Check a cassette has expected structure:**
```bash
jq '.[0] | keys' tests/fixtures/testAuthenticate.json
```

**Expected:**
```json
[
  "request",
  "response"
]
```

### 5. Check Request/Response Completeness

**Example for testGet:**
```bash
jq 'length' tests/fixtures/testGet.json
# Should be 3 (OAuth + Create Person + Get Person)

jq '.[].request.url' tests/fixtures/testGet.json
# Should show 3 URLs
```

### 6. Verify Integration Tests Pass

```bash
composer test:integration

# All tests should pass (2 skipped as expected)
# Tests: 8, Assertions: 36, Skipped: 2
```

### 7. Verify Unit Tests Still Pass

```bash
composer test:unit

# All unit tests should pass
# Tests: 24, Assertions: 37
```

### 8. Verify Coverage Maintained

```bash
composer test:coverage

# Coverage should be maintained: 77.72%+
```

## Troubleshooting

### Issue: "No credentials found"

**Problem:** Integration tests can't authenticate

**Solution:**
```bash
# Verify environment variables are set
env | grep FAMILYSEARCH

# Or create credentials file
cp tests/Integration/SandboxCredentials.example.php \
   tests/Integration/SandboxCredentials.php
# Edit with your credentials
```

### Issue: "404 Not Found" when re-recording

**Problem:** API endpoints have changed or person doesn't exist

**Solution:**
- Delete ALL cassettes and start fresh
- Person IDs in old cassettes don't exist in sandbox anymore
- Re-recording creates fresh persons

### Issue: "429 Too Many Requests"

**Problem:** Rate limiting from FamilySearch API

**Solution:**
```bash
# Wait a few minutes
sleep 300

# Re-run
composer test:integration

# Or re-record one test at a time with delays
vendor/bin/phpunit --filter testAuthenticate tests/Integration/
sleep 60
vendor/bin/phpunit --filter testPost tests/Integration/
sleep 60
# etc.
```

### Issue: Cassette not created after test

**Problem:** Test is skipped or failed before recording

**Solution:**
```bash
# Check if test is marked as skipped
grep -A5 "testRedirect" tests/Integration/FamilySearchIntegrationTest.php

# Look for markTestSkipped() call
```

### Issue: "VCR is turned off"

**Problem:** VCR configuration issue

**Solution:**
- Check `tests/bootstrap.php` has VCR configuration
- Ensure `php-vcr/php-vcr` is installed: `composer show php-vcr/php-vcr`

### Issue: Tests pass with cassettes but fail with live API

**Problem:** API behavior has changed

**Solution:**
1. Review API response differences
2. Update test expectations if needed
3. Update SDK if API contract changed
4. Document breaking changes

### Issue: Sensitive data in cassettes

**Problem:** Access tokens or credentials in cassette files

**Solution:**
```bash
# Check for sensitive data
grep -i "access_token\|password" tests/fixtures/*.json

# Access tokens are OK (temporary, sandbox)
# Passwords should NOT appear (already URL-encoded in body)

# If needed, sanitize:
sed -i.bak 's/USYS[A-Z0-9_]*/REDACTED_TOKEN/g' tests/fixtures/*.json
```

## Cassette Maintenance Schedule

### When to Re-record

**Required:**
- When API contract changes
- When tests fail unexpectedly with cassettes
- When cassettes contain ancient data (>6 months old)

**Recommended:**
- Quarterly (every 3 months)
- Before major releases
- After SDK updates that change API usage

**Optional:**
- Weekly (for active development)
- When adding new tests

### Quick Check

```bash
# Check cassette age
ls -lht tests/fixtures/*.json | head -5

# If oldest is >3 months, consider re-recording
```

## Clean-up Tasks

### Remove Unused Fixtures

```bash
# gedcomx/ fixtures are NOT used by any tests
rm -rf tests/fixtures/gedcomx/

# Verify tests still pass
composer test:integration
```

### Verify No Duplicate Cassettes

```bash
# Check for duplicates
find tests/fixtures -name "*.json" | sort

# Should only see each test name once (no duplicates)
```

## Security Best Practices

### 1. Never Commit Credentials

```bash
# Verify .gitignore has:
cat .gitignore | grep -i credential
# Should show: tests/Integration/SandboxCredentials.php
```

### 2. Use Environment Variables in CI

```yaml
# .github/workflows/tests.yml should NOT have credentials
# CI runs with cassettes only (no live API calls)
```

### 3. Sanitize Tokens (Optional)

```bash
# If publishing cassettes publicly, consider sanitizing
# Replace real tokens with placeholders
# Note: Cassettes won't work for re-recording after sanitization
```

### 4. Use Sandbox Environment Only

- Never use production credentials
- Never record cassettes from production API
- Sandbox data is safe to commit

## Post-Recording Checklist

After re-recording cassettes:

- [ ] All integration tests pass
- [ ] Cassettes have current timestamps
- [ ] User-Agent strings are current (PHP 8.x, curl 8.x)
- [ ] No sensitive data in cassettes (or sanitized)
- [ ] gedcomx/ directory removed (unused)
- [ ] Tests pass WITHOUT credentials (using cassettes only)
- [ ] Coverage maintained at 77.72%+
- [ ] Documentation updated if API changes detected
- [ ] Cassettes committed to git

## Commit Message Template

```
chore: re-record VCR cassettes with current API responses

- Updated all cassettes to reflect current FamilySearch API
- User-Agent now shows PHP 8.x and curl 8.x
- Removed unused gedcomx/ fixtures
- All tests passing (8 tests, 2 skipped)
- Coverage maintained at 77.72%
```

## Quick Reference Commands

```bash
# Check cassette age
ls -lht tests/fixtures/*.json

# Re-record all
rm tests/fixtures/*.json && composer test:integration

# Re-record one
rm tests/fixtures/testGet.json && vendor/bin/phpunit --filter testGet tests/Integration/

# Verify cassettes
jq '.[0].request.headers["User-Agent"]' tests/fixtures/*.json

# Test without credentials
unset FAMILYSEARCH_USERNAME FAMILYSEARCH_PASSWORD FAMILYSEARCH_API_KEY
composer test:integration

# Check for sensitive data
grep -i "password\|secret" tests/fixtures/*.json
```

## Additional Resources

- **FamilySearch API:** https://developers.familysearch.org/
- **Sandbox Guide:** https://developers.familysearch.org/main/reference/api-reference-guide
- **Test Documentation:** See `TESTING.md` in repository

---

**Guide Version:** 1.0  
**Last Updated:** July 26, 2026  
**VCR Version:** 1.11.2  
**PHPUnit Version:** 9.6.35
