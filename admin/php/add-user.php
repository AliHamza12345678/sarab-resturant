<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('users.create');

$error_msg = "";
$success_msg = "";

$roleRows = mysqli_query($conn, "SELECT id, name FROM roles WHERE name IN ('Admin','Manager','Staff') ORDER BY id");
$assignable_roles = [];
while ($r = mysqli_fetch_assoc($roleRows)) {
    $assignable_roles[$r['id']] = $r['name'];
}

if (isset($_POST['add_user_btn'])) {
    verify_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 0);

    if (!array_key_exists($role_id, $assignable_roles)) {
        $error_msg = "Please choose a valid role.";
    } elseif (empty($full_name) || empty($email) || empty($password)) {
        $error_msg = "Full name, email, and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters.";
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check, 's', $email);
        mysqli_stmt_execute($check);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if ($exists) {
            $error_msg = "An account with this email already exists.";
        } else {
            $base_username = strstr($email, '@', true) ?: 'user';
            $username = $base_username;
            $uc = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($uc, 's', $username);
            mysqli_stmt_execute($uc);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($uc))) {
                $username = $base_username . rand(100, 999);
            }
            mysqli_stmt_close($uc);

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = mysqli_prepare($conn, "INSERT INTO users (full_name, username, email, phone, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
            mysqli_stmt_bind_param($insert, 'sssssi', $full_name, $username, $email, $phone, $hashed_password, $role_id);

            if (mysqli_stmt_execute($insert)) {
                log_activity($conn, 'user_created', "Created user {$email} ({$assignable_roles[$role_id]})", null, [
                    'full_name' => $full_name, 'email' => $email, 'role_id' => $role_id,
                ]);
                mysqli_stmt_close($insert);
                header("Location: users.php?msg=created");
                exit();
            } else {
                error_log("add-user insert failed: " . mysqli_stmt_error($insert));
                $error_msg = "Could not create the account. Please try again.";
            }
            mysqli_stmt_close($insert);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add User | Admin Panel</title>
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
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
          </button>
          <span class="ms-3 fw-semibold text-muted">Add User</span>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Create a new user account</h1>
                <p class="text-muted mb-0">Start new staff with the least-privileged role that fits their job.</p>
              </div>
            </div>
            <div class="heading-actions"><a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Users</a></div>
          </div>

          <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
          <?php endif; ?>

          <section class="panel mt-3">
            <div class="panel-body p-4" style="max-width:640px;">
              <form method="POST">
                <?php csrf_field(); ?>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Full name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email address *</label>
                    <input type="email" name="email" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Role *</label>
                    <select name="role_id" class="form-select" required>
                      <?php foreach ($assignable_roles as $rid => $rname): ?>
                        <option value="<?php echo (int) $rid; ?>" <?php echo $rname === 'Staff' ? 'selected' : ''; ?>><?php echo htmlspecialchars($rname); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text">Assign role &amp; team ownership. Start with the least privileged role.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                  </div>
                </div>
                <button type="submit" name="add_user_btn" class="btn btn-primary mt-4"><i class="bi bi-person-plus"></i> Create Account</button>
              </form>
            </div>
          </section>
        </div>
      </main>
      <footer class="admin-footer"><div class="container-fluid px-3 px-lg-4"><span>Restaurant Admin Panel</span></div></footer>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
