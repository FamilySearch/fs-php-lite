# Security Policy

## Security Overview

### What the SDK Provides

The FamilySearch PHP Lite SDK provides:

- **Optional AES-256-GCM encryption** for OAuth access tokens stored in PHP sessions
- **Authenticated encryption** with tamper detection (prevents ciphertext modification)
- **Automatic key normalization** supporting multiple key formats (raw, base64, hex, passphrase)
- **Backward compatibility** for seamless migration from plaintext to encrypted storage
- **Fail-secure behavior** (encryption failures never fall back to plaintext storage)

### What You Are Responsible For

As a developer using this SDK, you are responsible for:

- **Enabling encryption** in production environments
- **Generating and managing** secure encryption keys
- **Configuring PHP** session settings securely
- **Setting proper file permissions** on session storage directories
- **Enforcing HTTPS** for all API communications
- **Implementing secure session management** practices

---

## Session Token Storage

### Default Behavior: Plaintext Storage

**⚠️ Warning:** By default, OAuth access tokens are stored in plaintext in PHP session files.

```php
// Default configuration (NOT SECURE for production)
$fs = new FamilySearch([
    'appKey' => 'your-app-key',
    'sessionEncryption' => false  // Default: tokens stored in plaintext
]);
```

**Risk:** If an attacker gains read access to your server's filesystem, they can read session files and extract access tokens. This could happen through:
- Misconfigured file permissions
- Backup file exposure
- Server compromise
- Shared hosting environment vulnerabilities
- Container/VM snapshot leaks

### Secure Configuration: Encrypted Storage

**✅ Recommended:** Enable AES-256-GCM encryption for production:

```php
$fs = new FamilySearch([
    'appKey' => $_ENV['FS_APP_KEY'],
    'sessionEncryption' => true,
    'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY']
]);
```

### What Encryption Protects Against

Encryption provides **defense-in-depth** against:

✅ **Passive filesystem access** (attacker reads session files from disk)  
✅ **Backup exposure** (encrypted session files in backups remain protected)  
✅ **Forensic analysis** (disk forensics cannot recover plaintext tokens)  
✅ **Accidental logging** (encrypted values logged instead of plaintext tokens)  
✅ **Container/VM snapshots** (session data remains encrypted in snapshots)  
✅ **Shared hosting risks** (other tenants cannot read your tokens)  

### What Encryption Does NOT Protect Against

Encryption is **not a silver bullet**. It does NOT protect against:

❌ **Memory dumps** (tokens are plaintext in PHP process memory during execution)  
❌ **Active server compromise** (attacker with code execution can access encryption keys)  
❌ **XSS attacks** (client-side JavaScript attacks bypass server-side encryption)  
❌ **Session hijacking** (valid session IDs grant access regardless of encryption)  
❌ **Stolen encryption keys** (attacker with key can decrypt all tokens)  
❌ **Network interception** (HTTPS is required separately for transport security)  

**Bottom Line:** Encryption protects data **at rest** on disk. You still need proper access controls, secure coding practices, HTTPS, and secure session management.

---

## Encryption Best Practices

### 1. Generate Secure Encryption Keys

**✅ Correct: Use cryptographically secure random bytes**

```bash
# Generate a secure 32-byte key and encode as base64
php -r "echo base64_encode(random_bytes(32));"
# Output: WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo=

# Alternative: Using OpenSSL
php -r "echo base64_encode(openssl_random_pseudo_bytes(32));"

# Or generate hex format
php -r "echo bin2hex(random_bytes(32));"
# Output: 4c0bd859f72d55003baa72e76fea385e599c9562b1b75a1fec0831b19f04118a
```

**❌ Wrong: Weak or predictable keys**

```php
// DO NOT DO THIS - Weak keys
'sessionEncryptionKey' => 'mysecretkey'           // Too short, predictable
'sessionEncryptionKey' => 'password123'           // Dictionary word
'sessionEncryptionKey' => md5('my-app-name')      // Predictable
'sessionEncryptionKey' => date('Y-m-d')           // Guessable
```

### 2. Store Keys in Environment Variables

**✅ Correct: Environment variables**

