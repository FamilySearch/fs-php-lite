# FamilySearch PHP Lite SDK Examples

This directory contains working examples demonstrating how to use the fs-php-lite SDK with the FamilySearch API.

## Prerequisites

- PHP 8.1 or higher
- Composer
- FamilySearch developer account and API key ([register here](https://www.familysearch.org/developers/))

## Setup

1. Install dependencies:
```bash
composer install
```

2. Configure your API credentials in `_includes.php` or set environment variables:
```php
$config = [
    'environment' => 'beta',  // or 'production'
    'appKey' => 'YOUR_APP_KEY',
    'redirectUri' => 'http://localhost:8080/examples/oauthResponse.php'
];
```

3. Start the built-in PHP server:
```bash
php -S localhost:8080
```

4. Navigate to `http://localhost:8080/examples/` in your browser

## Available Examples

### Authentication
- **oauthRedirect.php** - Initiates OAuth authentication flow
- **oauthResponse.php** - Handles OAuth callback and exchanges code for access token
- **isAuthenticated.php** - Checks if the current session is authenticated

### User Operations
- **currentUser.php** - Retrieves information about the authenticated user
- **currentPerson.php** - Gets the current user's person record

### Person Operations
- **readPerson.php** - Reads a person record by ID
- **createPerson.php** - Creates a new person in the tree
- **readPersonDuplicates.php** - Finds possible duplicate person records
- **readPersonPortrait.php** - Retrieves a person's portrait image
- **readPersonRecordHints.php** - Gets record hints for a person

### Source Operations
- **createAttachSource.php** - Creates and attaches a source to a person

### Advanced
- **readUserMemories.php** - Retrieves memories uploaded by a user
- **triggerThrottling.php** - Demonstrates throttling behavior and retry logic

## Example Workflow

1. Start with **oauthRedirect.php** to authenticate
2. After OAuth callback, you'll be redirected to **oauthResponse.php**
3. Once authenticated, try **currentUser.php** to see your profile
4. Explore person operations like **readPerson.php** or **createPerson.php**

## Running Examples

### Using Built-in PHP Server
```bash
cd /path/to/fs-php-lite
php -S localhost:8080
```
Then open `http://localhost:8080/examples/` in your browser.

### Individual Example Execution
Some examples can be run directly from the command line:
```bash
php examples/readPerson.php
```

## Architecture

- **_includes.php** - Common configuration and SDK initialization
- **_header.php** - HTML header for web-based examples
- **_footer.php** - HTML footer for web-based examples
- **_sidebar.php** - Navigation sidebar
- **_server.php** - Development server bootstrap

## Notes

- Examples use the FamilySearch Integration environment by default
- Access tokens are stored in PHP sessions
- For production use, implement proper token storage and security
- See the main [README.md](../README.md) for full SDK documentation

## Troubleshooting

**"Access token not found"**
- Run the OAuth flow first via `oauthRedirect.php`

**"Invalid API key"**
- Verify your app key is correctly configured in `_includes.php`

**"Person not found"**
- Use valid person IDs from your FamilySearch tree
- Try `currentPerson.php` to get a valid person ID

## Additional Resources

- [FamilySearch API Documentation](https://developers.familysearch.org/main/docs/getting-started)
- [OAuth 2.0 Guide](https://developers.familysearch.org/main/docs/authentication)
- [FamilySearch GEDCOM X](https://github.com/FamilySearch/gedcomx)
