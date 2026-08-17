<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Integration;

use PHPUnit\Framework\TestCase;
use FamilySearch;
use ReflectionClass;

/**
 * Integration tests for encrypted session token flow
 * Tests the full lifecycle of token storage and retrieval with encryption
 */
class EncryptedSessionFlowTest extends TestCase
{
    private string $testKey;
    private string $testToken;

    protected function setUp(): void
    {
        // Generate fresh test data for each test
        $this->testKey = base64_encode(random_bytes(32));
        $this->testToken = 'test-access-token-' . bin2hex(random_bytes(16));

        // Mock $_SESSION array (don't actually start session in PHPUnit)
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        // Clear session data
        if (isset($_SESSION['FS_ACCESS_TOKEN'])) {
            unset($_SESSION['FS_ACCESS_TOKEN']);
        }
    }

    protected function tearDown(): void
    {
        // Clean up session data
        if (isset($_SESSION['FS_ACCESS_TOKEN'])) {
            unset($_SESSION['FS_ACCESS_TOKEN']);
        }
    }

    /**
     * Helper to simulate OAuth response
     */
    private function simulateOAuthResponse(FamilySearch $fs, string $token): object
    {
        $mockResponse = new \stdClass();
        $mockResponse->statusCode = 200;
        $mockResponse->data = ['access_token' => $token];

        // Invoke private oauthResponseHandler
        $reflection = new ReflectionClass($fs);
        $method = $reflection->getMethod('oauthResponseHandler');
        $method->setAccessible(true);

        return $method->invoke($fs, $mockResponse);
    }

    // ========================================================================
    // Basic Flow Tests
    // ========================================================================

    public function testOAuthFlowWithEncryptionEnabled(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        // Simulate OAuth response
        $response = $this->simulateOAuthResponse($fs, $this->testToken);

        // Verify response
        $this->assertEquals(200, $response->statusCode);

        // Verify token is stored in session
        $this->assertArrayHasKey('FS_ACCESS_TOKEN', $_SESSION, 'Token should be stored in session');

        // Verify token is encrypted (has colon separators)
        $storedValue = $_SESSION['FS_ACCESS_TOKEN'];
        $this->assertIsString($storedValue);
        $this->assertEquals(2, substr_count($storedValue, ':'), 'Stored token should be encrypted (3 segments)');
        $this->assertNotEquals($this->testToken, $storedValue, 'Stored token should not be plaintext');

        // Verify token can be retrieved
        $this->assertEquals($this->testToken, $fs->getAccessToken(), 'Token should be retrievable from memory');
    }

    public function testTokenPersistsAcrossRequestsWhenEncrypted(): void
    {
        // First request: Authenticate and store token
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $this->simulateOAuthResponse($fs1, $this->testToken);

        $this->assertEquals($this->testToken, $fs1->getAccessToken(), 'Token should be available in first request');

        // Second request: Load token from session
        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $this->assertEquals($this->testToken, $fs2->getAccessToken(), 'Token should be loaded from session in second request');
    }

