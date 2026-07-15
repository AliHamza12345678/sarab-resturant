<?php
require_once '../../config/db.php';
require_once '../../config/security.php';

if (isset($_SESSION['admin_user'])) { header("Location: index.php"); exit(); }

$info_msg = "";
$reset_link = ""; // NOTE: real email delivery is wired up in Phase 8. Until then we
                   // show the (real, DB-backed) reset link directly so the flow works end to end.
$throttle_key = 'admin_forgot_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (isset($_POST['forgot_btn'])) {
    verify_csrf();

    if (!login_attempt_allowed($throttle_key, 5, 600)) {
        $info_msg = "Too many requests. Please try again in a few minutes.";
    } else {
    login_attempt_register_failure($throttle_key);
    $email = trim($_POST['email'] ?? '');

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND status = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $ins = mysqli_prepare($conn, "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($ins, 'sss', $email, $token, $expires);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            $reset_link = "reset-password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
        }
    }
    // Always show the same generic message whether or not the account exists (avoid user enumeration)
    $info_msg = "If an account exists for that email, a password reset link has been generated.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | Admin Panel</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-card">
      <a class="auth-brand" href="login.php"><span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span><span><strong>Admin Panel</strong><small>Get a reset link for your account.</small></span></a>
      <form class="needs-validation" method="POST" novalidate>
        <?php csrf_field(); ?>
        <div class="mb-4">
          <p class="eyebrow mb-1">Secure Access</p>
          <h1 class="h3 mb-1">Forgot Password</h1>
          <p class="text-muted mb-0">Get a reset link for your account.</p>
        </div>
        <?php if (!empty($info_msg)): ?><div class="alert alert-info"><?php echo htmlspecialchars($info_msg); ?></div><?php endif; ?>
        <?php if (!empty($reset_link)): ?>
          <div class="alert alert-warning">
            Email delivery isn't configured yet — here is your reset link for now:<br>
            <a href="<?php echo htmlspecialchars($reset_link); ?>"><?php echo htmlspecialchars($reset_link); ?></a>
          </div>
        <?php endif; ?>
        <div class="mb-4"><label class="form-label" for="forgotEmail">Email address</label><input class="form-control" id="forgotEmail" name="email" type="email" required><div class="invalid-feedback">Enter a valid email.</div></div>
        <button class="btn btn-primary w-100" type="submit" name="forgot_btn"><i class="bi bi-envelope-arrow-up" aria-hidden="true"></i> Send Reset Link</button>
      </form>
      <div class="auth-footer">Remembered it? <a href="login.php">Back to login</a></div>
    </section>
  </main>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
