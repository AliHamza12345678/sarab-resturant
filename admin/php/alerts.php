<?php
require_once 'include/auth.php';
require_once '../../config/db.php';

$alerts = [];

if (user_has_permission('orders.view')) {
    $c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status='Pending'"))['c'];
    if ($c > 0) $alerts[] = ['type'=>'warning','icon'=>'bi-cart-check','text'=>"$c order(s) awaiting confirmation.",'link'=>'orders.php?filter=Pending'];
}
if (user_has_permission('reservations.view')) {
    $c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM reservations WHERE status='Pending'"))['c'];
    if ($c > 0) $alerts[] = ['type'=>'info','icon'=>'bi-calendar-event','text'=>"$c reservation(s) awaiting confirmation.",'link'=>'reservations.php?filter=Pending'];
}
if (user_has_permission('messages.view')) {
    $c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM contact_messages WHERE is_read=0"))['c'];
    if ($c > 0) $alerts[] = ['type'=>'primary','icon'=>'bi-chat-left-text','text'=>"$c unread customer message(s).",'link'=>'messages.php?filter=unread'];
}
if (user_has_permission('menu.view')) {
    $c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM menu_items WHERE status=0"))['c'];
    if ($c > 0) $alerts[] = ['type'=>'secondary','icon'=>'bi-egg-fried','text'=>"$c menu item(s) currently disabled.",'link'=>'menu.php'];
}
if (user_has_permission('users.view')) {
    $c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE status=0"))['c'];
    if ($c > 0) $alerts[] = ['type'=>'danger','icon'=>'bi-person-x','text'=>"$c suspended staff account(s).",'link'=>'users.php'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alerts | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Alerts</span>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-3">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-bell"></i></span>
              <div><h1 class="h3 mb-1">Alerts</h1><p class="text-muted mb-0">Things that need your attention right now.</p></div>
            </div>
          </div>

          <?php if (empty($alerts)): ?>
            <div class="alert alert-success"><i class="bi bi-check2-circle me-2"></i>All caught up — nothing needs your attention right now.</div>
          <?php else: ?>
            <?php foreach ($alerts as $a): ?>
              <a href="<?php echo htmlspecialchars($a['link']); ?>" class="alert alert-<?php echo $a['type']; ?> d-flex align-items-center justify-content-between text-decoration-none">
                <span><i class="bi <?php echo $a['icon']; ?> me-2"></i><?php echo htmlspecialchars($a['text']); ?></span>
                <i class="bi bi-chevron-right"></i>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
