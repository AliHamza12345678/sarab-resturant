<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_once '../../config/insights.php';
require_once '../../config/cache.php';

// Real dashboard metrics
$res_revenue   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_price),0) as total FROM orders WHERE status != 'Cancelled'"));
$res_orders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders"));
$res_pending   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status='Pending'"));
$res_users     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"));
$res_reservations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM reservations"));
$res_messages  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM contact_messages WHERE is_read = 0"));
$res_menu      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM menu_items WHERE status = 1"));

$revenue      = number_format($res_revenue['total'], 2);
$total_orders = $res_orders['total'];
$pending_orders = $res_pending['total'];
$total_users  = $res_users['total'];
$total_reservations = $res_reservations['total'];
$unread_messages = $res_messages['total'];
$active_items = $res_menu['total'];

// Period-over-period trend for the top KPI cards (real % change, 7-day window)
$weekTrend = insight_period_comparison($conn, 7);

$usersWeek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"))['c'];
$usersPrevWeek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)"))['c'];
$usersTrend = insight_percent_change((float) $usersWeek, (float) $usersPrevWeek);

$resWeek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM reservations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"))['c'];
$resPrevWeek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM reservations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)"))['c'];
$resTrend = insight_percent_change((float) $resWeek, (float) $resPrevWeek);

// AI-powered smart insights (real statistical analysis, see config/insights.php)
// Cached for 3 minutes: this runs a dozen+ aggregate queries and doesn't need
// to be second-by-second fresh for a "business insights" panel.
$ai_insights = cache_remember('dashboard_ai_insights', 180, fn() => generate_ai_insights($conn));
$revenue_forecast = cache_remember('dashboard_revenue_forecast', 180, fn() => insight_revenue_forecast($conn, 14, 7));

// Chart data (cached — these are aggregate queries scanning order history)
$revenueByDay = cache_remember('dashboard_revenue_by_day', 180, function() use ($conn) {
    $rows = [];
    $rres = mysqli_query($conn, "SELECT DATE(created_at) d, SUM(CASE WHEN status != 'Cancelled' THEN total_price ELSE 0 END) revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY d ORDER BY d ASC");
    while ($r = mysqli_fetch_assoc($rres)) { $rows[] = $r; }
    return $rows;
});

$ordersByMonth = cache_remember('dashboard_orders_by_month', 180, function() use ($conn) {
    $rows = [];
    $mres = mysqli_query($conn, "SELECT DATE_FORMAT(created_at,'%b %Y') ym, COUNT(*) c FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m'), ym ORDER BY DATE_FORMAT(created_at,'%Y-%m') ASC");
    while ($r = mysqli_fetch_assoc($mres)) { $rows[] = $r; }
    return $rows;
});

$topFoods = cache_remember('dashboard_top_foods', 180, function() use ($conn) {
    $rows = [];
    $tres = mysqli_query($conn, "SELECT title, SUM(quantity) qty FROM order_items GROUP BY title ORDER BY qty DESC LIMIT 5");
    while ($r = mysqli_fetch_assoc($tres)) { $rows[] = $r; }
    return $rows;
});

$categorySales = cache_remember('dashboard_category_sales', 180, function() use ($conn) {
    $rows = [];
    $cres = mysqli_query($conn, "SELECT c.title, SUM(oi.price*oi.quantity) total FROM order_items oi JOIN menu_items mi ON mi.id=oi.menu_item_id JOIN categories c ON c.id=mi.category_id GROUP BY c.title ORDER BY total DESC");
    while ($r = mysqli_fetch_assoc($cres)) { $rows[] = $r; }
    return $rows;
});

// Real recent activity (replaces the old fake "Team Activity" panel)
$recent_activity = mysqli_query($conn, "SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.id DESC LIMIT 6");

// Real notifications (replaces the old fake dropdown)
$notif_pending_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status='Pending'"))['c'];
$notif_pending_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM reservations WHERE status='Pending'"))['c'];
$notif_unread_msg = $unread_messages;

