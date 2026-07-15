<?php
/**
 * Central security helpers used across the whole project.
 * Include this AFTER session_start() (or let it start the session for you).
 */

require_once __DIR__ . '/security_headers.php';

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie before starting it
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookieParams['path'],
        'domain'   => $cookieParams['domain'],
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // true on https
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Log an error to the PHP error log (never to the browser) and show a
 * generic, safe message to the visitor instead.
 */
function safe_fail(string $userMessage, string $technicalDetail = ''): void
{
    if ($technicalDetail !== '') {
        error_log($technicalDetail);
    }
    http_response_code(500);
    die(htmlspecialchars($userMessage));
}

/**
 * Generate (or reuse) a CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Echo a hidden <input> field carrying the CSRF token, for use inside <form>.
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify a submitted CSRF token (from $_POST['csrf_token']).
 * Stops execution with a safe error if it does not match.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Security check failed. Please refresh the page and try again.');
    }
}

/**
 * Regenerate the session ID (call this right after a successful login)
 * to prevent session fixation attacks.
 */
function regenerate_session(): void
{
    session_regenerate_id(true);
}

/**
 * Simple brute-force throttle for login forms.
 * Returns true if the request is allowed, false if locked out.
 */
function login_attempt_allowed(string $key, int $maxAttempts = 5, int $lockSeconds = 300): bool
{
    $now = time();
    if (!isset($_SESSION['login_attempts'][$key])) {
        $_SESSION['login_attempts'][$key] = ['count' => 0, 'first' => $now];
    }
    $data = $_SESSION['login_attempts'][$key];

    if ($now - $data['first'] > $lockSeconds) {
        // window expired, reset
        $_SESSION['login_attempts'][$key] = ['count' => 0, 'first' => $now];
        return true;
    }

    return $data['count'] < $maxAttempts;
}

function login_attempt_register_failure(string $key): void
{
    $now = time();
    if (!isset($_SESSION['login_attempts'][$key])) {
        $_SESSION['login_attempts'][$key] = ['count' => 0, 'first' => $now];
    }
    $_SESSION['login_attempts'][$key]['count']++;
}

function login_attempt_reset(string $key): void
{
    unset($_SESSION['login_attempts'][$key]);
}
