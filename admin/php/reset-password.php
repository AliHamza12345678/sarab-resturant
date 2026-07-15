<?php
require_once '../../config/db.php';
require_once '../../config/security.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';
$error_msg = "";
$success_msg = "";

// Validate the token is real, unexpired, and matches the email
$valid = false;
if (!empty($token) && !empty($email)) {
    $stmt = mysqli_prepare($conn, "SELECT id FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ss', $email, $token);
    mysqli_stmt_execute($stmt);
    $reset = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $valid = (bool) $reset;
}

if ($valid && isset($_POST['reset_btn'])) {
    verify_csrf();
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['password_confirm'] ?? '');

    if (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error_msg = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $u = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");
        mysqli_stmt_bind_param($u, 'ss', $hashed, $email);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        // Invalidate all outstanding reset tokens for this email (one-time use)
        $d = mysqli_prepare($conn, "DELETE FROM password_resets WHERE email = ?");
        mysqli_stmt_bind_param($d, 's', $email);
        mysqli_stmt_execute($d);
        mysqli_stmt_close($d);

        $success_msg = "Password reset successfully. You can now log in.";
        $valid = false; // token consumed, hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | Admin Panel</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-card">
      <a class="auth-brand" href="login.php"><span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span><span><strong>Admin Panel</strong><small>Set a new password</small></span></a>

      <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <div class="auth-footer"><a href="login.php">Go to login</a></div>
      <?php elseif (!$valid): ?>
        <div class="alert alert-danger">This reset link is invalid or has expired. Please request a new one.</div>
        <div class="auth-footer"><a href="forgot-password.php">Request a new link</a></div>
      <?php else: ?>
        <form class="needs-validation" method="POST" novalidate>
          <?php csrf_field(); ?>
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
          <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
          <div class="mb-4">
            <p class="eyebrow mb-1">Secure Access</p>
            <h1 class="h3 mb-1">Set New Password</h1>
          </div>
          <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
          <div class="mb-3"><label class="form-label">New password</label><input class="form-control" type="password" name="password" minlength="6" required></div>
          <div class="mb-4"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="password_confirm" minlength="6" required></div>
          <button class="btn btn-primary w-100" type="submit" name="reset_btn">Reset Password</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
