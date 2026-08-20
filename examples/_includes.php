<?php

session_start();

include '../src/FamilySearch.php';

// =============================================================================
// SECURITY: Credentials from Environment Variables
// =============================================================================
// NEVER hardcode credentials in source code. Always use environment variables
// or a secrets management system. Hardcoded credentials can be:
// - Accidentally committed to version control (GitHub, GitLab, etc.)
// - Exposed in error logs or debugging output
// - Discovered by attackers who gain read access to your server
//
// To set environment variables:
// - Development: Copy .env.example to .env and configure
// - Production: Use your hosting platform's environment variable settings
//   (Heroku Config Vars, AWS Parameter Store, etc.)
// =============================================================================

// Load FamilySearch app key from environment
$appKey = getenv('FS_APP_KEY');

// Validate that app key is configured
if (empty($appKey)) {
  die('
    <h1>Configuration Error</h1>
    <p><strong>FS_APP_KEY environment variable is not set.</strong></p>
    <p>To fix this:</p>
    <ol>
      <li>Copy <code>.env.example</code> to <code>.env</code> in the examples directory</li>
      <li>Get your FamilySearch developer app key from
          <a href="https://developers.familysearch.org/" target="_blank">
          https://developers.familysearch.org/</a></li>
      <li>Set <code>FS_APP_KEY</code> in your <code>.env</code> file</li>
      <li>Make sure <code>.env</code> is in your <code>.gitignore</code> (NEVER commit credentials!)</li>
    </ol>
    <p>For production deployment, set environment variables using your hosting platform.</p>
  ');
}

// =============================================================================
// SDK Configuration
// =============================================================================

// Basic configuration (suitable for development/testing)
// For production, enable encryption (see commented example below)
$fs = new FamilySearch([
  'environment' => 'integration',
  'appKey' => $appKey,  // From environment variable (secure)
  'redirectUri' => calculateBaseUrl() . '/examples/oauthResponse.php',
]);

// =============================================================================
// PRODUCTION CONFIGURATION (with encryption enabled)
// =============================================================================
// Uncomment and configure for production deployment:
//
// $encryptionKey = getenv('FS_ENCRYPTION_KEY');
//
// // Validate encryption key is configured
// if (empty($encryptionKey)) {
//   die('FS_ENCRYPTION_KEY environment variable is required for encrypted sessions');
// }
//
// $fs = new FamilySearch([
//   'environment' => 'production',  // Use 'production' environment
//   'appKey' => $appKey,
//   'redirectUri' => calculateBaseUrl() . '/examples/oauthResponse.php',
//
//   // Enable AES-256-GCM encryption for session tokens (RECOMMENDED for production)
//   'sessionEncryption' => true,
//   'sessionEncryptionKey' => $encryptionKey,  // From environment variable (secure)
// ]);
//
// Why enable encryption?
// - Protects access tokens from filesystem access (backups, logs, disk forensics)
// - Required for compliance (PCI DSS, GDPR, etc.)
// - Defense-in-depth security strategy
//
// Generate encryption key:
//   php -r "echo base64_encode(random_bytes(32));"
//
// Store in .env file:
//   FS_ENCRYPTION_KEY=WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo=
//
// See SECURITY.md for comprehensive security guidance.
// =============================================================================

/**
 * Pretty print a PHP variable
 *
 * @param mixed $var
 */
function prettyPrint($var): void
{
  echo '<pre>', print_r($var, true), '</pre>';
}

/**
 * Calculate the apps protocol and domain. This allows us to run the app both
 * locally and in Heroku without having to modify the redirect URI.
 *
 * @return string
 */
function calculateBaseUrl(): string
{
  return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?: $_SERVER['REQUEST_SCHEME']) . '://' . $_SERVER['HTTP_HOST'];
}