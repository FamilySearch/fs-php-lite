# Testing Your Application with fs-php-lite

This guide helps you test your own applications that use the FamilySearch PHP Lite SDK.

## Unit Testing Your Code

When writing unit tests for your application code that uses the SDK, you can mock the `FamilySearch` class to avoid making real HTTP requests:

```php
use PHPUnit\Framework\TestCase;

class MyAppTest extends TestCase
{
    public function testMyFeature(): void
    {
        // Mock the SDK
        $mockFS = $this->createMock(FamilySearch::class);
        
        // Set up expected behavior
        $mockResponse = (object)[
            'statusCode' => 200,
            'data' => ['persons' => [/* ... */]]
        ];
        $mockFS->method('get')->willReturn($mockResponse);
        
        // Test your code that uses the SDK
        $myService = new MyService($mockFS);
        $result = $myService->doSomething();
        
        $this->assertNotEmpty($result);
    }
}
```

## Integration Testing Against Integration Environment

To test your application against the live FamilySearch Integration environment, initialize the SDK with an access token obtained through the standard OAuth2 authorization flow:

```php
class MyIntegrationTest extends TestCase
{
    private FamilySearch $fs;
    
    protected function setUp(): void
    {
        $this->fs = new FamilySearch([
            'environment' => 'integration',
            'appKey' => $_ENV['FAMILYSEARCH_API_KEY'],
            'redirectUri' => $_ENV['FAMILYSEARCH_REDIRECT_URI'],
            // Use a pre-obtained access token for testing
            'accessToken' => $_ENV['FAMILYSEARCH_ACCESS_TOKEN']
        ]);
    }
    
    public function testGetCurrentUser(): void
    {
        $response = $this->fs->get('/platform/users/current');
        
        $this->assertEquals(200, $response->statusCode);
        $this->assertArrayHasKey('users', $response->data);
    }
}
```

**Note:** Integration tests require a valid access token. Since FamilySearch uses OAuth2 authorization code flow (which requires browser interaction), you'll need to:
1. Manually complete the OAuth flow once to obtain an access token
2. Store the token securely (e.g., in an environment variable)
3. Use that token in your automated tests
4. Refresh or regenerate the token when it expires

## Getting Integration Environment Access

To test against the FamilySearch Integration environment:

1. Visit https://developers.familysearch.org/
2. Create an account and register your application
3. Your app key is automatically enabled for Integration environment access
4. Obtain an access token:
   - Visit https://integration.familysearch.org/platform/ (or https://beta.familysearch.org/platform/ for Beta) to get a token without completing the full OAuth2 flow
   - Complete the OAuth2 authorization flow in your application
5. Store your credentials in environment variables:

```bash
export FAMILYSEARCH_API_KEY="your-api-key"
export FAMILYSEARCH_REDIRECT_URI="http://localhost/callback"
export FAMILYSEARCH_ACCESS_TOKEN="your-access-token"
```

**Integration Environment URL:** https://integration.familysearch.org/

**Important:** Never commit credentials or access tokens to version control. Use environment variables or a git-ignored configuration file.
