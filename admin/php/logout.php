<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/rbac.php';

// Clear the remember-me token (DB + cookie) so a stolen cookie can't be reused
if (isset($_SESSION['admin_user']['id'])) {
    clear_remember_me_token($conn, (int) $_SESSION['admin_user']['id']);
}

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header("Location: login.php");
exit();
