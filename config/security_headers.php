<?php
/**
 * Production security headers.
 * Included very early (before any HTML output) on every page via
 * config/security.php, so headers are always sent regardless of entry point.
 */

if (!headers_sent()) {
    // Prevent the browser from guessing content types (stops some XSS vectors)
    header('X-Content-Type-Options: nosniff');

    // Disallow the site being embedded in an iframe anywhere else (clickjacking protection)
    header('X-Frame-Options: SAMEORIGIN');

    // Only send the origin (not full URL/query string) as referrer to other sites
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Disable powerful browser features this site never uses
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

    // Content-Security-Policy: restricts where scripts/styles/images/etc. may load from.
    // 'unsafe-inline' is required because this template uses inline <script>/<style>
    // blocks throughout; a stricter nonce-based CSP would need those refactored out.
    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
         . "img-src 'self' data: https:; "
         . "connect-src 'self'; "
         . "frame-ancestors 'self'; "
         . "base-uri 'self'; "
         . "form-action 'self'";
    header("Content-Security-Policy: $csp");

    // HSTS: only send when actually served over HTTPS (sending it over plain HTTP
    // is meaningless and can be actively harmful in local/dev environments).
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Force HTTPS in production. Call this explicitly (not automatic) so local
 * HTTP development still works without a certificate. Add a call to this
 * at the top of header.php / admin auth.php once a real SSL certificate is
 * installed on the production domain.
 */
function force_https_redirect(): void
{
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (!$isHttps && php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
        $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirectUrl, true, 301);
        exit();
    }
}
