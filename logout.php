<?php
require_once __DIR__ . '/config/customer_auth.php';

if (isset($_SESSION['customer_user']['id'])) {
    clear_customer_remember_token($conn, (int) $_SESSION['customer_user']['id']);
}
unset($_SESSION['customer_user']);
header("Location: login.php");
exit();