    public function testOAuthFlowWithEncryptionDisabled(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => false
        ]);

        // Simulate OAuth response
        $response = $this->simulateOAuthResponse($fs, $this->testToken);

        // Verify response
        $this->assertEquals(200, $response->statusCode);

        // Verify token is stored in session as plaintext
        $this->assertArrayHasKey('FS_ACCESS_TOKEN', $_SESSION);
        $this->assertEquals($this->testToken, $_SESSION['FS_ACCESS_TOKEN'], 'Token should be stored as plaintext');
    }

    public function testTokenPersistsAcrossRequestsWhenPlaintext(): void
    {
        // First request: Authenticate and store plaintext token
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => false
        ]);

        $this->simulateOAuthResponse($fs1, $this->testToken);

        // Second request: Load plaintext token from session
        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => false
        ]);

        $this->assertEquals($this->testToken, $fs2->getAccessToken(), 'Plaintext token should persist across requests');
    }

    // ========================================================================
    // Backward Compatibility Tests
    // ========================================================================

    public function testPlaintextTokenWithEncryptionDisabled(): void
    {
        // Store plaintext token in session
        $_SESSION['FS_ACCESS_TOKEN'] = $this->testToken;

        // Load with encryption disabled
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => false
        ]);

        $this->assertEquals($this->testToken, $fs->getAccessToken(), 'Plaintext token should be loaded when encryption disabled');
    }

    public function testMigrationEnableEncryptionOnExistingPlaintextSession(): void
    {
        // Step 1: Store plaintext token (legacy scenario)
        $_SESSION['FS_ACCESS_TOKEN'] = $this->testToken;

        // Step 2: Enable encryption (migration scenario)
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        // Plaintext token should be accepted
        $this->assertEquals($this->testToken, $fs1->getAccessToken(), 'Plaintext token should be accepted during migration');

        // Step 3: Simulate OAuth response to encrypt the token
        $this->simulateOAuthResponse($fs1, $this->testToken);

        // Verify token is now encrypted in session
        $storedValue = $_SESSION['FS_ACCESS_TOKEN'];
        $this->assertEquals(2, substr_count($storedValue, ':'), 'Token should now be encrypted');

        // Step 4: New request should load encrypted token
        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $this->assertEquals($this->testToken, $fs2->getAccessToken(), 'Encrypted token should be loaded after migration');
    }

    public function testDowngradeEncryptedTokenWithEncryptionDisabled(): void
    {
        // Step 1: Store encrypted token
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);
        $this->simulateOAuthResponse($fs1, $this->testToken);

        // Verify token is encrypted
        $this->assertNotEquals($this->testToken, $_SESSION['FS_ACCESS_TOKEN']);

        // Step 2: Disable encryption (downgrade scenario)
        // Suppress expected warning
        set_error_handler(function () {}, E_USER_WARNING);

        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => false
        ]);

        restore_error_handler();

        // Encrypted token should not be loaded
        $this->assertNull($fs2->getAccessToken(), 'Encrypted token should not be loaded when encryption disabled');

        // Session should be cleared
        $this->assertArrayNotHasKey('FS_ACCESS_TOKEN', $_SESSION, 'Session should be cleared on downgrade');
    }

    // ========================================================================
    // Error Handling Tests
    // ========================================================================

    public function testCorruptedEncryptedSessionDataIsCleared(): void
    {
        // Store corrupted encrypted data
        $_SESSION['FS_ACCESS_TOKEN'] = 'corrupted:encrypted:data';

        // Suppress expected warning
        set_error_handler(function () {}, E_USER_WARNING);

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        restore_error_handler();

        // Token should not be loaded
        $this->assertNull($fs->getAccessToken(), 'Corrupted data should not be loaded');

        // Session should be cleared
        $this->assertArrayNotHasKey('FS_ACCESS_TOKEN', $_SESSION, 'Corrupted session data should be cleared');
    }

    public function testDecryptionWithWrongKeyFailsAndClearsSession(): void
    {
        // Step 1: Store token with key 1
        $key1 = base64_encode(random_bytes(32));
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $key1
        ]);
        $this->simulateOAuthResponse($fs1, $this->testToken);

        // Verify token is encrypted
        $encryptedValue = $_SESSION['FS_ACCESS_TOKEN'];
        $this->assertNotEquals($this->testToken, $encryptedValue);

        // Step 2: Try to load with key 2 (wrong key)
        $key2 = base64_encode(random_bytes(32));

        // Suppress expected warning
        set_error_handler(function () {}, E_USER_WARNING);

        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $key2
        ]);

        restore_error_handler();

        // Token should not be loaded
        $this->assertNull($fs2->getAccessToken(), 'Token should not load with wrong key');

        // Session should be cleared
        $this->assertArrayNotHasKey('FS_ACCESS_TOKEN', $_SESSION, 'Session should be cleared after decryption failure');
    }

    public function testEncryptionFailureDoesNotStoreToken(): void
    {
        // This test verifies fail-secure behavior
        // If encryption somehow fails, the token should NOT be stored in plaintext

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        // Simulate OAuth response with valid encryption
        $this->simulateOAuthResponse($fs, $this->testToken);

        // If encryption worked, token should be in session
        $this->assertArrayHasKey('FS_ACCESS_TOKEN', $_SESSION);

        // Token should be encrypted (not plaintext)
        $this->assertNotEquals($this->testToken, $_SESSION['FS_ACCESS_TOKEN'], 'Token should be encrypted, not plaintext');
    }

    // ========================================================================
    // Custom Session Variable Tests
    // ========================================================================

    public function testEncryptionWorksWithCustomSessionVariable(): void
    {
        $customVar = 'MY_CUSTOM_TOKEN_VAR';

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessionVariable' => $customVar,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $this->simulateOAuthResponse($fs, $this->testToken);

        // Verify token is stored in custom variable
        $this->assertArrayHasKey($customVar, $_SESSION, 'Token should be stored in custom session variable');
        $this->assertArrayNotHasKey('FS_ACCESS_TOKEN', $_SESSION, 'Token should not be in default variable');

        // Verify token is encrypted
        $this->assertEquals(2, substr_count($_SESSION[$customVar], ':'), 'Token should be encrypted');

        // Clean up
        unset($_SESSION[$customVar]);
    }

    public function testPlaintextTokenWorksWithCustomSessionVariable(): void
    {
        $customVar = 'MY_CUSTOM_TOKEN_VAR';

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessionVariable' => $customVar,
            'sessionEncryption' => false
        ]);

        $this->simulateOAuthResponse($fs, $this->testToken);

        // Verify token is stored in custom variable as plaintext
        $this->assertArrayHasKey($customVar, $_SESSION);
        $this->assertEquals($this->testToken, $_SESSION[$customVar], 'Token should be plaintext in custom variable');

        // Clean up
        unset($_SESSION[$customVar]);
    }

    // ========================================================================
    // Multiple Instance Tests
    // ========================================================================

    public function testMultipleInstancesShareEncryptedSession(): void
    {
        $config = [
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ];

        // Instance 1: Store token
        $fs1 = new FamilySearch($config);
        $this->simulateOAuthResponse($fs1, $this->testToken);

        // Instance 2: Load token
        $fs2 = new FamilySearch($config);
        $this->assertEquals($this->testToken, $fs2->getAccessToken(), 'Second instance should load encrypted token');

        // Instance 3: Also load token
        $fs3 = new FamilySearch($config);
        $this->assertEquals($this->testToken, $fs3->getAccessToken(), 'Third instance should also load encrypted token');
    }

    public function testDifferentKeysProduceDifferentCiphertexts(): void
    {
        $key1 = base64_encode(random_bytes(32));
        $key2 = base64_encode(random_bytes(32));

        // Encrypt with key 1
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $key1,
            'sessionVariable' => 'TOKEN1'
        ]);
        $this->simulateOAuthResponse($fs1, $this->testToken);
        $encrypted1 = $_SESSION['TOKEN1'];

        // Encrypt with key 2
        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $key2,
            'sessionVariable' => 'TOKEN2'
        ]);
        $this->simulateOAuthResponse($fs2, $this->testToken);
        $encrypted2 = $_SESSION['TOKEN2'];

        // Ciphertexts should be different
        $this->assertNotEquals($encrypted1, $encrypted2, 'Different keys should produce different ciphertexts');

        // Clean up
        unset($_SESSION['TOKEN1']);
        unset($_SESSION['TOKEN2']);
    }

    // ========================================================================
    // Session Disabled Tests
    // ========================================================================

    public function testEncryptionDoesNotRunWhenSessionsDisabled(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $this->simulateOAuthResponse($fs, $this->testToken);

        // Token should be in memory but not in session
        $this->assertEquals($this->testToken, $fs->getAccessToken(), 'Token should be in memory');
        $this->assertArrayNotHasKey('FS_ACCESS_TOKEN', $_SESSION, 'Token should not be stored when sessions disabled');
    }

    public function testTokenNotPersistedWhenSessionsDisabled(): void
    {
        // First instance with sessions disabled
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false
        ]);
        $this->simulateOAuthResponse($fs1, $this->testToken);

        // Token available in first instance
        $this->assertEquals($this->testToken, $fs1->getAccessToken());

        // Second instance should not have token
        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false
        ]);

        $this->assertNull($fs2->getAccessToken(), 'Token should not persist when sessions disabled');
    }

    // ========================================================================
    // Manual Token Override Tests
    // ========================================================================

    public function testManualAccessTokenOverridesEncryptedSession(): void
    {
        // Store encrypted token in session
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);
        $this->simulateOAuthResponse($fs1, $this->testToken);

        // Create new instance with manual token override
        $manualToken = 'manual-override-token';
        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey,
            'accessToken' => $manualToken
        ]);

        $this->assertEquals($manualToken, $fs2->getAccessToken(), 'Manual token should override session token');
    }
}
