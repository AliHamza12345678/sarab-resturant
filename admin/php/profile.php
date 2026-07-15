<?php
require_once 'include/auth.php';
require_once '../../config/db.php';

$id = (int) $_SESSION['admin_user']['id'];
$error_msg = '';
$success_msg = '';

if (isset($_POST['update_profile_btn'])) {
    verify_csrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error_msg = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($check, 'si', $email, $id);
        mysqli_stmt_execute($check);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if ($exists) {
            $error_msg = "Another account already uses this email.";
        } elseif (!empty($new_password) && empty($current_password)) {
            $error_msg = "Enter your current password to set a new one.";
        } else {
            $passwordOk = true;
            if (!empty($new_password)) {
                $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id=" . (int) $id));
                if (!password_verify($current_password, $row['password'])) {
                    $passwordOk = false;
                    $error_msg = "Current password is incorrect.";
                } elseif (strlen($new_password) < 6) {
                    $passwordOk = false;
                    $error_msg = "New password must be at least 6 characters.";
                }
            }

            if ($passwordOk) {
                if (!empty($new_password)) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, email=?, phone=?, password=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'ssssi', $full_name, $email, $phone, $hashed, $id);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'sssi', $full_name, $email, $phone, $id);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                log_activity($conn, 'profile_updated', "Updated own profile");
                $_SESSION['admin_user']['full_name'] = $full_name;
                $_SESSION['admin_user']['email'] = $email;
                $success_msg = "Profile updated successfully.";
            }
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile | Admin Panel</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>
    <?php require_once 'include/sidebar.php'; ?>
    <div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle><span></span><span></span><span></span></button>
          <span class="ms-3 fw-semibold text-muted">My Profile</span>
          <div class="navbar-actions ms-auto"><a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-3">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-person-circle"></i></span>
              <div><h1 class="h3 mb-1">My Profile</h1><p class="text-muted mb-0">Manage your personal account details.</p></div>
            </div>
          </div>

          <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
          <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>

          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <div class="panel text-center p-4">
                <img class="avatar-img avatar-lg mx-auto mb-3" src="../assets/images/avatar/avatar.jpg" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                <h2 class="h5 mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($user['role_name']); ?></span>
                <p class="text-muted small mb-0">Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                <p class="text-muted small mb-0">Last login: <?php echo $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : 'This session'; ?></p>
              </div>
            </div>
            <div class="col-12 col-lg-8">
              <div class="panel p-4">
                <h2 class="h6 mb-3">Account Details</h2>
                <form method="POST">
                  <?php csrf_field(); ?>
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Full name *</label><input class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>
                  </div>
                  <hr class="my-4">
                  <h2 class="h6 mb-3">Change Password <small class="text-muted fw-normal">(leave blank to keep current password)</small></h2>
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password"></div>
                    <div class="col-md-6"><label class="form-label">New password</label><input class="form-control" type="password" name="new_password" minlength="6"></div>
                  </div>
                  <button type="submit" name="update_profile_btn" class="btn btn-primary mt-4"><i class="bi bi-save"></i> Save Changes</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
