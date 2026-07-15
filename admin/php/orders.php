<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_once '../../config/cache.php';
require_permission('orders.view');

$valid_statuses = ['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];

// Handle status change (POST + CSRF, was previously a GET link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_change'], $_POST['id'])) {
    require_permission('orders.edit');
    verify_csrf();
    $id = intval($_POST['id']);
    $status = $_POST['status_change'];
    if (in_array($status, $valid_statuses, true) && $id > 0) {
        $oldOrder = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, status FROM orders WHERE id=" . (int) $id));
        $stmt = mysqli_prepare($conn, "UPDATE orders SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($oldOrder) {
            log_activity($conn, 'order_status_change', "Changed order #$id status", ['status' => $oldOrder['status']], ['status' => $status]);
        }
    }
    cache_flush_all();
    header("Location: orders.php?msg=updated"); exit;
}

// Handle delete (POST + CSRF, was previously a GET link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    require_permission('orders.delete');
    verify_csrf();
    $id = intval($_POST['delete_id']);
    $oldOrder = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=" . (int) $id));
    mysqli_begin_transaction($conn);
    $s1 = mysqli_prepare($conn, "DELETE FROM order_items WHERE order_id=?");
    mysqli_stmt_bind_param($s1, 'i', $id);
    mysqli_stmt_execute($s1);
    mysqli_stmt_close($s1);
    $s2 = mysqli_prepare($conn, "DELETE FROM orders WHERE id=?");
    mysqli_stmt_bind_param($s2, 'i', $id);
    mysqli_stmt_execute($s2);
    mysqli_stmt_close($s2);
    mysqli_commit($conn);
    if ($oldOrder) {
        log_activity($conn, 'order_deleted', "Deleted order #$id", $oldOrder, null);
    }
    cache_flush_all();
    header("Location: orders.php?msg=deleted"); exit;
}

require_once 'include/pagination.php';

$allowed_filters = array_merge(['all'], $valid_statuses);
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters, true)) ? $_GET['filter'] : 'all';
$search = trim($_GET['q'] ?? '');
[$sortCol, $sortDir] = sort_params(['id', 'full_name', 'total_price', 'status', 'created_at'], 'id');
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
    $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR id = ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = is_numeric($search) ? (int) $search : 0;
    $types .= 'sssi';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM orders $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, (int) ceil($total / $perPage));

$listStmt = mysqli_prepare($conn, "SELECT * FROM orders $whereSql ORDER BY $sortCol $sortDir LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
mysqli_stmt_bind_param($listStmt, $allTypes, ...$allParams);
mysqli_stmt_execute($listStmt);
$orders = mysqli_stmt_get_result($listStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Orders Management</span>
          <div class="navbar-actions ms-auto">
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-4">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-cart-check"></i></span>
              <div>
                <h1 class="h3 mb-1">Orders</h1>
                <p class="text-muted mb-0">View and manage all customer orders</p>
              </div>
            </div>
          </div>

          <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?php echo $_GET['msg'] === 'deleted' ? 'Order deleted successfully.' : 'Order status updated.'; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <!-- Filter tabs -->
          <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
            <?php foreach (['all','Pending','Preparing','Out for Delivery','Delivered','Cancelled'] as $f): ?>
              <a href="<?php echo htmlspecialchars(qs(['filter' => $f, 'page' => 1])); ?>" class="btn btn-sm <?php echo ($filter===$f) ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo htmlspecialchars($f); ?></a>
            <?php endforeach; ?>
            <form class="d-flex gap-2 ms-auto" method="GET">
              <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
              <input class="form-control form-control-sm" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search customer, email, phone, #ID" style="min-width:240px;">
              <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
              <?php if ($search !== ''): ?><a href="<?php echo htmlspecialchars(qs(['q' => null, 'page' => 1])); ?>" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </form>
            <span class="badge bg-secondary align-self-center"><?php echo $total; ?> orders</span>
          </div>

          <div class="panel">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?php echo sort_header('id', '#ID', $sortCol, $sortDir); ?></th>
                    <th><?php echo sort_header('full_name', 'Customer', $sortCol, $sortDir); ?></th>
                    <th>Phone</th>
                    <th><?php echo sort_header('total_price', 'Total', $sortCol, $sortDir); ?></th>
                    <th>Payment</th>
                    <th><?php echo sort_header('status', 'Status', $sortCol, $sortDir); ?></th>
                    <th><?php echo sort_header('created_at', 'Date', $sortCol, $sortDir); ?></th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (mysqli_num_rows($orders) === 0): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No orders found.</td></tr>
                  <?php else: ?>
                    <?php while ($o = mysqli_fetch_assoc($orders)): ?>
                      <tr>
                        <td><strong>#<?php echo $o['id']; ?></strong></td>
                        <td>
                          <div class="fw-semibold"><?php echo htmlspecialchars($o['full_name']); ?></div>
                          <small class="text-muted"><?php echo htmlspecialchars($o['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($o['phone']); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($o['total_price'],2); ?></td>
                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($o['payment_method']); ?></span></td>
                        <td>
                          <?php
                            $sbadge = ['Pending'=>'warning','Preparing'=>'info','Out for Delivery'=>'primary','Delivered'=>'success','Cancelled'=>'danger'];
                            $sclass = $sbadge[$o['status']] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?php echo $sclass; ?>"><?php echo $o['status']; ?></span>
                        </td>
                        <td><small class="text-muted"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></small></td>
                        <td>
                          <a href="order-detail.php?id=<?php echo (int) $o['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View Invoice"><i class="bi bi-receipt"></i></a>
                          <div class="dropdown d-inline">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Action</button>
                            <ul class="dropdown-menu">
                              <?php foreach (['Preparing'=>['bi-fire','text-warning','Mark Preparing'],'Out for Delivery'=>['bi-truck','text-info','Out for Delivery'],'Delivered'=>['bi-check-circle','text-success','Mark Delivered'],'Cancelled'=>['bi-x-circle','text-danger','Mark Cancelled']] as $st => $meta): ?>
                              <li>
                                <form method="POST" action="<?php echo htmlspecialchars(qs([])); ?>">
                                  <?php csrf_field(); ?>
                                  <input type="hidden" name="id" value="<?php echo (int) $o['id']; ?>">
                                  <input type="hidden" name="status_change" value="<?php echo htmlspecialchars($st); ?>">
                                  <button type="submit" class="dropdown-item"><i class="bi <?php echo $meta[0]; ?> me-2 <?php echo $meta[1]; ?>"></i><?php echo htmlspecialchars($meta[2]); ?></button>
                                </form>
                              </li>
                              <?php endforeach; ?>
                              <li><hr class="dropdown-divider"></li>
                              <li>
                                <form method="POST" action="<?php echo htmlspecialchars(qs([])); ?>" onsubmit="return confirm('Delete this order?')">
                                  <?php csrf_field(); ?>
                                  <input type="hidden" name="delete_id" value="<?php echo (int) $o['id']; ?>">
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
            <div class="d-flex justify-content-between align-items-center mt-3 px-1">
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
