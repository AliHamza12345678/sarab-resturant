<?php
require_once __DIR__ . '/config/customer_auth.php';

if (customer_logged_in()) { header("Location: my-account.php"); exit(); }

$error = '';
$throttle_key = 'register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (isset($_POST['register_btn'])) {
    verify_csrf();

    if (!login_attempt_allowed($throttle_key, 5, 600)) {
        $error = "Too many attempts. Please try again in a few minutes.";
    } else {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $password_confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($check, 's', $email);
        mysqli_stmt_execute($check);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if ($exists) {
            $error = "An account with this email already exists. Try logging in instead.";
        } else {
            $base_username = strstr($email, '@', true) ?: 'customer';
            $username = $base_username;
            $uc = mysqli_prepare($conn, "SELECT id FROM users WHERE username=? LIMIT 1");
            mysqli_stmt_bind_param($uc, 's', $username);
            mysqli_stmt_execute($uc);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($uc))) {
                $username = $base_username . rand(100, 999);
            }
            mysqli_stmt_close($uc);

            $customerRoleId = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM roles WHERE name='Customer'"))['id'];
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, username, email, phone, password, role_id, status) VALUES (?,?,?,?,?,?,1)");
            mysqli_stmt_bind_param($stmt, 'sssssi', $full_name, $username, $email, $phone, $hashed, $customerRoleId);
            mysqli_stmt_execute($stmt);
            $newId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            login_attempt_reset($throttle_key);
            $userRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=$newId"));
            customer_login_session($userRow);

            header("Location: my-account.php?welcome=1");
            exit();
        }
    }
    if (!empty($error)) {
        login_attempt_register_failure($throttle_key);
    }
    }
}

require_once 'header.php';
?>

<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center">
      <h2 style="font-weight: 700; margin-bottom: 10px;">Create Account</h2>
      <p style="color: #888; margin-bottom: 0;">Join us for faster checkout and order tracking</p>
   </div>
</div>

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="p-4 p-md-5 bg-white rounded shadow-sm border">
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
          <?php csrf_field(); ?>
          <div class="mb-3">
            <label class="form-label" style="font-weight:500;">Full Name *</label>
            <input type="text" name="full_name" class="form-control py-2" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">Email *</label>
              <input type="email" name="email" class="form-control py-2" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">Phone</label>
              <input type="tel" name="phone" class="form-control py-2">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">Password *</label>
              <input type="password" name="password" class="form-control py-2" minlength="6" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">Confirm Password *</label>
              <input type="password" name="password_confirm" class="form-control py-2" minlength="6" required>
            </div>
          </div>
          <button type="submit" name="register_btn" class="btn-red w-100 py-3 mt-2" style="border-radius:30px; font-weight:600;">Create Account</button>
        </form>
        <p class="text-center mt-4 mb-0 text-muted">Already have an account? <a href="login.php" style="color: var(--primary); font-weight:600;">Sign in</a></p>
      </div>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>
