<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('users.view');

$msg = '';

// Toggle status (activate/suspend) - POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status_id'])) {
    require_permission('users.edit');
    verify_csrf();
    $id = intval($_POST['toggle_status_id']);
    if ($id === (int) $_SESSION['admin_user']['id']) {
        $msg = '<div class="alert alert-warning">You cannot change your own status.</div>';
    } else {
        $oldUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, status FROM users WHERE id=" . (int) $id));
        $stmt = mysqli_prepare($conn, "UPDATE users SET status = 1 - status WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($oldUser) {
            log_activity($conn, 'user_status_toggled', "Toggled status for user #$id", ['status' => $oldUser['status']], ['status' => 1 - $oldUser['status']]);
        }
        header("Location: users.php" . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : '')); exit;
    }
}

// Delete user - POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    require_permission('users.delete');
    verify_csrf();
    $id = intval($_POST['delete_id']);

    if ($id === (int) $_SESSION['admin_user']['id']) {
        $msg = '<div class="alert alert-warning">You cannot delete your own account.</div>';
    } else {
        // Don't allow deleting the last remaining Admin
        $adminRoleId = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM roles WHERE name='Admin'"))['id'];
        $targetRole = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role_id FROM users WHERE id=" . intval($id)));
        $adminCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role_id=" . intval($adminRoleId)))['c'];

        if ($targetRole && (int) $targetRole['role_id'] === (int) $adminRoleId && $adminCount <= 1) {
            $msg = '<div class="alert alert-warning">Cannot delete the last remaining Admin account.</div>';
        } else {
            $oldUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, full_name, username, email, phone, role_id, status FROM users WHERE id=" . (int) $id));
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($oldUser) log_activity($conn, 'user_deleted', "Deleted user #$id", $oldUser, null);
            header("Location: users.php?msg=deleted"); exit;
        }
    }
}

