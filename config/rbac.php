<?php
/**
 * RBAC (Role-Based Access Control) helpers.
 * Include after config/db.php and config/security.php.
 */

/**
 * Load the list of permission keys for a given role_id, e.g. ['orders.view', 'orders.edit', ...]
 */
function load_role_permissions($conn, int $role_id): array
{
    $perms = [];
    $stmt = mysqli_prepare($conn, "SELECT p.`key` FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $perms[] = $row['key'];
    }
    mysqli_stmt_close($stmt);
    return $perms;
}

/**
 * Check if the currently logged-in admin user has a given permission key.
 */
function user_has_permission(string $key): bool
{
    return in_array($key, $_SESSION['admin_permissions'] ?? [], true);
}

/**
 * Require the current user to have a specific permission, or die with 403.
 * Use at the top of any admin action, e.g. require_permission('menu.delete');
 */
function require_permission(string $key): void
{
    if (!user_has_permission($key)) {
        http_response_code(403);
        die('You do not have permission to perform this action (missing: ' . htmlspecialchars($key) . ').');
    }
}

/**
 * Require the current user to hold one of the given roles, or die with 403.
 * Use e.g. require_role(['Admin']);
 */
function require_role(array $allowedRoles): void
{
    $role = $_SESSION['admin_user']['role_name'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        die('You do not have permission to access this page.');
    }
}

/**
 * Convenience: true/false version of require_role, for conditionally
 * showing/hiding UI (e.g. sidebar links, buttons) without stopping execution.
 */
function user_has_role(array $roles): bool
{
    $role = $_SESSION['admin_user']['role_name'] ?? '';
    return in_array($role, $roles, true);
}

/**
 * Full activity log write: captures who, what, when, from where, with
 * what browser, and (optionally) the record's before/after state.
 *
 * @param array|null $oldValue Associative array snapshot before the change (e.g. the row before UPDATE)
 * @param array|null $newValue Associative array snapshot after the change
 */
function log_activity($conn, string $action, string $description = '', ?array $oldValue = null, ?array $newValue = null): void
{
    $userId = $_SESSION['admin_user']['id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
    $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_SLASHES) : null;
    $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_SLASHES) : null;

    $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, description, old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'issssss', $userId, $action, $description, $oldJson, $newJson, $ip, $userAgent);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Convenience: log only the fields that actually changed between two
 * associative arrays (skips unchanged fields, and always skips password
 * hashes so they never end up in the audit trail).
 */
function log_field_changes($conn, string $action, string $description, array $before, array $after): void
{
    unset($before['password'], $after['password'], $before['remember_token'], $after['remember_token']);
    $oldDiff = [];
    $newDiff = [];
    foreach ($after as $key => $value) {
        if (!array_key_exists($key, $before)) continue;
        if ($before[$key] != $value) {
            $oldDiff[$key] = $before[$key];
            $newDiff[$key] = $value;
        }
    }
    if (empty($oldDiff)) {
        log_activity($conn, $action, $description);
        return;
    }
    log_activity($conn, $action, $description, $oldDiff, $newDiff);
}

// ---------------------------------------------------------------------
// Remember-me token helpers
// ---------------------------------------------------------------------

const REMEMBER_COOKIE_NAME = 'remember_me';
const REMEMBER_COOKIE_DAYS = 30;

/**
 * Issue a new remember-me token for a user: stores a hashed token in the
 * database and sets a long-lived cookie containing "userId:plainToken".
 * The plain token is never stored - only its hash - so a stolen database
 * dump cannot be used to forge logins.
 */
function issue_remember_me_token($conn, int $userId): void
{
    $plainToken = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $plainToken);
    $expires = date('Y-m-d H:i:s', time() + (REMEMBER_COOKIE_DAYS * 86400));

    $stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $hashedToken, $expires, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    setcookie(
        REMEMBER_COOKIE_NAME,
        $userId . ':' . $plainToken,
        [
            'expires'  => time() + (REMEMBER_COOKIE_DAYS * 86400),
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

/**
 * Clear a user's remember-me token (call on logout / password change).
 */
function clear_remember_me_token($conn, int $userId): void
{
    $stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        setcookie(REMEMBER_COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
    }
}

/**
 * If there's no active session but a valid remember-me cookie is present,
 * transparently log the user back in. Returns true if it restored a session.
 */
function attempt_remember_me_login($conn): bool
{
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return false;
    }

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$userId, $plainToken] = $parts;
    $userId = intval($userId);
    if ($userId <= 0 || empty($plainToken)) {
        return false;
    }

    $hashedToken = hash('sha256', $plainToken);

    $stmt = mysqli_prepare($conn, "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND u.remember_token = ? AND u.remember_token_expires > NOW() AND u.status = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'is', $userId, $hashedToken);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$user) {
        // invalid/expired token: clear the bad cookie
        setcookie(REMEMBER_COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/']);
        return false;
    }

    regenerate_session();
    unset($user['password']);
    $_SESSION['admin_user'] = $user;
    $_SESSION['admin_permissions'] = load_role_permissions($conn, (int) $user['role_id']);
    $_SESSION['admin_last_activity'] = time();

    // Rotate the token (one-time-use style) so a captured cookie can't be replayed forever
    issue_remember_me_token($conn, $userId);

    return true;
}
