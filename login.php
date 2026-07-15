<?php
require_once __DIR__ . '/config/customer_auth.php';

if (customer_logged_in()) { header("Location: my-account.php"); exit(); }

// Silent remember-me restore
if (attempt_customer_remember_login($conn)) {
    header("Location: my-account.php");
    exit();
}

$error = '';
$throttle_key = 'customer_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'my-account.php';

if (isset($_POST['login_btn'])) {
    verify_csrf();

    if (!login_attempt_allowed($throttle_key)) {
        $error = "Too many attempts. Please try again in a few minutes.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember_me']);

        $stmt = mysqli_prepare($conn, "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id
            WHERE u.email=? AND u.status=1 AND r.name='Customer' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            login_attempt_reset($throttle_key);
            customer_login_session($user);
            $u = mysqli_prepare($conn, "UPDATE users SET last_login_at=NOW() WHERE id=?");
            mysqli_stmt_bind_param($u, 'i', $user['id']);
            mysqli_stmt_execute($u);
            mysqli_stmt_close($u);
            if ($remember) issue_customer_remember_token($conn, (int) $user['id']);
            header("Location: " . $redirect);
            exit();
        } else {
            login_attempt_register_failure($throttle_key);
            $error = "Invalid email or password.";
        }
    }
}

require_once 'header.php';
?>

<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center">
      <h2 style="font-weight: 700; margin-bottom: 10px;">Sign In</h2>
      <p style="color: #888; margin-bottom: 0;">Access your orders, reservations, and profile</p>
   </div>
</div>

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="p-4 p-md-5 bg-white rounded shadow-sm border">
        <?php if (isset($_GET['registered'])): ?><div class="alert alert-success">Account created — please sign in.</div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
          <?php csrf_field(); ?>
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
          <div class="mb-3">
            <label class="form-label" style="font-weight:500;">Email</label>
            <input type="email" name="email" class="form-control py-2" required>
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <label class="form-label" style="font-weight:500;">Password</label>
              <a href="forgot-password.php" class="small" style="color: var(--primary);">Forgot?</a>
            </div>
            <input type="password" name="password" class="form-control py-2" required>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMeCustomer">
            <label class="form-check-label" for="rememberMeCustomer">Remember me</label>
          </div>
          <button type="submit" name="login_btn" class="btn-red w-100 py-3" style="border-radius:30px; font-weight:600;">Sign In</button>
        </form>
        <p class="text-center mt-4 mb-0 text-muted">New here? <a href="register.php" style="color: var(--primary); font-weight:600;">Create an account</a></p>
      </div>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>
