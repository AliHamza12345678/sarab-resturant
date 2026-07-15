<?php
/**
 * Customer-facing authentication helpers.
 * Deliberately separate from admin/html/include/auth.php + config/rbac.php:
 * customers use $_SESSION['customer_user'] (never 'admin_user'), so a
 * single browser could in theory be logged into the admin panel and as a
 * customer at the same time without either session clobbering the other.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php'; // starts the hardened session

const CUSTOMER_REMEMBER_COOKIE = 'customer_remember_me';
const CUSTOMER_REMEMBER_DAYS = 30;

function customer_logged_in(): bool
{
    return isset($_SESSION['customer_user']);
}

function current_customer(): ?array
{
    return $_SESSION['customer_user'] ?? null;
}

/**
 * Redirect to login if not authenticated as a customer. Call at the top
 * of any "my account" style page.
 */
function require_customer_login(): void
{
    global $conn;
    if (!customer_logged_in()) {
        if (!empty($_COOKIE[CUSTOMER_REMEMBER_COOKIE]) && attempt_customer_remember_login($conn)) {
            return;
        }
        $returnTo = urlencode($_SERVER['REQUEST_URI'] ?? 'my-account.php');
        header("Location: login.php?redirect={$returnTo}");
        exit();
    }
}

function customer_login_session(array $user): void
{
    regenerate_session();
    unset($user['password']);
    $_SESSION['customer_user'] = $user;
}

function issue_customer_remember_token($conn, int $userId): void
{
    $plainToken = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $plainToken);
    $expires = date('Y-m-d H:i:s', time() + (CUSTOMER_REMEMBER_DAYS * 86400));

    $stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $hashedToken, $expires, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    setcookie(CUSTOMER_REMEMBER_COOKIE, $userId . ':' . $plainToken, [
        'expires' => time() + (CUSTOMER_REMEMBER_DAYS * 86400),
        'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function clear_customer_remember_token($conn, int $userId): void
{
    $stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (isset($_COOKIE[CUSTOMER_REMEMBER_COOKIE])) {
        setcookie(CUSTOMER_REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE[CUSTOMER_REMEMBER_COOKIE]);
    }
}

function attempt_customer_remember_login($conn): bool
{
    if (empty($_COOKIE[CUSTOMER_REMEMBER_COOKIE])) return false;
    $parts = explode(':', $_COOKIE[CUSTOMER_REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) return false;
    [$userId, $plainToken] = $parts;
    $userId = intval($userId);
    if ($userId <= 0 || empty($plainToken)) return false;

    $hashedToken = hash('sha256', $plainToken);
    $stmt = mysqli_prepare($conn, "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id
        WHERE u.id=? AND u.remember_token=? AND u.remember_token_expires > NOW() AND u.status=1 AND r.name='Customer' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'is', $userId, $hashedToken);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$user) {
        setcookie(CUSTOMER_REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        return false;
    }

    customer_login_session($user);
    issue_customer_remember_token($conn, $userId); // rotate
    return true;
}
