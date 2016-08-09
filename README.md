# fs-php-lite

Lite PHP SDK for the [FamilySearch API](https://familysearch.org/developers/).

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
  
  // How many times should a throttled response be retried? Defaults to 5
  'maxThrottledRetries' => 5
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

// All response objects have the following properties
$response->statusCode;     // Integer
$response->statusText;     // String
$response->headers;        // Array
$response->finalUrl;       // String
$response->body;           // String
$response->requestHeaders; // Array
$response->requestBody;    // String
$response->redirected;     // Boolean; defaults to false
$response->throttled;      // Boolean; defaults to false
$response->curl;           // A reference to the curl resource for the request

// If the response included JSON in the body then it will be parsed into an
// associative array and be available via the `data` property.
$response->data; 

// If a request is forwarded then the response will contain the original URL
$response->originalUrl;

// If a request is throttled then the response will tell how many times it was
// throttled until it finally succeeded.
$response->retries;
```