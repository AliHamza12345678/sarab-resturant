<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('users.edit');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: users.php"); exit(); }

$error_msg = "";
$success_msg = "";

$roleRows = mysqli_query($conn, "SELECT id, name FROM roles WHERE name IN ('Admin','Manager','Staff') ORDER BY id");
$assignable_roles = [];
while ($r = mysqli_fetch_assoc($roleRows)) {
    $assignable_roles[$r['id']] = $r['name'];
}

if (isset($_POST['update_user_btn'])) {
    verify_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 0);
    $status = intval($_POST['status'] ?? 1);
    $new_password = trim($_POST['new_password'] ?? '');

    $isSelf = ($id === (int) $_SESSION['admin_user']['id']);
    $oldUserState = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, email, phone, role_id, status FROM users WHERE id=" . (int) $id));

    if (empty($full_name) || empty($email) || !array_key_exists($role_id, $assignable_roles)) {
        $error_msg = "Please fill all required fields correctly.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } elseif ($isSelf && $status === 0) {
        $error_msg = "You cannot suspend your own account.";
    } else {
        // Email uniqueness (excluding self)
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($check, 'si', $email, $id);
        mysqli_stmt_execute($check);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if ($exists) {
            $error_msg = "Another account already uses this email.";
        } else {
            $passwordChanged = false;
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error_msg = "New password must be at least 6 characters.";
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, email=?, phone=?, role_id=?, status=?, password=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'sssiisi', $full_name, $email, $phone, $role_id, $status, $hashed, $id);
                    $passwordChanged = true;
                }
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, email=?, phone=?, role_id=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssiii', $full_name, $email, $phone, $role_id, $status, $id);
            }

            if (empty($error_msg)) {
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $description = "Updated user #$id" . ($passwordChanged ? ' (password changed)' : '');
                log_field_changes($conn, 'user_updated', $description, $oldUserState ?: [], [
                    'full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'role_id' => $role_id, 'status' => $status,
                ]);

                // Keep session in sync if the admin edited their own profile
                if ($isSelf) {
                    $_SESSION['admin_user']['full_name'] = $full_name;
                    $_SESSION['admin_user']['email'] = $email;
                }

                header("Location: users.php?msg=updated");
                exit();
            }
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) { header("Location: users.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit User | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Edit User</span>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Edit <?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p class="text-muted mb-0">Update account details, role, and status.</p>
              </div>
            </div>
            <div class="heading-actions"><a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Users</a></div>
          </div>

          <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

          <section class="panel mt-3">
            <div class="panel-body p-4" style="max-width:640px;">
              <form method="POST">
                <?php csrf_field(); ?>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Full name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email address *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Role *</label>
                    <select name="role_id" class="form-select" required <?php echo ((int) $user['id'] === (int) $_SESSION['admin_user']['id']) ? 'disabled' : ''; ?>>
                      <?php foreach ($assignable_roles as $rid => $rname): ?>
                        <option value="<?php echo (int) $rid; ?>" <?php echo ((int) $rid === (int) $user['role_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($rname); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ((int) $user['id'] === (int) $_SESSION['admin_user']['id']): ?>
                      <input type="hidden" name="role_id" value="<?php echo (int) $user['role_id']; ?>">
                      <div class="form-text">You cannot change your own role.</div>
                    <?php endif; ?>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" <?php echo ((int) $user['id'] === (int) $_SESSION['admin_user']['id']) ? 'disabled' : ''; ?>>
                      <option value="1" <?php echo $user['status'] ? 'selected' : ''; ?>>Active</option>
                      <option value="0" <?php echo !$user['status'] ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                    <?php if ((int) $user['id'] === (int) $_SESSION['admin_user']['id']): ?>
                      <input type="hidden" name="status" value="1">
                    <?php endif; ?>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">New password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Leave blank to keep current password">
                  </div>
                </div>
                <button type="submit" name="update_user_btn" class="btn btn-primary mt-4"><i class="bi bi-save"></i> Save Changes</button>
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