```php
// Load key from environment (12-factor app pattern)
$fs = new FamilySearch([
    'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY']
]);
```

```bash
# Set environment variable in production
export FS_SESSION_ENCRYPTION_KEY="WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo="

# Or use .env file (excluded from version control)
echo "FS_SESSION_ENCRYPTION_KEY=WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo=" >> .env
```

**❌ Wrong: Hardcoded in source code**

```php
// DO NOT DO THIS - Key in source code
$fs = new FamilySearch([
    'sessionEncryptionKey' => 'WdaFfj4iL3Epz2o9phaBbh7FyA5fJs3lCcr6YB4QQxo=' // NEVER COMMIT THIS
]);
```

### 3. Use Secure Key Storage Solutions

For production environments, use dedicated secrets management:

**Cloud Providers:**
- **AWS:** AWS Secrets Manager or Parameter Store
- **Azure:** Azure Key Vault
- **GCP:** Google Secret Manager
- **Heroku:** Config Vars
- **Docker:** Docker Secrets

**Self-Hosted:**
- **HashiCorp Vault**
- **Kubernetes Secrets**
- **Ansible Vault**

**Example with AWS Secrets Manager:**

```php
// Retrieve key from AWS Secrets Manager
$client = new SecretsManagerClient(['region' => 'us-east-1']);
$result = $client->getSecretValue(['SecretId' => 'fs-session-encryption-key']);
$key = json_decode($result['SecretString'], true)['key'];

$fs = new FamilySearch([
    'sessionEncryptionKey' => $key
]);
```

### 4. Key Rotation Strategy

Rotate encryption keys periodically (every 90 days recommended):

**Step 1: Generate new key**
```bash
php -r "echo base64_encode(random_bytes(32));"
```

**Step 2: Deploy new key** (keep old key available temporarily)
```bash
# Set new key
export FS_SESSION_ENCRYPTION_KEY_NEW="<new-key>"
```

**Step 3: Migrate sessions** (users re-authenticate naturally over time)
- Old sessions decrypt with old key
- New sessions encrypt with new key
- After migration period (7-30 days), remove old key

**Step 4: Update application**
```php
// Try new key first, fallback to old key during migration
$keys = [
    $_ENV['FS_SESSION_ENCRYPTION_KEY_NEW'],  // Primary key
    $_ENV['FS_SESSION_ENCRYPTION_KEY_OLD']   // Fallback during migration
];
```

### 5. Environment-Specific Keys

**✅ Use different keys per environment:**

```bash
# Development
FS_SESSION_ENCRYPTION_KEY="dev-key-here"

# Staging
FS_SESSION_ENCRYPTION_KEY="staging-key-here"

# Production
FS_SESSION_ENCRYPTION_KEY="production-key-here"
```

**❌ Never reuse keys across environments.** If a development key is compromised, it should not affect production.

---

## Server Configuration

### 1. PHP Session Directory Permissions

**Verify your session directory permissions:**

```bash
# Find your session directory
php -r "echo session_save_path();"

# Check permissions
ls -ld /var/lib/php/sessions

# Should show: drwx------ (700) - only owner can read/write/execute
```

**✅ Secure configuration:**

```bash
# Set proper permissions (owner only)
sudo chmod 700 /var/lib/php/sessions
sudo chown www-data:www-data /var/lib/php/sessions  # Use your web server user
```

**❌ Insecure configurations to avoid:**

```bash
# DO NOT DO THIS
sudo chmod 777 /var/lib/php/sessions  # World-readable! Anyone can read tokens!
sudo chmod 755 /var/lib/php/sessions  # World-readable!
```

### 2. PHP Session Configuration

**Edit `/etc/php/8.x/apache2/php.ini` (or `/etc/php/8.x/fpm/php.ini`):**

```ini
; Session save path with proper permissions
session.save_path = "/var/lib/php/sessions"

; Use strict mode - reject uninitialized session IDs
session.use_strict_mode = 1

; Cookies only (no URL session IDs)
session.use_cookies = 1
session.use_only_cookies = 1

; HTTPS only in production (prevents interception)
session.cookie_secure = 1

; HTTP only (prevents JavaScript access - XSS mitigation)
session.cookie_httponly = 1

; SameSite protection (CSRF mitigation)
session.cookie_samesite = "Strict"

; Prevent session fixation
session.use_trans_sid = 0

; Regenerate session ID after authentication
; (implement in your application code)

; Strong session ID entropy
session.sid_length = 48
session.sid_bits_per_character = 6
```

