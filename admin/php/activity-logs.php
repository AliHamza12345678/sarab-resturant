<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_once 'include/pagination.php';
require_permission('activity_logs.view');

$search = trim($_GET['q'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
[$page, $perPage, $offset] = paginate_params(20);

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR al.description LIKE ? OR al.ip_address LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($actionFilter !== '') {
    $where[] = 'al.action = ?';
    $params[] = $actionFilter;
    $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, (int) ceil($total / $perPage));

$listStmt = mysqli_prepare($conn, "SELECT al.*, u.full_name, u.email FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id $whereSql ORDER BY al.id DESC LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($listStmt, $types . 'ii', ...$allParams);
mysqli_stmt_execute($listStmt);
$logs = mysqli_stmt_get_result($listStmt);

$actionsList = mysqli_query($conn, "SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");

/** Render a short, human "browser" name from a raw user-agent string. */
function friendly_browser(?string $ua): string
{
    if (empty($ua)) return 'Unknown';
    if (stripos($ua, 'Edg/') !== false) return 'Edge';
    if (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') === false) return 'Chrome';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    if (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) return 'Safari';
    if (stripos($ua, 'curl') !== false) return 'curl (API/script)';
    return 'Other';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Activity Log | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Activity Log</span>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-3">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-clock-history"></i></span>
              <div><h1 class="h3 mb-1">Activity Log</h1><p class="text-muted mb-0">Full audit trail: who did what, when, from where, and what changed.</p></div>
            </div>
          </div>

          <form class="d-flex gap-2 mb-3 flex-wrap" method="GET">
            <input class="form-control form-control-sm" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search user, description, IP..." style="min-width:240px;">
            <select name="action" class="form-select form-select-sm" style="max-width:220px;" onchange="this.form.submit()">
              <option value="">All Actions</option>
              <?php while ($a = mysqli_fetch_assoc($actionsList)): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $actionFilter === $a['action'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['action']); ?></option>
              <?php endwhile; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            <?php if ($search !== '' || $actionFilter !== ''): ?><a href="activity-logs.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            <span class="badge bg-secondary align-self-center ms-auto"><?php echo $total; ?> entries</span>
          </form>

          <section class="panel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>User</th><th>Action</th><th>Description</th><th>Changes</th><th>IP</th><th>Browser</th><th>When</th></tr></thead>
                <tbody>
                  <?php if (mysqli_num_rows($logs) === 0): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No activity recorded yet.</td></tr>
                  <?php else: ?>
                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                    <tr>
                      <td>
                        <?php if ($log['full_name']): ?>
                          <strong><?php echo htmlspecialchars($log['full_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                        <?php else: ?>
                          <span class="text-muted">System / Guest</span>
                        <?php endif; ?>
                      </td>
                      <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                      <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                      <td>
                        <?php if (!empty($log['old_value']) || !empty($log['new_value'])): ?>
                          <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#diff-<?php echo $log['id']; ?>">
                            <i class="bi bi-eye"></i> View
                          </button>
                        <?php else: ?>
                          <span class="text-muted small">&mdash;</span>
                        <?php endif; ?>
                      </td>
                      <td><small class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></small></td>
                      <td><small class="text-muted"><?php echo htmlspecialchars(friendly_browser($log['user_agent'])); ?></small></td>
                      <td><small class="text-muted"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></small></td>
                    </tr>
                    <?php if (!empty($log['old_value']) || !empty($log['new_value'])): ?>
                    <tr class="collapse" id="diff-<?php echo $log['id']; ?>">
                      <td colspan="7" class="bg-light">
                        <div class="row g-3 p-2">
                          <?php if (!empty($log['old_value'])): ?>
                          <div class="col-md-6">
                            <p class="small fw-semibold text-danger mb-1"><i class="bi bi-dash-circle"></i> Old Value</p>
                            <pre class="small bg-white border rounded p-2 mb-0" style="white-space:pre-wrap;"><?php echo htmlspecialchars(json_encode(json_decode($log['old_value']), JSON_PRETTY_PRINT)); ?></pre>
                          </div>
                          <?php endif; ?>
                          <?php if (!empty($log['new_value'])): ?>
                          <div class="col-md-6">
                            <p class="small fw-semibold text-success mb-1"><i class="bi bi-plus-circle"></i> New Value</p>
                            <pre class="small bg-white border rounded p-2 mb-0" style="white-space:pre-wrap;"><?php echo htmlspecialchars(json_encode(json_decode($log['new_value']), JSON_PRETTY_PRINT)); ?></pre>
                          </div>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    <?php endif; ?>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center p-2">
              <p class="text-muted small mb-0">Showing <?php echo $total === 0 ? 0 : ($offset + 1); ?>-<?php echo min($offset + $perPage, $total); ?> of <?php echo $total; ?></p>
              <?php echo render_pagination($page, $totalPages); ?>
            </div>
          </section>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
