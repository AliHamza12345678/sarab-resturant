<?php
require_once __DIR__ . '/config/customer_auth.php';
require_customer_login();

$id = (int) $_SESSION['customer_user']['id'];
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 5;
$offset = ($page - 1) * $perPage;

$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE user_id=$id"))['c'];
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, 'iii', $id, $perPage, $offset);
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);
$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT currency_symbol FROM settings LIMIT 1")) ?: ['currency_symbol' => '$'];
$currency = $settings['currency_symbol'];

require_once 'header.php';
?>

<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center">
      <h2 style="font-weight: 700; margin-bottom: 10px;">My Orders</h2>
      <p style="color: #888; margin-bottom: 0;"><a href="my-account.php" style="color:#888;">My Account</a> / Orders</p>
   </div>
</div>

<div class="container my-5">
  <?php if (mysqli_num_rows($orders) === 0): ?>
    <div class="text-center py-5">
      <i class="fas fa-receipt" style="font-size:3rem;color:#ddd;"></i>
      <p class="text-muted mt-3 mb-4">You haven't placed any orders yet.</p>
      <a href="index.php#menu" class="btn-red py-3 px-5" style="border-radius:30px;">Browse Menu</a>
    </div>
  <?php else: ?>
    <?php while ($o = mysqli_fetch_assoc($orders)):
        $itemsRes = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=" . (int) $o['id']);
        $statusColor = ['Pending'=>'warning','Preparing'=>'info','Out for Delivery'=>'primary','Delivered'=>'success','Cancelled'=>'danger'][$o['status']] ?? 'secondary';
    ?>
      <div class="p-4 bg-white rounded shadow-sm border mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h6 class="mb-1">Order #<?php echo str_pad($o['id'], 6, '0', STR_PAD_LEFT); ?></h6>
            <p class="text-muted small mb-0"><?php echo date('M d, Y \a\t H:i', strtotime($o['created_at'])); ?></p>
          </div>
          <span class="badge text-bg-<?php echo $statusColor; ?> px-3 py-2"><?php echo htmlspecialchars($o['status']); ?></span>
        </div>
        <div class="border-top pt-3">
          <?php while ($item = mysqli_fetch_assoc($itemsRes)): ?>
            <div class="d-flex justify-content-between small mb-1">
              <span><?php echo htmlspecialchars($item['title']); ?> &times; <?php echo (int) $item['quantity']; ?></span>
              <span><?php echo $currency; ?><?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
            </div>
          <?php endwhile; ?>
          <div class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top">
            <span>Total</span>
            <span><?php echo $currency; ?><?php echo number_format($o['total_price'], 2); ?></span>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
    <?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
      <ul class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