**Apply configuration changes:**

```bash
# Apache
sudo systemctl restart apache2

# Nginx + PHP-FPM
sudo systemctl restart php8.x-fpm
sudo systemctl restart nginx
```

### 3. Verify Session Security

**Test script to verify session configuration:**

```php
<?php
// test_session_security.php
session_start();

echo "Session Configuration:\n";
echo "=====================\n";
echo "session.use_strict_mode: " . ini_get('session.use_strict_mode') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "session.use_trans_sid: " . ini_get('session.use_trans_sid') . "\n";
echo "session.save_path: " . session_save_path() . "\n";

// Check permissions
$path = session_save_path();
$perms = substr(sprintf('%o', fileperms($path)), -3);
echo "\nSession directory permissions: $perms\n";

if ($perms === '700') {
    echo "✅ Permissions are secure\n";
} else {
    echo "❌ WARNING: Permissions should be 700, current: $perms\n";
}
?>
```

### 4. Enforce HTTPS

**Apache `.htaccess`:**

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Nginx:**

```nginx
# Force HTTPS
server {
    listen 80;
    server_name example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name example.com;
    
    ssl_certificate /etc/ssl/certs/example.com.crt;
    ssl_certificate_key /etc/ssl/private/example.com.key;
    
    # Strong SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers on;
    
    # ... rest of configuration
}
```

---

## Production Deployment Checklist

Use this checklist before deploying to production:

### Application Security
- [ ] **Encryption enabled** (`sessionEncryption: true`)
- [ ] **Encryption key generated** using `random_bytes(32)`
- [ ] **Encryption key stored** in environment variable (not hardcoded)
- [ ] **Different keys** per environment (dev, staging, production)
- [ ] **No credentials hardcoded** in application code
- [ ] **No credentials committed** to version control

### Server Configuration
- [ ] **Session directory permissions** set to `700` (owner only)
- [ ] **Session directory owner** is web server user (e.g., `www-data`)
- [ ] **PHP session settings** configured securely (see above)
- [ ] **`session.cookie_secure = 1`** (HTTPS only)
- [ ] **`session.cookie_httponly = 1`** (no JavaScript access)
- [ ] **`session.cookie_samesite = "Strict"`** (CSRF protection)
- [ ] **HTTPS enforced** for entire application
- [ ] **Valid SSL certificate** installed and auto-renewing

### Monitoring & Maintenance
- [ ] **Security headers** configured (CSP, X-Frame-Options, etc.)
- [ ] **Error logging** enabled but errors not displayed to users
- [ ] **Key rotation schedule** established (every 90 days)
- [ ] **Security updates** process for PHP and dependencies
- [ ] **Backup encryption keys** stored securely (encrypted backups)
- [ ] **Incident response plan** documented

### Testing
- [ ] **Test encryption** works correctly in staging
- [ ] **Test session persistence** across requests
- [ ] **Test key rotation** procedure
- [ ] **Test HTTPS enforcement** (HTTP should redirect)
- [ ] **Verify session cookies** have secure flags set

---

## Threat Model

### Threat: Filesystem Access to Session Files

**Risk Level:** HIGH

**Attack Scenario:**
- Attacker gains read access to server filesystem
- Session files in `/var/lib/php/sessions` are readable
- Attacker extracts plaintext OAuth tokens from session files

**Mitigation:**

1. **Enable encryption** (primary defense):
   ```php
   'sessionEncryption' => true,
   'sessionEncryptionKey' => $_ENV['FS_SESSION_ENCRYPTION_KEY']
   ```

2. **Set proper file permissions** (defense-in-depth):
   ```bash
   chmod 700 /var/lib/php/sessions
   ```

3. **Use separate storage** (advanced):
   ```php
   // Store sessions in Redis or Memcached
   session.save_handler = redis
   session.save_path = "tcp://127.0.0.1:6379"
   ```

