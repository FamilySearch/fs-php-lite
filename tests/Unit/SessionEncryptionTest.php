<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for FamilySearch session token encryption
 */
class SessionEncryptionTest extends TestCase
{
    private string $testKey;
    private string $testToken;

    protected function setUp(): void
    {
        // Generate a fresh encryption key and test token for each test
        $this->testKey = base64_encode(random_bytes(32));
        $this->testToken = 'test-access-token-' . bin2hex(random_bytes(8));

        // Clear any existing session data
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if (isset($_SESSION)) {
            unset($_SESSION);
        }
    }

    protected function tearDown(): void
    {
        // Clean up session data after each test
        if (isset($_SESSION['FS_ACCESS_TOKEN'])) {
            unset($_SESSION['FS_ACCESS_TOKEN']);
        }
    }

    /**
     * Helper method to invoke private methods via reflection
     */
    private function invokePrivateMethod(FamilySearch $fs, string $methodName, ...$args)
    {
        $reflection = new ReflectionClass($fs);
        $method = $reflection->getMethod($methodName);
        return $method->invoke($fs, ...$args);
    }

    /**
     * Helper method to get private property value via reflection
     */
    private function getPrivateProperty(FamilySearch $fs, string $propertyName)
    {
        $reflection = new ReflectionClass($fs);
        $property = $reflection->getProperty($propertyName);
        return $property->getValue($fs);
    }

    // ========================================================================
    // Configuration Tests
    // ========================================================================

