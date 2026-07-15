<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('orders.view');

$id = intval($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) { header("Location: orders.php"); exit(); }

$itemStmt = mysqli_prepare($conn, "SELECT * FROM order_items WHERE order_id = ?");
mysqli_stmt_bind_param($itemStmt, 'i', $id);
mysqli_stmt_execute($itemStmt);
$items = mysqli_stmt_get_result($itemStmt);

$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings LIMIT 1")) ?: [];
$currency = $settings['currency_symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order #<?php echo (int) $order['id']; ?> | Admin Panel</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    @media print {
      .no-print { display: none !important; }
      .admin-shell, .admin-main { display: block !important; }
      body { background: #fff !important; }
    }
  </style>
</head>
<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop no-print" data-sidebar-close></div>
    <div class="no-print"><?php require_once 'include/sidebar.php'; ?></div>
    <div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white no-print">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
          </button>
          <span class="ms-3 fw-semibold text-muted">Order Invoice</span>
          <div class="navbar-actions ms-auto">
            <a href="orders.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back to Orders</a>
            <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="bi bi-printer"></i> Print Invoice</button>
          </div>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="panel p-4" style="max-width:800px;margin:0 auto;">
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
              <div>
                <h2 class="h4 mb-1"><?php echo htmlspecialchars($settings['site_name'] ?? 'Restaurant'); ?></h2>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></p>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars($settings['phone'] ?? ''); ?> &middot; <?php echo htmlspecialchars($settings['email'] ?? ''); ?></p>
              </div>
              <div class="text-end">
                <h3 class="h5 mb-1">Invoice #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h3>
                <p class="text-muted small mb-0">Date: <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                <span class="badge text-bg-<?php echo $order['status']==='Delivered'?'success':($order['status']==='Cancelled'?'danger':'warning'); ?>"><?php echo htmlspecialchars($order['status']); ?></span>
              </div>
            </div>
            <hr>
            <div class="row mb-4">
              <div class="col-md-6">
                <h4 class="h6 text-muted">Billed To</h4>
                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($order['full_name']); ?></p>
                <p class="mb-0 small"><?php echo htmlspecialchars($order['email']); ?></p>
                <p class="mb-0 small"><?php echo htmlspecialchars($order['phone']); ?></p>
                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
              </div>
              <div class="col-md-6 text-md-end">
                <h4 class="h6 text-muted">Payment</h4>
                <p class="mb-0"><?php echo htmlspecialchars($order['payment_method']); ?></p>
              </div>
            </div>
            <table class="table">
              <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr></thead>
              <tbody>
                <?php $total = 0; while ($item = mysqli_fetch_assoc($items)): $sub = $item['price'] * $item['quantity']; $total += $sub; ?>
                <tr>
                  <td><?php echo htmlspecialchars($item['title']); ?></td>
                  <td class="text-center"><?php echo (int) $item['quantity']; ?></td>
                  <td class="text-end"><?php echo $currency; ?><?php echo number_format($item['price'], 2); ?></td>
                  <td class="text-end"><?php echo $currency; ?><?php echo number_format($sub, 2); ?></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
              <tfoot>
                <tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?php echo $currency; ?><?php echo number_format($order['total_price'], 2); ?></th></tr>
              </tfoot>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