**Residual Risk:** LOW (after mitigations)

---

### Threat: Session Hijacking

**Risk Level:** HIGH

**Attack Scenario:**
- Attacker intercepts session cookie (via XSS, network sniffing, or malware)
- Attacker uses stolen session ID to impersonate user
- Encryption doesn't prevent this (attacker has valid session ID)

**Mitigation:**

1. **Enforce HTTPS** (prevent network interception):
   ```ini
   session.cookie_secure = 1
   ```

2. **HTTPOnly cookies** (prevent XSS theft):
   ```ini
   session.cookie_httponly = 1
   ```

3. **SameSite cookies** (prevent CSRF):
   ```ini
   session.cookie_samesite = "Strict"
   ```

4. **Regenerate session ID** after login:
   ```php
   // After successful authentication
   session_regenerate_id(true);
   ```

5. **IP/User-Agent validation** (advanced):
   ```php
   $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
   $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
   // Verify on subsequent requests
   ```

**Residual Risk:** MEDIUM (after mitigations)

---

### Threat: Encryption Key Exposure

**Risk Level:** CRITICAL

**Attack Scenario:**
- Encryption key hardcoded in source code committed to Git
- Attacker accesses GitHub repository
- All encrypted sessions can be decrypted

**Mitigation:**

1. **Never commit keys** to version control:
   ```bash
   # Add to .gitignore
   echo ".env" >> .gitignore
   echo "config/secrets.php" >> .gitignore
   ```

2. **Use environment variables**:
   ```bash
   export FS_SESSION_ENCRYPTION_KEY="<key>"
   ```

3. **Use secrets management** (production):
   - AWS Secrets Manager
   - HashiCorp Vault
   - Azure Key Vault

4. **Scan for leaked secrets**:
   ```bash
   # Use git-secrets or similar
   git secrets --install
   git secrets --register-aws
   ```

**Residual Risk:** LOW (after mitigations)

---

### Threat: Memory Dumps / Process Inspection

**Risk Level:** MEDIUM

**Attack Scenario:**
- Attacker gains access to server with elevated privileges
- Attacker dumps PHP process memory or debugs running process
- Encryption keys and decrypted tokens extracted from memory

**Reality Check:**
- ❌ **Session encryption DOES NOT protect against this**
- Tokens are plaintext in memory during request processing
- Encryption only protects data **at rest** on disk

**Mitigation:**

1. **Operating system security** (primary defense):
   - Restrict SSH access (key-based only)
   - Disable root login
   - Use firewalls (only open necessary ports)
   - Keep OS patched

2. **Principle of least privilege**:
   - Web server runs as unprivileged user
   - No shell access for web server user
   - Restrict sudo access

3. **Process isolation**:
   - Use containers (Docker) or VMs
   - SELinux or AppArmor profiles
   - PHP-FPM pools per application

**Residual Risk:** MEDIUM (OS compromise is severe regardless)

---

### Threat: XSS (Cross-Site Scripting) Attacks

**Risk Level:** HIGH

**Attack Scenario:**
- Attacker injects malicious JavaScript into your application
- JavaScript steals session cookie or makes API calls as user
- Encryption doesn't prevent this (attack happens client-side)

**Reality Check:**
- ❌ **Session encryption DOES NOT protect against XSS**
- XSS attacks happen in the browser, not on the server
- Attacker can make authenticated API calls directly

**Mitigation:**

1. **HTTPOnly cookies** (prevent cookie theft):
   ```ini
   session.cookie_httponly = 1
   ```

2. **Content Security Policy** (prevent script injection):
   ```php
   header("Content-Security-Policy: default-src 'self'; script-src 'self';");
   ```

3. **Output escaping** (prevent HTML injection):
   ```php
   echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
   ```

4. **Input validation**:
   ```php
   $clean_input = filter_var($input, FILTER_SANITIZE_STRING);
   ```

**Residual Risk:** MEDIUM (XSS is application-specific)

---

## Key Rotation Procedure

### When to Rotate Keys

