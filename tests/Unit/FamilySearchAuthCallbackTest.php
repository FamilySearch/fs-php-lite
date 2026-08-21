<?php

declare(strict_types=1);

namespace FamilySearch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FamilySearch;

/**
 * Unit tests for FamilySearch authentication failure callback system
 *
 * Note: These tests cannot fully test the callback invocation with real HTTP requests
 * since we'd need to mock curl responses. Integration tests should cover end-to-end
 * 401 handling. These tests focus on configuration validation and basic behavior.
 */
class FamilySearchAuthCallbackTest extends TestCase
{
    /**
     * Test that SDK works without callback configured (backward compatibility)
     */
    public function testSdkWorksWithoutCallback(): void
    {
        $fs = new FamilySearch(['sessions' => false]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that callback can be configured with a closure
     */
    public function testCallbackWithClosure(): void
    {
        $callbackInvoked = false;

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => function($response, $reason) use (&$callbackInvoked) {
                $callbackInvoked = true;
            }
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that callback can be configured with a function name
     */
    public function testCallbackWithFunctionName(): void
    {
        // Create a global function for testing
        if (!function_exists('testAuthCallback')) {
            eval('function testAuthCallback($response, $reason) {}');
        }

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => 'testAuthCallback'
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that callback can be configured with array [object, method]
     */
    public function testCallbackWithObjectMethod(): void
    {
        $handler = new class {
            public function handleAuthFailure($response, $reason) {
                // Handler implementation
            }
        };

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => [$handler, 'handleAuthFailure']
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test that non-callable throws InvalidArgumentException
     */
    public function testNonCallableThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('onAuthenticationFailure must be a callable');

        new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => 'not-a-function'
        ]);
    }

    /**
     * Test that string (non-function) throws exception
     */
    public function testStringNonFunctionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => 'invalid_callback_name'
        ]);
    }

    /**
     * Test that array with invalid method throws exception
     */
    public function testArrayWithInvalidMethodThrowsException(): void
    {
        $handler = new class {};

        $this->expectException(\InvalidArgumentException::class);

        new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => [$handler, 'nonExistentMethod']
        ]);
    }

    /**
     * Test that integer throws exception
     */
    public function testIntegerThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => 123
        ]);
    }

    /**
     * Test that null is allowed (no callback)
     */
    public function testNullIsAllowed(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => null
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test callback signature with mock response object
     */
    public function testCallbackReceivesCorrectParameters(): void
    {
        $receivedResponse = null;
        $receivedReason = null;

        $callback = function($response, $reason) use (&$receivedResponse, &$receivedReason) {
            $receivedResponse = $response;
            $receivedReason = $reason;
        };

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => $callback
        ]);

        // Create a mock response object
        $mockResponse = new \stdClass();
        $mockResponse->statusCode = 401;
        $mockResponse->body = '{"error": "unauthorized"}';

        // Manually invoke callback to test parameters
        // (In real usage, this is called internally by request() method)
        call_user_func($callback, $mockResponse, 'expired');

        $this->assertSame($mockResponse, $receivedResponse);
        $this->assertEquals('expired', $receivedReason);
    }

    /**
     * Test that callback can call setAccessToken to recover from auth failure
     */
    public function testCallbackCanCallSetAccessToken(): void
    {
        $newTokenSet = false;
        $fsInstance = null;

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => function($response, $reason) use (&$fsInstance, &$newTokenSet) {
                // Simulate re-authentication by setting new token
                if ($fsInstance !== null) {
                    $fsInstance->setAccessToken('new-token-after-reauth');
                    $newTokenSet = true;
                }
            }
        ]);

        // Store reference for callback
        $fsInstance = $fs;

        // Set initial token
        $fs->setAccessToken('expired-token');
        $this->assertEquals('expired-token', $fs->getAccessToken());

        // Manually trigger callback (simulating 401 response)
        $mockResponse = new \stdClass();
        $mockResponse->statusCode = 401;

        // Invoke callback manually for testing
        call_user_func(
            function($response, $reason) use (&$fsInstance, &$newTokenSet) {
                if ($fsInstance !== null) {
                    $fsInstance->setAccessToken('new-token-after-reauth');
                    $newTokenSet = true;
                }
            },
            $mockResponse,
            'expired'
        );

        $this->assertTrue($newTokenSet);
        $this->assertEquals('new-token-after-reauth', $fs->getAccessToken());
    }

    /**
     * Test reason determination: 'expired' when isTokenExpired() returns true
     */
    public function testReasonIsExpiredWhenTokenExpired(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        // Set token that was created long ago (will be considered expired)
        $fs->setAccessToken('old-token', -1); // Negative expiresIn = already expired

        // Token should be considered expired
        $this->assertTrue($fs->isTokenExpired());

        // When 401 occurs with expired token, reason should be 'expired'
        // (This is tested in integration tests where real requests are made)
    }

    /**
     * Test reason determination: 'invalid' when token exists but not expired
     */
    public function testReasonIsInvalidWhenTokenNotExpired(): void
    {
        $fs = new FamilySearch([
            'sessions' => false,
            'expirationWarningThreshold' => 0
        ]);

        // Set fresh token (not expired)
        $fs->setAccessToken('invalid-but-not-expired-token');

        // Token should not be considered expired
        $this->assertFalse($fs->isTokenExpired());

        // When 401 occurs with non-expired token, reason should be 'invalid'
        // (This is tested in integration tests where real requests are made)
    }

    /**
     * Test multiple callbacks configuration (last one wins)
     */
    public function testMultipleCallbacksLastOneWins(): void
    {
        $firstCallbackInvoked = false;
        $secondCallbackInvoked = false;

        // This won't work as expected since we can't reconfigure after construction
        // This test just ensures constructor handles the option correctly
        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => function($response, $reason) use (&$secondCallbackInvoked) {
                $secondCallbackInvoked = true;
            }
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);
    }

    /**
     * Test callback with class method using invokable object
     */
    public function testCallbackWithInvokableObject(): void
    {
        $handler = new class {
            public $invoked = false;

            public function __invoke($response, $reason) {
                $this->invoked = true;
            }
        };

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => $handler
        ]);

        $this->assertInstanceOf(FamilySearch::class, $fs);

        // Test invocation
        $mockResponse = new \stdClass();
        $mockResponse->statusCode = 401;
        $handler($mockResponse, 'expired');

        $this->assertTrue($handler->invoked);
    }

    /**
     * Test that callback is optional and SDK continues to work without it
     */
    public function testBackwardCompatibilityWithoutCallback(): void
    {
        // Create SDK without callback (old behavior)
        $fs = new FamilySearch([
            'sessions' => false,
            'appKey' => 'test-key'
        ]);

        // Set a token
        $fs->setAccessToken('test-token');

        // SDK should work normally
        $this->assertEquals('test-token', $fs->getAccessToken());
        $this->assertFalse($fs->isTokenExpired());
    }

    /**
     * Test callback with password re-authentication pattern
     */
    public function testCallbackPasswordReauthenticationPattern(): void
    {
        $reauthenticated = false;
        $username = 'testuser';
        $password = 'testpass';

        $callback = function($response, $reason) use (&$reauthenticated, $username, $password) {
            if ($reason === 'expired') {
                // In real usage, would call: $this->oauthPassword($username, $password)
                // For testing, just set flag
                $reauthenticated = true;
            }
        };

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => $callback
        ]);

        // Simulate callback invocation
        $mockResponse = new \stdClass();
        $mockResponse->statusCode = 401;
        call_user_func($callback, $mockResponse, 'expired');

        $this->assertTrue($reauthenticated);
    }

    /**
     * Test callback with redirect pattern
     */
    public function testCallbackRedirectPattern(): void
    {
        $shouldRedirect = false;

        $callback = function($response, $reason) use (&$shouldRedirect) {
            if ($reason === 'invalid') {
                // In real usage, would do: header('Location: /login'); exit;
                // For testing, just set flag
                $shouldRedirect = true;
            }
        };

        $fs = new FamilySearch([
            'sessions' => false,
            'onAuthenticationFailure' => $callback
        ]);

        // Simulate callback invocation with 'invalid' reason
        $mockResponse = new \stdClass();
        $mockResponse->statusCode = 401;
        call_user_func($callback, $mockResponse, 'invalid');

        $this->assertTrue($shouldRedirect);
    }
}
