<?php
require_once __DIR__ . '/config/customer_auth.php';
require_customer_login();

$id = (int) $_SESSION['customer_user']['id'];
$error = '';
$success = '';

if (isset($_POST['update_profile_btn'])) {
    verify_csrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    if (empty($full_name)) {
        $error = "Name is required.";
    } elseif (!empty($new_password) && empty($current_password)) {
        $error = "Enter your current password to set a new one.";
    } else {
        $passwordOk = true;
        if (!empty($new_password)) {
            $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id=$id"));
            if (!password_verify($current_password, $row['password'])) {
                $passwordOk = false;
                $error = "Current password is incorrect.";
            } elseif (strlen($new_password) < 6) {
                $passwordOk = false;
                $error = "New password must be at least 6 characters.";
            }
        }

        if ($passwordOk) {
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, phone=?, password=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssi', $full_name, $phone, $hashed, $id);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, phone=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssi', $full_name, $phone, $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['customer_user']['full_name'] = $full_name;
            $_SESSION['customer_user']['phone'] = $phone;
            $success = "Profile updated successfully.";
        }
    }
}

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$id"));
$orderCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE user_id=$id"))['c'];
$reservationCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM reservations WHERE user_id=$id"))['c'];

require_once 'header.php';
?>

<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center">
      <h2 style="font-weight: 700; margin-bottom: 10px;">My Account</h2>
      <p style="color: #888; margin-bottom: 0;">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?></p>
   </div>
</div>

<div class="container my-5">
  <?php if (isset($_GET['welcome'])): ?><div class="alert alert-success">Welcome! Your account has been created.</div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="p-4 bg-white rounded shadow-sm border text-center mb-4">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;margin:0 auto 16px;"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
        <h5 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
        <p class="text-muted small mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
      </div>
      <div class="row g-2 mb-4">
        <div class="col-6">
          <a href="my-orders.php" class="d-block p-3 bg-white rounded shadow-sm border text-center text-decoration-none">
            <div style="font-size:1.5rem;font-weight:700;color:var(--primary);"><?php echo (int) $orderCount; ?></div>
            <div class="small text-muted">My Orders</div>
          </a>
        </div>
        <div class="col-6">
          <a href="my-reservations.php" class="d-block p-3 bg-white rounded shadow-sm border text-center text-decoration-none">
            <div style="font-size:1.5rem;font-weight:700;color:var(--primary);"><?php echo (int) $reservationCount; ?></div>
            <div class="small text-muted">Reservations</div>
          </a>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="p-4 p-md-5 bg-white rounded shadow-sm border">
        <h5 class="mb-4">Edit Profile</h5>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="POST">
          <?php csrf_field(); ?>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">Full Name *</label>
              <input type="text" name="full_name" class="form-control py-2" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">Phone</label>
              <input type="tel" name="phone" class="form-control py-2" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-weight:500;">Email</label>
            <input type="email" class="form-control py-2" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
            <small class="text-muted">Contact support to change your email.</small>
          </div>
          <hr class="my-4">
          <h6 class="mb-3">Change Password <small class="text-muted fw-normal">(leave blank to keep current)</small></h6>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">Current Password</label>
              <input type="password" name="current_password" class="form-control py-2">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" style="font-weight:500;">New Password</label>
              <input type="password" name="new_password" class="form-control py-2" minlength="6">
            </div>
          </div>
          <button type="submit" name="update_profile_btn" class="btn-red py-3 px-5 mt-2" style="border-radius:30px; font-weight:600;">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>