Rotate encryption keys:
- **Every 90 days** (recommended)
- **Immediately** if key compromise suspected
- **After employee departure** (if they had key access)
- **After security incident**

### Rotation Steps

**1. Generate new key:**
```bash
NEW_KEY=$(php -r "echo base64_encode(random_bytes(32));")
echo "New key: $NEW_KEY"
```

**2. Deploy new key alongside old key:**
```bash
# Keep old key for backward compatibility
export FS_SESSION_ENCRYPTION_KEY_OLD="$FS_SESSION_ENCRYPTION_KEY"
export FS_SESSION_ENCRYPTION_KEY="$NEW_KEY"
```

**3. Update application** to try new key first, fallback to old:
```php
// During migration period
$keys = [
    $_ENV['FS_SESSION_ENCRYPTION_KEY'],     // New key (primary)
    $_ENV['FS_SESSION_ENCRYPTION_KEY_OLD']  // Old key (fallback)
];

// Try decryption with each key
foreach ($keys as $key) {
    $fs = new FamilySearch([
        'sessionEncryptionKey' => $key,
        'sessionEncryption' => true
    ]);
    // If successful, break
}
```

**4. Wait for migration period** (7-30 days):
- Users gradually re-authenticate
- Old sessions expire naturally
- New sessions use new key

**5. Remove old key:**
```bash
unset FS_SESSION_ENCRYPTION_KEY_OLD
```

---

## Reporting Security Issues

### Responsible Disclosure

If you discover a security vulnerability in this SDK, please report it responsibly:

**DO:**
- ✅ Email security issues privately to: [justincyork@gmail.com](mailto:justincyork@gmail.com)
- ✅ Provide detailed steps to reproduce
- ✅ Include proof-of-concept code (if applicable)
- ✅ Give us reasonable time to fix (90 days)

**DON'T:**
- ❌ Publicly disclose vulnerabilities before fix is released
- ❌ Exploit vulnerabilities in production systems
- ❌ Demand payment for vulnerability disclosure

### What to Include in Report

Please include:
- SDK version affected
- Description of vulnerability
- Steps to reproduce
- Proof-of-concept code
- Potential impact assessment
- Suggested fix (if applicable)

**Example Report:**

```
Subject: [SECURITY] Session Token Exposure via [vector]

SDK Version: 1.2.0

Description:
Under certain conditions, access tokens may be logged in plaintext
when [specific scenario].

Steps to Reproduce:
1. Enable debug logging
2. Perform OAuth flow
3. Check logs at /var/log/php-errors.log

Impact:
Access tokens exposed in log files, potential unauthorized API access.

Proof of Concept:
[code here]

Suggested Fix:
Mask tokens in debug output using [approach].
```

### Response Timeline

We aim to:
- **Acknowledge** your report within 48 hours
- **Provide initial assessment** within 7 days
- **Release a fix** within 90 days (or explain delay)
- **Credit you** in release notes (if desired)

### Security Advisory Process

1. **Confirmed vulnerability** → We create private security advisory
2. **Fix developed** → Tested and reviewed
3. **Fix released** → Published with CVE (if applicable)
4. **Public disclosure** → After users have time to update

---

## Additional Resources

### Security Tools

- **Secrets scanning:** [git-secrets](https://github.com/awslabs/git-secrets)
- **Dependency scanning:** [composer audit](https://getcomposer.org/doc/03-cli.md#audit)
- **PHP security:** [Snyk](https://snyk.io/), [OWASP Dependency-Check](https://owasp.org/www-project-dependency-check/)

### Security References

- [OWASP PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [PHP Session Security](https://www.php.net/manual/en/session.security.php)
- [NIST Cryptographic Standards](https://csrc.nist.gov/publications/detail/sp/800-175b/rev-1/final)
- [FamilySearch API Documentation](https://www.familysearch.org/developers/docs/api/)

### PHP Security Configuration

- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [Session Management](https://www.php.net/manual/en/session.security.management.php)
- [OpenSSL Functions](https://www.php.net/manual/en/ref.openssl.php)

---

**Last Updated:** 2026-08-12  
**SDK Version:** 1.3.0+  
**Encryption Feature:** Since v1.3.0
