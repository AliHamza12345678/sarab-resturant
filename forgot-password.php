<?php
require_once __DIR__ . '/config/customer_auth.php';

if (customer_logged_in()) { header("Location: my-account.php"); exit(); }

$info = '';
$reset_link = '';
$throttle_key = 'forgot_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (isset($_POST['forgot_btn'])) {
    verify_csrf();

    if (!login_attempt_allowed($throttle_key, 5, 600)) {
        $info = "Too many requests. Please try again in a few minutes.";
    } else {
    login_attempt_register_failure($throttle_key); // counts every request, success or not — this endpoint must stay rate-limited either way
    $email = trim($_POST['email'] ?? '');

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = mysqli_prepare($conn, "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=? AND u.status=1 AND r.name='Customer' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $ins = mysqli_prepare($conn, "INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)");
            mysqli_stmt_bind_param($ins, 'sss', $email, $token, $expires);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            require_once __DIR__ . '/config/mailer.php';
            $resetUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
            try {
                $settings = get_site_settings($conn);
                send_email($email, 'Reset your password - ' . $settings['site_name'], email_template($settings['site_name'], 'Password Reset', "<p>Click below to reset your password (valid for 1 hour):</p><p><a href=\"{$resetUrl}\">Reset Password</a></p>"), $settings['email'] ?? null, $settings['site_name']);
            } catch (\Throwable $e) { error_log('forgot-password email failed: ' . $e->getMessage()); }

            $reset_link = 'reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
        }
    }
    $info = "If an account exists for that email, a password reset link has been sent.";
    }
}

require_once 'header.php';
?>

<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center">
      <h2 style="font-weight: 700; margin-bottom: 10px;">Forgot Password</h2>
   </div>
</div>

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="p-4 p-md-5 bg-white rounded shadow-sm border">
        <?php if (!empty($info)): ?><div class="alert alert-info"><?php echo htmlspecialchars($info); ?></div><?php endif; ?>
        <?php if (!empty($reset_link)): ?>
          <div class="alert alert-warning">Email delivery isn't configured on this server yet — here's your reset link: <a href="<?php echo htmlspecialchars($reset_link); ?>"><?php echo htmlspecialchars($reset_link); ?></a></div>
        <?php endif; ?>
        <form method="POST">
          <?php csrf_field(); ?>
          <div class="mb-3">
            <label class="form-label" style="font-weight:500;">Email address</label>
            <input type="email" name="email" class="form-control py-2" required>
          </div>
          <button type="submit" name="forgot_btn" class="btn-red w-100 py-3" style="border-radius:30px; font-weight:600;">Send Reset Link</button>
        </form>
        <p class="text-center mt-4 mb-0 text-muted"><a href="login.php" style="color: var(--primary);">Back to login</a></p>
      </div>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>
