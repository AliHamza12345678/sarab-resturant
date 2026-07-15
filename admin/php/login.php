<?php
require_once '../../config/db.php';
require_once '../../config/security.php'; // starts a hardened session
require_once '../../config/rbac.php';

// Redirect if already logged in
if (isset($_SESSION['admin_user'])) {
    header("Location: index.php");
    exit();
}

// Not logged in via session? Try the "remember me" cookie first.
if (attempt_remember_me_login($conn)) {
    header("Location: index.php");
    exit();
}

$error_msg = "";
$throttle_key = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (isset($_POST['login_btn'])) {
    verify_csrf();

    if (!login_attempt_allowed($throttle_key)) {
        $error_msg = "Too many failed attempts. Please try again in a few minutes.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember_me = isset($_POST['remember_me']);

        if (!empty($email) && !empty($password)) {
            $stmt = mysqli_prepare($conn, "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.email = ? AND u.status = 1 AND r.name IN ('Admin','Manager','Staff') LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user['password'])) {
                login_attempt_reset($throttle_key);
                regenerate_session(); // prevent session fixation
                unset($user['password']); // never keep the password hash in session
                $_SESSION['admin_user'] = $user;
                $_SESSION['admin_permissions'] = load_role_permissions($conn, (int) $user['role_id']);
                $_SESSION['admin_last_activity'] = time();
                log_activity($conn, 'login_success', "Admin login: {$user['email']}");

                // Track last login time
                $u = mysqli_prepare($conn, "UPDATE users SET last_login_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($u, 'i', $user['id']);
                mysqli_stmt_execute($u);
                mysqli_stmt_close($u);

                if ($remember_me) {
                    issue_remember_me_token($conn, (int) $user['id']);
                }

                header("Location: index.php");
                exit();
            } else {
                login_attempt_register_failure($throttle_key);
                log_activity($conn, 'login_failed', "Failed admin login attempt for: {$email}");
                $error_msg = "Invalid email or password.";
            }
        } else {
            $error_msg = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD authentication page">
  <title>Login | adminHMD</title>

  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-body">
  <button class="icon-button theme-toggle auth-theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
    <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
  </button>
  <main class="auth-page">
    <section class="auth-card">
      <a class="auth-brand" href="index.php"><span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span><span><strong>adminHMD</strong><small>Sign in to your admin workspace.</small></span></a>
      <div class="auth-visual"><img src="../assets/images/png/dasher-ui-bootstrap-5.jpg" alt="adminHMD dashboard interface"></div>
      
      <form class="needs-validation" method="POST" action="" novalidate>
        <?php csrf_field(); ?>
        <div class="mb-4">
          <p class="eyebrow mb-1">Secure Access</p>
          <h1 class="h3 mb-1">Login</h1>
          <p class="text-muted mb-0">Sign in to your admin workspace.</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="loginEmail">Email address</label>
          <input class="form-control" id="loginEmail" type="email" name="email" required>
          <div class="invalid-feedback">Enter a valid email.</div>
        </div>
        
        <div class="mb-3">
          <div class="d-flex justify-content-between">
            <label class="form-label" for="loginPassword">Password</label>
            <a class="small fw-semibold" href="forgot-password.php">Forgot?</a>
          </div>
          <input class="form-control" id="loginPassword" type="password" name="password" minlength="6" required>
          <div class="invalid-feedback">Password must be at least 6 characters.</div>
        </div>
        
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
          <label class="form-check-label" for="rememberMe">Remember me</label>
        </div>
        
        <button class="btn btn-primary w-100" type="submit" name="login_btn"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign In</button>
      </form>
      
      <div class="auth-footer">New here? <a href="register.php">Create an account</a></div>
    </section>
  </main>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
