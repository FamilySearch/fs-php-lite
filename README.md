# fs-php-lite

Lite PHP SDK for the FamilySearch API

## Usage

```php

include_once('FamilySearch.php');

// Create the SDK instance
$fs = new FamilySearch([
  'environment' => 'production',
  'appKey' => 'ahfud9Adjfia',
  'redirectUri' => 'https://example.com/fs-redirect',
  
  // Tell it to automatically save and load the access token from $_SESSION. 
  // Should the be the behavior by default?
  'sessions' => true, 
  'sessionVariable' => 'FS_ACCESS_TOKEN',
  
  // Necessary for when the developer wants to store the accessToken somewhere
  // besides $_SESSION
  'accessToken' => ''
]);

// OAuth step 1: Redirect
$fs->oauthRedirect();

// OAuth step 2: Exchange the code for an access token.
//
// This will automatically retrieve the code from $_GET and exchange it for
// an access token. The access token is returned but doesn't need to be saved to
// a variable if sessions are turned on.
$fs->oauthResponse();

// Get the current user
//
// What format should the URLs be specified? Ideally developers could just copy
// and paste URLs from the docs. They're in the format /platform/users/current.
// Removing the repetive portion leaves us with `users/current` but that looks
// odd. Perhaps we can allow all formats. We can check for the `/platform/`
// prefix and add it when it's missing.
//
// We have to return a response object. We can't just return the response body.
// A developer needs to know when a 404 or 410 was returned.
$response = $fs->get('/users/current');

$response->statusCode();

$response->body();
```