# Security Architecture Documentation

## Overview
Labour On Demand implements a multi-layered security architecture designed to protect user data, prevent unauthorized access, and mitigate common web vulnerabilities.

## Core Security Layers

### 1. Unified Security Configuration
All security parameters are centralized in `config/security_config.php`.
- **Honeypot Duration**: Configurable block period for bot-trap triggers.
- **SQLi Strictness**: Toggle for aggressive SQL injection pattern matching.
- **MFA Toggle**: Global switch for multi-factor authentication.
- **Rate Limits**: Configurable login attempts and lockout durations.

### 2. Authentication & MFA
- **Hashed OTPs**: All OTPs are hashed using `hash_hmac` with SHA-256 and a secret key.
- **Multi-Factor Authentication (MFA)**: Optional email-based OTP verification after password entry.
- **Account Lockout**: Persistent IP blocking via the `rate_limits` table after 5 failed login attempts.
- **Session Hardening**: 
  - Automatic session ID regeneration after successful login.
  - 30-minute idle timeout enforced globally via `SESSION_IDLE_TIMEOUT`.

### 3. Request Protection & Firewall
- **Firewall**: Filters malicious user agents and provides advanced SQL injection detection.
- **Input Canonicalization**: Implemented recursive decoding (URL, HTML, Unicode) to detect obfuscated payloads.
- **Multi-Language Detection**: Protection against PHP, Python, JS, and OS Injection.
- **CSRF Protection**: Synchronizer Token Pattern is used for all POST requests.
- **CAPTCHA**: Google reCAPTCHA v2 is integrated for human verification.
- **Honeypot**: Bot detection via invisible fields and form submission speed checks.

### 4. Data Integrity & Logging
- **Prepared Statements**: PDO with bound parameters is used for all database interactions.
- **Security Logger**: Detailed logging of security incidents for forensic auditing.
- **Output Escaping**: Robust use of `htmlspecialchars()` for all dynamic content.

### 5. UI Reliability & Fallbacks
- **Font Awesome Fallback**: Real-time CDN connectivity check with graceful text-based fallbacks.
- **Noscript Guidance**: Sticky warnings for users with JavaScript disabled to ensure secure interactions.

## Incident Response
Security incidents (Honeypot hits, Rate limit triggers, CSRF failures, MFA failures) are logged to the `security_incidents` table for audit and review.
