<?php
require_once __DIR__ . '/config/customer_auth.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';
$error = '';
$success = '';

$valid = false;
if (!empty($token) && !empty($email)) {
    $stmt = mysqli_prepare($conn, "SELECT id FROM password_resets WHERE email=? AND token=? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ss', $email, $token);
    mysqli_stmt_execute($stmt);
    $valid = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if ($valid && isset($_POST['reset_btn'])) {
    verify_csrf();
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['password_confirm'] ?? '');

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $u = mysqli_prepare($conn, "UPDATE users SET password=? WHERE email=?");
        mysqli_stmt_bind_param($u, 'ss', $hashed, $email);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        $d = mysqli_prepare($conn, "DELETE FROM password_resets WHERE email=?");
        mysqli_stmt_bind_param($d, 's', $email);
        mysqli_stmt_execute($d);
        mysqli_stmt_close($d);

        $success = "Password reset successfully. You can now log in.";
        $valid = false;
    }
}

require_once 'header.php';
?>

<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center"><h2 style="font-weight: 700; margin-bottom: 10px;">Reset Password</h2></div>
</div>

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="p-4 p-md-5 bg-white rounded shadow-sm border">
        <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <p class="text-center mb-0"><a href="login.php" class="btn-red py-3 px-5" style="border-radius:30px;">Go to Login</a></p>
        <?php elseif (!$valid): ?>
          <div class="alert alert-danger">This reset link is invalid or has expired.</div>
          <p class="text-center mb-0"><a href="forgot-password.php" style="color: var(--primary);">Request a new link</a></p>
        <?php else: ?>
          <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
          <form method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <div class="mb-3">
              <label class="form-label" style="font-weight:500;">New Password</label>
              <input type="password" name="password" class="form-control py-2" minlength="6" required>
            </div>
            <div class="mb-3">
              <label class="form-label" style="font-weight:500;">Confirm Password</label>
              <input type="password" name="password_confirm" class="form-control py-2" minlength="6" required>
            </div>
            <button type="submit" name="reset_btn" class="btn-red w-100 py-3" style="border-radius:30px; font-weight:600;">Reset Password</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>