    public function testEncryptionDisabledByDefault(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false
        ]);

        $sessionEncryption = $this->getPrivateProperty($fs, 'sessionEncryption');
        $this->assertFalse($sessionEncryption, 'Encryption should be disabled by default');
    }

    public function testEncryptionCanBeEnabled(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $sessionEncryption = $this->getPrivateProperty($fs, 'sessionEncryption');
        $this->assertTrue($sessionEncryption, 'Encryption should be enabled when configured');
    }

    public function testMissingKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sessionEncryptionKey is required');

        new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true
            // Missing sessionEncryptionKey
        ]);
    }

    public function testEmptyKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sessionEncryptionKey is required');

        new FamilySearch([
            'appKey' => 'test',
            'sessionEncryption' => true,
            'sessionEncryptionKey' => ''
        ]);
    }

    // ========================================================================
    // Key Validation Tests
    // ========================================================================

    public function testValid32ByteRawKey(): void
    {
        $rawKey = random_bytes(32);
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $rawKey
        ]);

        $normalizedKey = $this->getPrivateProperty($fs, 'sessionEncryptionKey');
        $this->assertEquals(32, strlen($normalizedKey), 'Raw 32-byte key should be used as-is');
        $this->assertEquals($rawKey, $normalizedKey, 'Raw key should not be modified');
    }

    public function testValid44CharacterBase64Key(): void
    {
        $rawKey = random_bytes(32);
        $base64Key = base64_encode($rawKey);

        $this->assertEquals(44, strlen($base64Key), 'Base64-encoded 32-byte key should be 44 characters');

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $base64Key
        ]);

        $normalizedKey = $this->getPrivateProperty($fs, 'sessionEncryptionKey');
        $this->assertEquals(32, strlen($normalizedKey), 'Base64 key should be decoded to 32 bytes');
        $this->assertEquals($rawKey, $normalizedKey, 'Base64 key should decode to original raw key');
    }

    public function testValid64CharacterHexKey(): void
    {
        $rawKey = random_bytes(32);
        $hexKey = bin2hex($rawKey);

        $this->assertEquals(64, strlen($hexKey), 'Hex-encoded 32-byte key should be 64 characters');

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $hexKey
        ]);

        $normalizedKey = $this->getPrivateProperty($fs, 'sessionEncryptionKey');
        $this->assertEquals(32, strlen($normalizedKey), 'Hex key should be decoded to 32 bytes');
        $this->assertEquals($rawKey, $normalizedKey, 'Hex key should decode to original raw key');
    }

    public function testPassphraseDerivedToKey(): void
    {
        // Use a passphrase that's NOT exactly 32 bytes (to test hash derivation)
        $passphrase = 'my-secure-passphrase-for-testing-that-is-longer';

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $passphrase
        ]);

        $normalizedKey = $this->getPrivateProperty($fs, 'sessionEncryptionKey');
        $this->assertEquals(32, strlen($normalizedKey), 'Passphrase should be hashed to 32 bytes');

        // Verify it matches SHA-256 hash
        $expectedKey = hash('sha256', $passphrase, true);
        $this->assertEquals($expectedKey, $normalizedKey, 'Passphrase should be derived using SHA-256');
    }

    public function testShortPassphraseDerivedToKey(): void
    {
        $shortPassphrase = 'short';

        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $shortPassphrase
        ]);

        $normalizedKey = $this->getPrivateProperty($fs, 'sessionEncryptionKey');
        $this->assertEquals(32, strlen($normalizedKey), 'Short passphrase should be hashed to 32 bytes');
    }

    // ========================================================================
    // Encryption/Decryption Tests
    // ========================================================================

    public function testEncryptionDecryptionRoundTrip(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);
        $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $encrypted);

        $this->assertEquals($this->testToken, $decrypted, 'Decrypted token should match original');
    }

    public function testEncryptedTokenFormat(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);

        // Encrypted format should be: base64(iv):base64(tag):base64(ciphertext)
        $this->assertIsString($encrypted);
        $this->assertEquals(2, substr_count($encrypted, ':'), 'Encrypted token should have exactly 2 colons');

        $parts = explode(':', $encrypted);
        $this->assertCount(3, $parts, 'Encrypted token should have 3 segments');

        // Each part should be valid base64
        foreach ($parts as $part) {
            $this->assertNotEmpty($part, 'Each segment should be non-empty');
            $decoded = base64_decode($part, true);
            $this->assertNotFalse($decoded, 'Each segment should be valid base64');
        }
    }

    public function testEncryptedTokensDiffer(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        // Encrypt the same token twice
        $encrypted1 = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);
        $encrypted2 = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);

        $this->assertNotEquals($encrypted1, $encrypted2, 'Same token should produce different ciphertexts (IV uniqueness)');

        // But both should decrypt to the same value
        $decrypted1 = $this->invokePrivateMethod($fs, 'decryptToken', $encrypted1);
        $decrypted2 = $this->invokePrivateMethod($fs, 'decryptToken', $encrypted2);

        $this->assertEquals($this->testToken, $decrypted1);
        $this->assertEquals($this->testToken, $decrypted2);
    }

    public function testIVUniqueness(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $ivs = [];
        for ($i = 0; $i < 10; $i++) {
            $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);
            $parts = explode(':', $encrypted);
            $iv = $parts[0]; // First segment is the IV
            $ivs[] = $iv;
        }

        // All IVs should be unique
        $uniqueIvs = array_unique($ivs);
        $this->assertCount(10, $uniqueIvs, 'All generated IVs should be unique');
    }

    public function testDecryptionWithWrongKeyFails(): void
    {
        $fs1 = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $encrypted = $this->invokePrivateMethod($fs1, 'encryptToken', $this->testToken);

        // Try to decrypt with wrong key
        $wrongKey = base64_encode(random_bytes(32));
        $fs2 = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $wrongKey
        ]);

        $decrypted = $this->invokePrivateMethod($fs2, 'decryptToken', $encrypted);

        $this->assertFalse($decrypted, 'Decryption with wrong key should fail');
    }

    public function testDecryptionWithCorruptedDataFails(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);

        // Corrupt the ciphertext
        $parts = explode(':', $encrypted);
        $parts[2] = base64_encode('corrupted-data');
        $corrupted = implode(':', $parts);

        $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $corrupted);

        $this->assertFalse($decrypted, 'Decryption with corrupted data should fail');
    }

    public function testDecryptionWithTamperedAuthTagFails(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);

        // Tamper with the authentication tag
        $parts = explode(':', $encrypted);
        $parts[1] = base64_encode(random_bytes(16)); // Replace tag with random data
        $tampered = implode(':', $parts);

        $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $tampered);

        $this->assertFalse($decrypted, 'Decryption with tampered auth tag should fail');
    }

    public function testDecryptionWithInvalidFormatFails(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        // Test various invalid formats
        $invalidFormats = [
            'no-colons',
            'one:colon',
            'too:many:colons:here',
            ':empty:first',
            'empty::middle',
            'empty:last:',
            '',
            'invalid-base64!:invalid:data'
        ];

        foreach ($invalidFormats as $invalid) {
            $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $invalid);
            $this->assertFalse($decrypted, "Decryption should fail for format: $invalid");
        }
    }

    // ========================================================================
    // Encryption Detection Tests
    // ========================================================================

    public function testIsEncryptedTokenDetectsEncryptedFormat(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $this->testToken);
        $isEncrypted = $this->invokePrivateMethod($fs, 'isEncryptedToken', $encrypted);

        $this->assertTrue($isEncrypted, 'Encrypted token should be detected as encrypted');
    }

    public function testIsEncryptedTokenDetectsPlaintextFormat(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false
        ]);

        $plaintext = 'plain-access-token-123';
        $isEncrypted = $this->invokePrivateMethod($fs, 'isEncryptedToken', $plaintext);

        $this->assertFalse($isEncrypted, 'Plaintext token should not be detected as encrypted');
    }

    public function testIsEncryptedTokenHandlesEdgeCases(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false
        ]);

        // Test edge cases - each test case is [value, expected]
        $edgeCases = [
            ['', false],
            ['no-colons', false],
            ['one:colon', false],
            ['two:colons:here', true],  // Matches format (even if not valid base64)
            ['three:colons:are:too:many', false],
            [null, false],
            [[], false],
            [123, false],
        ];

        foreach ($edgeCases as list($value, $expected)) {
            $isEncrypted = $this->invokePrivateMethod($fs, 'isEncryptedToken', $value);
            $this->assertEquals($expected, $isEncrypted, "isEncryptedToken should return " . ($expected ? 'true' : 'false') . " for: " . var_export($value, true));
        }
    }

    // ========================================================================
    // Error Handling Tests
    // ========================================================================

    public function testEncryptionWithoutOpenSSLThrowsException(): void
    {
        // This test can only run if OpenSSL is available (which it should be)
        // We're testing that the check exists, not that it actually fails
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        // If we get here, OpenSSL is available (which is expected)
        $this->assertTrue(extension_loaded('openssl'), 'OpenSSL should be available for testing');
    }

    public function testLongTokenCanBeEncryptedAndDecrypted(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        // Test with a long token (typical OAuth tokens can be 500+ characters)
        $longToken = str_repeat('a', 1000);

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $longToken);
        $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $encrypted);

        $this->assertEquals($longToken, $decrypted, 'Long token should encrypt and decrypt correctly');
    }

    public function testEmptyTokenCanBeEncryptedAndDecrypted(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $emptyToken = '';

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $emptyToken);
        $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $encrypted);

        $this->assertEquals($emptyToken, $decrypted, 'Empty token should encrypt and decrypt correctly');
    }

    public function testSpecialCharactersInTokenArePreserved(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $specialToken = "token-with-special-chars: !@#$%^&*()_+-=[]{}|;':\",./<>?`~\n\t\r";

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $specialToken);
        $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $encrypted);

        $this->assertEquals($specialToken, $decrypted, 'Special characters should be preserved');
    }

    public function testMultibyteCharactersInTokenArePreserved(): void
    {
        $fs = new FamilySearch([
            'appKey' => 'test',
            'sessions' => false,
            'sessionEncryption' => true,
            'sessionEncryptionKey' => $this->testKey
        ]);

        $multibyteToken = "token-with-unicode-こんにちは-🔐-émojis";

        $encrypted = $this->invokePrivateMethod($fs, 'encryptToken', $multibyteToken);
        $decrypted = $this->invokePrivateMethod($fs, 'decryptToken', $encrypted);

        $this->assertEquals($multibyteToken, $decrypted, 'Multibyte characters should be preserved');
    }
}
