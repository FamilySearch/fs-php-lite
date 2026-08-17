<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

/**
 * Example credentials template for integration tests
 *
 * Copy this file to IntegrationCredentials.php and fill in your credentials,
 * OR set environment variables (recommended):
 *   - FAMILYSEARCH_USERNAME
 *   - FAMILYSEARCH_PASSWORD
 *   - FAMILYSEARCH_API_KEY
 *   - FAMILYSEARCH_REDIRECT_URI (optional)
 *
 * Note: Credentials are NOT stored in this repository.
 * Request integration environment access through the FamilySearch developer program.
 */
class IntegrationCredentials
{
    const USERNAME = ''; // Set via FAMILYSEARCH_USERNAME env var
    const PASSWORD = ''; // Set via FAMILYSEARCH_PASSWORD env var
    const API_KEY = ''; // Set via FAMILYSEARCH_API_KEY env var
    const REDIRECT_URI = 'http://example.com/redirect'; // Set via FAMILYSEARCH_REDIRECT_URI env var
}