// Recent orders for dashboard table
$recent_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 8");
$recent_users = mysqli_query($conn, "SELECT u.full_name, u.email, u.status, u.created_at, u.id, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Restaurant admin dashboard">
  <title>Dashboard | Admin Panel</title>

  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <?php require_once 'include/sidebar.php'; ?>

    <div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
          </button>

          <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Search users, orders, reports" aria-label="Search">
          </form>

          <div class="navbar-actions ms-auto">
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
              <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>
            <div class="dropdown">
              <button class="icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <?php if (($notif_pending_orders + $notif_pending_res + $notif_unread_msg) > 0): ?><span class="notification-dot"></span><?php endif; ?>
                <i class="bi bi-bell" aria-hidden="true"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end notification-menu">
                <div class="dropdown-header fw-bold text-body">Notifications</div>
                <?php if ($notif_pending_orders > 0): ?>
                <a class="dropdown-item" href="orders.php?filter=Pending">
                  <span class="notification-title"><?php echo $notif_pending_orders; ?> order(s) awaiting confirmation</span>
                </a>
                <?php endif; ?>
                <?php if ($notif_pending_res > 0): ?>
                <a class="dropdown-item" href="reservations.php?filter=Pending">
                  <span class="notification-title"><?php echo $notif_pending_res; ?> reservation(s) awaiting confirmation</span>
                </a>
                <?php endif; ?>
                <?php if ($notif_unread_msg > 0): ?>
                <a class="dropdown-item" href="messages.php?filter=unread">
                  <span class="notification-title"><?php echo $notif_unread_msg; ?> unread message(s)</span>
                </a>
                <?php endif; ?>
                <?php if (($notif_pending_orders + $notif_pending_res + $notif_unread_msg) === 0): ?>
                <span class="dropdown-item text-muted">All caught up — nothing pending.</span>
                <?php endif; ?>
                <a class="dropdown-item text-center small text-primary" href="alerts.php">View all alerts</a>
              </div>
            </div>

            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img class="avatar-img avatar-sm" src="../assets/images/avatar/avatar.jpg" alt="<?php echo htmlspecialchars($_SESSION['admin_user']['full_name']); ?>">
                <span class="profile-name d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['admin_user']['full_name']); ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><a class="dropdown-item" href="settings.php">Account settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Overview</p>
                <h1 class="h3 mb-1">Dashboard</h1>
                <p class="text-muted mb-0">Monitor performance, sales, users, and support from one clean workspace.</p>
              </div>
            </div>
            <div class="heading-actions"><button class="btn btn-outline-secondary btn-sm" type="button"><i class="bi bi-download" aria-hidden="true"></i> Export</button><button class="btn btn-primary btn-sm" type="button"><i class="bi bi-file-earmark-plus" aria-hidden="true"></i> Create Report</button></div>
          </div>

          <section class="row g-3 mt-1" aria-label="Dashboard metrics">
            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-primary">
                <div class="metric-top">
                  <span class="metric-label">Revenue</span>
                  <span class="metric-icon"><i class="bi bi-currency-dollar" aria-hidden="true"></i></span>
                </div>
                <div class="metric-value">$<?php echo $revenue; ?></div>
                <div class="metric-meta">
                  <?php if ($weekTrend['revenue_change'] !== null): ?>
                    <span class="<?php echo $weekTrend['revenue_change'] >= 0 ? 'text-success' : 'text-danger'; ?>"><i class="bi bi-arrow-<?php echo $weekTrend['revenue_change'] >= 0 ? 'up' : 'down'; ?>-short"></i><?php echo abs(round($weekTrend['revenue_change'],1)); ?>%</span>
                  <?php endif; ?>
                  <span>vs last 7 days</span>
                </div>
              </article>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-success">
                <div class="metric-top">
                  <span class="metric-label">Orders</span>
                  <span class="metric-icon"><i class="bi bi-bag-check" aria-hidden="true"></i></span>
                </div>
                <div class="metric-value"><?php echo $total_orders; ?></div>
                <div class="metric-meta">
                  <?php if ($weekTrend['orders_change'] !== null): ?>
                    <span class="<?php echo $weekTrend['orders_change'] >= 0 ? 'text-success' : 'text-danger'; ?>"><i class="bi bi-arrow-<?php echo $weekTrend['orders_change'] >= 0 ? 'up' : 'down'; ?>-short"></i><?php echo abs(round($weekTrend['orders_change'],1)); ?>%</span>
                  <?php endif; ?>
                  <span><?php echo $pending_orders; ?> pending</span>
                </div>
              </article>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-warning">
                <div class="metric-top">
                  <span class="metric-label">Users</span>
                  <span class="metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                </div>
                <div class="metric-value"><?php echo $total_users; ?></div>
                <div class="metric-meta">
                  <?php if ($usersTrend !== null): ?>
                    <span class="<?php echo $usersTrend >= 0 ? 'text-success' : 'text-danger'; ?>"><i class="bi bi-arrow-<?php echo $usersTrend >= 0 ? 'up' : 'down'; ?>-short"></i><?php echo abs(round($usersTrend,1)); ?>%</span>
                  <?php endif; ?>
                  <span>vs last 7 days</span>
                </div>
              </article>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
              <article class="metric-card metric-danger">
                <div class="metric-top">
                  <span class="metric-label">Reservations</span>
                  <span class="metric-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                </div>
                <div class="metric-value"><?php echo $total_reservations; ?></div>
                <div class="metric-meta">
                  <?php if ($resTrend !== null): ?>
                    <span class="<?php echo $resTrend >= 0 ? 'text-success' : 'text-danger'; ?>"><i class="bi bi-arrow-<?php echo $resTrend >= 0 ? 'up' : 'down'; ?>-short"></i><?php echo abs(round($resTrend,1)); ?>%</span>
                  <?php endif; ?>
                  <span>vs last 7 days</span>
                </div>
              </article>
            </div>

            <div class="col-12">
              <article class="metric-card metric-secondary d-flex align-items-center justify-content-between flex-wrap gap-2" style="padding: 0.9rem 1.25rem;">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
                  <span><strong><?php echo $unread_messages; ?></strong> unread message(s)</span>
                </div>
                <a href="messages.php" class="btn btn-sm btn-outline-secondary">View Messages</a>
              </article>
            </div>
          </section>

          <!-- ================= AI SMART INSIGHTS ================= -->
          <section class="row g-3 mt-1">
            <div class="col-12">
              <div class="panel ai-insights-panel">
                <div class="panel-header">
                  <div>
                    <h2 class="h5 mb-1 section-title"><i class="bi bi-cpu" aria-hidden="true"></i><span>AI Smart Insights</span></h2>
                    <p class="text-muted mb-0">Automatically generated from your real order &amp; sales data — trend analysis, forecasting, and anomaly detection.</p>
                  </div>
                  <?php if ($revenue_forecast['forecast'] !== null): ?>
                  <div class="text-end">
                    <p class="text-muted small mb-0">7-day revenue forecast</p>
                    <p class="h5 mb-0">$<?php echo number_format($revenue_forecast['forecast'], 2); ?> <span class="badge bg-secondary fw-normal"><?php echo ucfirst($revenue_forecast['confidence']); ?> confidence</span></p>
                  </div>
                  <?php endif; ?>
                </div>
                <div class="row g-2">
                  <?php foreach ($ai_insights as $insight): ?>
                    <?php
                      $colorMap = ['success'=>'success','warning'=>'warning','danger'=>'danger','info'=>'primary'];
                      $color = $colorMap[$insight['type']] ?? 'primary';
                    ?>
                    <div class="col-12 col-lg-6">
                      <div class="d-flex align-items-start gap-2 p-2 rounded border h-100">
                        <span class="badge bg-<?php echo $color; ?>-subtle text-<?php echo $color; ?> p-2"><i class="bi <?php echo htmlspecialchars($insight['icon']); ?>"></i></span>
                        <p class="mb-0 small"><?php echo htmlspecialchars($insight['text']); ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </section>

          <!-- ================= CHARTS (real data) ================= -->
          <section class="row g-3 mt-1">
            <div class="col-12 col-xl-8">
              <div class="panel">
                <div class="panel-header">
                  <div>
                    <h2 class="h5 mb-1 section-title"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i><span>Revenue (Last 30 Days)</span></h2>
                    <p class="text-muted mb-0">Daily revenue from non-cancelled orders.</p>
                  </div>
                  <a class="btn btn-light btn-sm" href="charts.php">Full Analytics</a>
                </div>
                <canvas id="dashRevenueChart" height="90"></canvas>
              </div>
            </div>

            <div class="col-12 col-xl-4">
              <div class="panel h-100">
                <div class="panel-header">
                  <div>
                    <h2 class="h5 mb-1 section-title"><i class="bi bi-activity" aria-hidden="true"></i><span>Recent Activity</span></h2>
                    <p class="text-muted mb-0">Latest actions across the admin panel.</p>
                  </div>
                </div>
                <div class="activity-list">
                  <?php if (mysqli_num_rows($recent_activity) === 0): ?>
                    <p class="text-muted small mb-0">No activity recorded yet.</p>
                  <?php else: ?>
                    <?php while ($act = mysqli_fetch_assoc($recent_activity)): ?>
                      <div class="activity-item">
                        <span class="activity-dot bg-primary"></span>
                        <div>
                          <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($act['full_name'] ?? 'System'); ?></p>
                          <p class="text-muted small mb-0"><?php echo htmlspecialchars($act['description'] ?? $act['action']); ?> &middot; <?php echo date('M d, H:i', strtotime($act['created_at'])); ?></p>
                        </div>
                      </div>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </section>

          <section class="row g-3 mt-1">
            <div class="col-12 col-xl-4">
              <div class="panel">
                <div class="panel-header"><div><h2 class="h6 mb-0 section-title"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Monthly Orders</span></h2></div></div>
                <canvas id="dashOrdersChart" height="140"></canvas>
              </div>
            </div>
            <div class="col-12 col-xl-4">
              <div class="panel">
                <div class="panel-header"><div><h2 class="h6 mb-0 section-title"><i class="bi bi-trophy" aria-hidden="true"></i><span>Top Selling Foods</span></h2></div></div>
                <canvas id="dashTopFoodsChart" height="140"></canvas>
              </div>
            </div>
            <div class="col-12 col-xl-4">
              <div class="panel">
                <div class="panel-header"><div><h2 class="h6 mb-0 section-title"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Category Sales</span></h2></div></div>
                <canvas id="dashCategoryChart" height="140"></canvas>
              </div>
            </div>
          </section>

          <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-people" aria-hidden="true"></i><span>Recent Users</span></h2>
                <p class="text-muted mb-0">Latest account activity across the workspace.</p>
              </div>
              <a class="btn btn-outline-secondary btn-sm" href="users.php">Manage Users</a>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead><tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Joined</th><th scope="col" class="text-end">Action</th></tr></thead>
                <tbody>
                  <?php if (mysqli_num_rows($recent_users) === 0): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No users yet.</td></tr>
                  <?php else: ?>
                    <?php while ($ru = mysqli_fetch_assoc($recent_users)): ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <img class="avatar-img avatar-sm" src="../assets/images/avatar/avatar.jpg" alt="<?php echo htmlspecialchars($ru['full_name']); ?>">
                          <div>
                            <p class="fw-semibold mb-0"><?php echo htmlspecialchars($ru['full_name']); ?></p>
                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($ru['email']); ?></p>
                          </div>
                        </div>
                      </td>
                      <td><?php echo htmlspecialchars($ru['role_name']); ?></td>
                      <td><?php echo $ru['status'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Suspended</span>'; ?></td>
                      <td><?php echo date('M d, Y', strtotime($ru['created_at'])); ?></td>
                      <td class="text-end"><a class="btn btn-light btn-sm" href="user-details.php?id=<?php echo (int) $ru['id']; ?>">View</a></td>
                    </tr>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>

      <footer class="admin-footer">
        <div class="container-fluid px-3 px-lg-4">
          <span>Copyright 2026 adminHMD. <br> Developed by Ali Hamza</a> • Distributed by Ali Hamza </span>
          <span>Professional dashboard template.</span>
        </div>
      </footer>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script>
    const revenueByDay = <?php echo json_encode($revenueByDay); ?>;
    const ordersByMonth = <?php echo json_encode($ordersByMonth); ?>;
    const topFoods = <?php echo json_encode($topFoods); ?>;
    const categorySales = <?php echo json_encode($categorySales); ?>;

    new Chart(document.getElementById('dashRevenueChart'), {
      type: 'line',
      data: {
        labels: revenueByDay.map(r => r.d),
        datasets: [{ label: 'Revenue', data: revenueByDay.map(r => parseFloat(r.revenue)), borderColor: '#e11d48', backgroundColor: 'rgba(225,29,72,0.08)', fill: true, tension: 0.3, pointRadius: 2 }]
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxTicksLimit: 8 } } } }
    });

    new Chart(document.getElementById('dashOrdersChart'), {
      type: 'bar',
      data: {
        labels: ordersByMonth.map(r => r.ym),
        datasets: [{ label: 'Orders', data: ordersByMonth.map(r => parseInt(r.c)), backgroundColor: '#3b82f6' }]
      },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('dashTopFoodsChart'), {
      type: 'bar',
      data: {
        labels: topFoods.map(i => i.title),
        datasets: [{ label: 'Qty Sold', data: topFoods.map(i => parseInt(i.qty)), backgroundColor: '#f59e0b' }]
      },
      options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('dashCategoryChart'), {
      type: 'doughnut',
      data: {
        labels: categorySales.map(c => c.title),
        datasets: [{ data: categorySales.map(c => parseFloat(c.total)), backgroundColor: ['#e11d48','#f59e0b','#3b82f6','#22c55e','#8b5cf6','#06b6d4'] }]
      },
      options: { responsive: true }
    });
  </script>
</body>
</html>
