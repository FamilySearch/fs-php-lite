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
$response = $fs->get('/platform/users/current');

// Response objects have the following properties:
$response->statusCode;
$response->statusText;
$response->headers;
$response->finalUrl;
$response->body;

// If the response included JSON in the body then it will be parsed into an
// associative array and be available via the `data` property.
$response->data; 
```