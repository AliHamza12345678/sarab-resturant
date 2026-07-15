<?php
require_once __DIR__ . '/../../../config/security.php'; // starts a hardened session
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/rbac.php';

// Idle session timeout (30 minutes of inactivity logs the admin out)
$idleTimeoutSeconds = 1800;
if (isset($_SESSION['admin_user']) && isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $idleTimeoutSeconds) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

// Not logged in? Try to silently restore the session via a "remember me" cookie
// before giving up and sending the visitor to the login page.
if (!isset($_SESSION['admin_user'])) {
    if (!attempt_remember_me_login($conn)) {
        header("Location: login.php");
        exit();
    }
}

$_SESSION['admin_last_activity'] = time();
