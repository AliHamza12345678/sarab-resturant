<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('users.view');

$id = intval($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) { header("Location: users.php"); exit(); }

$logStmt = mysqli_prepare($conn, "SELECT * FROM activity_logs WHERE user_id=? ORDER BY id DESC LIMIT 20");
mysqli_stmt_bind_param($logStmt, 'i', $id);
mysqli_stmt_execute($logStmt);
$logs = mysqli_stmt_get_result($logStmt);

$orderCount = 0;
if ($user['role_name'] === 'Customer') {
    $orderCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE user_id=" . (int) $id))['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($user['full_name']); ?> | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">User Details</span>
          <div class="navbar-actions ms-auto"><a href="users.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Users</a></div>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <div class="panel text-center p-4">
                <img class="avatar-img avatar-lg mx-auto mb-3" src="../assets/images/avatar/avatar.jpg" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                <h2 class="h5 mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($user['role_name']); ?></span>
                <p class="mb-1"><?php echo $user['status'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Suspended</span>'; ?></p>
                <hr>
                <p class="text-muted small mb-1"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?></p>
                <p class="text-muted small mb-1"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></p>
                <p class="text-muted small mb-1"><i class="bi bi-calendar3 me-1"></i>Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                <p class="text-muted small mb-0"><i class="bi bi-clock-history me-1"></i>Last login: <?php echo $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : 'Never'; ?></p>
                <?php if ($user['role_name'] === 'Customer'): ?>
                  <hr><p class="mb-0"><strong><?php echo (int) $orderCount; ?></strong> orders placed</p>
                <?php endif; ?>
                <?php if (user_has_permission('users.edit')): ?>
                  <a href="user-edit.php?id=<?php echo (int) $user['id']; ?>" class="btn btn-sm btn-outline-primary mt-3"><i class="bi bi-pencil"></i> Edit User</a>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-12 col-lg-8">
              <div class="panel p-4">
                <h2 class="h6 mb-3"><i class="bi bi-clock-history me-1"></i> Recent Activity</h2>
                <?php if (mysqli_num_rows($logs) === 0): ?>
                  <p class="text-muted mb-0">No recorded activity for this user yet.</p>
                <?php else: ?>
                  <ul class="list-unstyled mb-0">
                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                      <li class="d-flex justify-content-between border-bottom py-2">
                        <span><?php echo htmlspecialchars($log['description'] ?: $log['action']); ?></span>
                        <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></small>
                      </li>
                    <?php endwhile; ?>
                  </ul>
                <?php endif; ?>
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
