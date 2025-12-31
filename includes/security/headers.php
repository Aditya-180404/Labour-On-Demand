<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Security Headers Configuration
 */

// Prevent Clickjacking
header("X-Frame-Options: SAMEORIGIN");

// Prevent MIME-type sniffing
header("X-Content-Type-Options: nosniff");

// Referrer Policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions Policy
header("Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=()");

// Strict Transport Security (If HTTPS)
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Hardened CSP
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https://* https://*.cloudinary.com; frame-src https://www.google.com; frame-ancestors 'none'; connect-src 'self' https://*.cloudinary.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; base-uri 'self'; form-action 'self';");

// Extra hardening
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>