// Search + pagination
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereSql = '';
$params = [];
$types = '';
if ($search !== '') {
    $whereSql = "WHERE (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}

// Total count for pagination
$countSql = "SELECT COUNT(*) c FROM users u $whereSql";
$countStmt = mysqli_prepare($conn, $countSql);
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalUsers = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
mysqli_stmt_close($countStmt);
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch page of users
$listSql = "SELECT u.id, u.full_name, u.username, u.email, u.status, u.created_at, u.last_login_at, r.name AS role_name
            FROM users u JOIN roles r ON r.id = u.role_id
            $whereSql
            ORDER BY u.id DESC LIMIT ? OFFSET ?";
$listStmt = mysqli_prepare($conn, $listSql);
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
mysqli_stmt_bind_param($listStmt, $allTypes, ...$allParams);
mysqli_stmt_execute($listStmt);
$users = mysqli_stmt_get_result($listStmt);

// Live stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    COUNT(*) AS total,
    SUM(status = 1) AS active,
    SUM(status = 0) AS suspended
    FROM users"));
$adminCountStat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='Admin'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Admin dashboard">
  <title>Users | Admin Panel</title>

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
          <span class="ms-3 fw-semibold text-muted">User Management</span>
          <div class="navbar-actions ms-auto">
            <span class="me-3 text-muted small"><?php echo htmlspecialchars($_SESSION['admin_user']['full_name']); ?> (<?php echo htmlspecialchars($_SESSION['admin_user']['role_name']); ?>)</span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Users</h1>
                <p class="text-muted mb-0">Review accounts, roles, and account status.</p>
              </div>
            </div>
            <div class="heading-actions">
              <?php if (user_has_permission('users.create')): ?>
                <a class="btn btn-primary btn-sm" href="add-user.php"><i class="bi bi-person-plus" aria-hidden="true"></i> Add User</a>
              <?php endif; ?>
            </div>
          </div>

          <?php echo $msg; ?>
          <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
            <div class="alert alert-success alert-dismissible fade show">User created successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show">User updated successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">User deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          <?php endif; ?>

          <section class="row g-3 mt-1" aria-label="User summary">
            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-primary">
                <div class="metric-top"><span class="metric-label">Total Users</span><span class="metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span></div>
                <div class="metric-value"><?php echo (int) $stats['total']; ?></div>
              </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-success">
                <div class="metric-top"><span class="metric-label">Active</span><span class="metric-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span></div>
                <div class="metric-value"><?php echo (int) $stats['active']; ?></div>
              </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-danger">
                <div class="metric-top"><span class="metric-label">Suspended</span><span class="metric-icon"><i class="bi bi-slash-circle" aria-hidden="true"></i></span></div>
                <div class="metric-value"><?php echo (int) $stats['suspended']; ?></div>
              </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-warning">
                <div class="metric-top"><span class="metric-label">Admins</span><span class="metric-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span></div>
                <div class="metric-value"><?php echo (int) $adminCountStat; ?></div>
              </article>
            </div>
          </section>

          <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>User List</span></h2>
                <p class="text-muted mb-0">Search, review, and manage accounts.</p>
              </div>
              <form class="d-flex gap-2" method="GET" action="users.php">
                <input class="form-control form-control-sm" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, username" style="min-width:220px;">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                <?php if ($search !== ''): ?><a href="users.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
              </form>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Last Login</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                  <?php if (mysqli_num_rows($users) === 0): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
                  <?php else: ?>
                    <?php while ($u = mysqli_fetch_assoc($users)): ?>
                      <tr>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <img class="avatar-img avatar-sm" src="../assets/images/avatar/avatar.jpg" alt="<?php echo htmlspecialchars($u['full_name']); ?>">
                            <div>
                              <p class="fw-semibold mb-0"><?php echo htmlspecialchars($u['full_name']); ?><?php if ((int)$u['id'] === (int)$_SESSION['admin_user']['id']) echo ' <span class="badge bg-info text-dark">You</span>'; ?></p>
                              <p class="text-muted small mb-0"><?php echo htmlspecialchars($u['email']); ?></p>
                            </div>
                          </div>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['role_name']); ?></span></td>
                        <td>
                          <?php if ($u['status']): ?>
                            <span class="badge text-bg-success">Active</span>
                          <?php else: ?>
                            <span class="badge text-bg-secondary">Suspended</span>
                          <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></small></td>
                        <td><small class="text-muted"><?php echo $u['last_login_at'] ? date('M d, Y H:i', strtotime($u['last_login_at'])) : 'Never'; ?></small></td>
                        <td class="text-end">
                          <a href="user-details.php?id=<?php echo (int) $u['id']; ?>" class="btn btn-sm btn-outline-secondary me-1" title="View Details"><i class="bi bi-eye"></i></a>
                          <?php if (user_has_permission('users.edit')): ?>
                            <a href="user-edit.php?id=<?php echo (int) $u['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php if ((int) $u['id'] !== (int) $_SESSION['admin_user']['id']): ?>
                              <form method="POST" class="d-inline" action="users.php?q=<?php echo urlencode($search); ?>">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="toggle_status_id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary me-1" title="<?php echo $u['status'] ? 'Suspend' : 'Activate'; ?>">
                                  <i class="bi <?php echo $u['status'] ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                </button>
                              </form>
                            <?php endif; ?>
                          <?php endif; ?>
                          <?php if (user_has_permission('users.delete') && (int) $u['id'] !== (int) $_SESSION['admin_user']['id']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                              <?php csrf_field(); ?>
                              <input type="hidden" name="delete_id" value="<?php echo (int) $u['id']; ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-3">
              <p class="text-muted small mb-0">Showing <?php echo $totalUsers === 0 ? 0 : ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> users</p>
              <?php if ($totalPages > 1): ?>
              <nav aria-label="Users pagination">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="users.php?page=<?php echo $page-1; ?>&q=<?php echo urlencode($search); ?>">Previous</a></li>
                  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>"><a class="page-link" href="users.php?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>"><?php echo $p; ?></a></li>
                  <?php endfor; ?>
                  <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><a class="page-link" href="users.php?page=<?php echo $page+1; ?>&q=<?php echo urlencode($search); ?>">Next</a></li>
                </ul>
              </nav>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </main>

      <footer class="admin-footer">
        <div class="container-fluid px-3 px-lg-4">
          <span>Restaurant Admin Panel</span>
        </div>
      </footer>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
