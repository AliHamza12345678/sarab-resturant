<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('reservations.view');

$valid_statuses = ['Pending', 'Confirmed', 'Cancelled'];

// Status update (POST + CSRF, was a GET link before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_change'], $_POST['id'])) {
    verify_csrf();
    require_permission('reservations.edit');
    $id = intval($_POST['id']);
    $status = $_POST['status_change'];
    if (in_array($status, $valid_statuses, true) && $id > 0) {
        $oldRes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, status FROM reservations WHERE id=" . (int) $id));
        $stmt = mysqli_prepare($conn, "UPDATE reservations SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($oldRes) {
            log_activity($conn, 'reservation_status_change', "Changed reservation #$id status", ['status' => $oldRes['status']], ['status' => $status]);
        }
    }
    header("Location: reservations.php?msg=updated"); exit;
}

// Delete (POST + CSRF, was a GET link before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    verify_csrf();
    require_permission('reservations.delete');
    $id = intval($_POST['delete_id']);
    $oldRes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM reservations WHERE id=" . (int) $id));
    $stmt = mysqli_prepare($conn, "DELETE FROM reservations WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($oldRes) {
        log_activity($conn, 'reservation_deleted', "Deleted reservation #$id", $oldRes, null);
    }
    header("Location: reservations.php?msg=deleted"); exit;
}

require_once 'include/pagination.php';

$allowed_filters = array_merge(['all'], $valid_statuses);
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters, true)) ? $_GET['filter'] : 'all';
$search = trim($_GET['q'] ?? '');
[$sortCol, $sortDir] = sort_params(['id', 'full_name', 'reservation_date', 'guests', 'status'], 'id');
[$page, $perPage, $offset] = paginate_params(15);

$where = [];
$params = [];
$types = '';
if ($filter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $filter;
    $types .= 's';
}
if ($search !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM reservations $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, (int) ceil($total / $perPage));

$listStmt = mysqli_prepare($conn, "SELECT * FROM reservations $whereSql ORDER BY $sortCol $sortDir LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($listStmt, $types . 'ii', ...$allParams);
mysqli_stmt_execute($listStmt);
$result = mysqli_stmt_get_result($listStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reservations | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Reservations</span>
          <div class="navbar-actions ms-auto">
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-4">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-calendar-event"></i></span>
              <div>
                <h1 class="h3 mb-1">Reservations</h1>
                <p class="text-muted mb-0">Manage all table booking requests</p>
              </div>
            </div>
            <?php if (user_has_permission('reservations.edit')): ?>
            <div class="heading-actions"><a href="reservation-edit.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Reservation</a></div>
            <?php endif; ?>
          </div>

          <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
              <?php echo $_GET['msg'] === 'deleted' ? 'Reservation deleted.' : 'Status updated.'; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <!-- Filter tabs -->
          <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
            <?php foreach (['all','Pending','Confirmed','Cancelled'] as $f): ?>
              <a href="<?php echo htmlspecialchars(qs(['filter' => $f, 'page' => 1])); ?>" class="btn btn-sm <?php echo ($filter===$f) ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo htmlspecialchars($f); ?></a>
            <?php endforeach; ?>
            <form class="d-flex gap-2 ms-auto" method="GET">
              <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
              <input class="form-control form-control-sm" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, phone" style="min-width:220px;">
              <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
              <?php if ($search !== ''): ?><a href="<?php echo htmlspecialchars(qs(['q' => null, 'page' => 1])); ?>" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </form>
            <span class="badge bg-secondary align-self-center"><?php echo $total; ?> reservations</span>
          </div>

          <div class="panel">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?php echo sort_header('id', '#', $sortCol, $sortDir); ?></th>
                    <th><?php echo sort_header('full_name', 'Customer', $sortCol, $sortDir); ?></th>
                    <th>Phone</th>
                    <th><?php echo sort_header('reservation_date', 'Date & Time', $sortCol, $sortDir); ?></th>
                    <th><?php echo sort_header('guests', 'Guests', $sortCol, $sortDir); ?></th>
                    <th>Message</th>
                    <th><?php echo sort_header('status', 'Status', $sortCol, $sortDir); ?></th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($total === 0): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No reservations found.</td></tr>
                  <?php else: ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                      <tr>
                        <td><strong>#<?php echo $row['id']; ?></strong></td>
                        <td>
                          <div class="fw-semibold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                          <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td>
                          <div><?php echo date('M d, Y', strtotime($row['reservation_date'])); ?></div>
                          <small class="text-muted"><?php echo $row['reservation_time']; ?></small>
                        </td>
                        <td><span class="badge bg-info text-dark"><?php echo $row['guests']; ?> guests</span></td>
                        <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($row['message']); ?>">
                          <?php
                            if ($row['message']) {
                              $preview = function_exists('mb_substr') ? mb_substr($row['message'], 0, 50) : substr($row['message'], 0, 50);
                              echo htmlspecialchars($preview) . '...';
                            } else {
                              echo '—';
                            }
                          ?>
                        </td>
                        <td>
                          <?php
                            $sbadge = ['Pending'=>'warning','Confirmed'=>'success','Cancelled'=>'danger'];
                            $sclass = $sbadge[$row['status']] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?php echo $sclass; ?>"><?php echo $row['status']; ?></span>
                        </td>
                        <td>
                          <a href="reservation-edit.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                          <div class="dropdown d-inline">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Action</button>
                            <ul class="dropdown-menu">
                              <?php foreach (['Confirmed'=>['bi-check-circle','text-success','Confirm'],'Cancelled'=>['bi-x-circle','text-danger','Cancel']] as $st => $meta): ?>
                              <li>
                                <form method="POST" action="<?php echo htmlspecialchars(qs([])); ?>">
                                  <?php csrf_field(); ?>
                                  <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                  <input type="hidden" name="status_change" value="<?php echo htmlspecialchars($st); ?>">
                                  <button type="submit" class="dropdown-item"><i class="bi <?php echo $meta[0]; ?> me-2 <?php echo $meta[1]; ?>"></i><?php echo htmlspecialchars($meta[2]); ?></button>
                                </form>
                              </li>
                              <?php endforeach; ?>
                              <li><hr class="dropdown-divider"></li>
                              <li>
                                <form method="POST" action="<?php echo htmlspecialchars(qs([])); ?>" onsubmit="return confirm('Delete this reservation?')">
                                  <?php csrf_field(); ?>
                                  <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                                  <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                </form>
                              </li>
                            </ul>
                          </div>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center p-2">
              <p class="text-muted small mb-0">Showing <?php echo $total === 0 ? 0 : ($offset + 1); ?>-<?php echo min($offset + $perPage, $total); ?> of <?php echo $total; ?></p>
              <?php echo render_pagination($page, $totalPages); ?>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/vendors/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/app.js"></script>
</body>
</html>