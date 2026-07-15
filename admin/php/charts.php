<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('orders.view');

// Revenue + order count for last 6 months (real data, only counts Delivered orders as revenue)
$revenueByMonth = [];
$res = mysqli_query($conn, "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(CASE WHEN status='Delivered' THEN total_price ELSE 0 END) AS revenue, COUNT(*) AS orders
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym ASC
");
while ($row = mysqli_fetch_assoc($res)) { $revenueByMonth[] = $row; }

// Orders by status
$statusCounts = [];
$res2 = mysqli_query($conn, "SELECT status, COUNT(*) c FROM orders GROUP BY status");
while ($row = mysqli_fetch_assoc($res2)) { $statusCounts[$row['status']] = (int) $row['c']; }

// Top 5 selling menu items (by quantity sold)
$topItems = [];
$res3 = mysqli_query($conn, "
    SELECT title, SUM(quantity) AS qty FROM order_items GROUP BY title ORDER BY qty DESC LIMIT 5
");
while ($row = mysqli_fetch_assoc($res3)) { $topItems[] = $row; }

// Category sales (revenue contribution)
$categorySales = [];
$res4 = mysqli_query($conn, "
    SELECT c.title, SUM(oi.price * oi.quantity) AS total
    FROM order_items oi
    JOIN menu_items mi ON mi.id = oi.menu_item_id
    JOIN categories c ON c.id = mi.category_id
    GROUP BY c.title ORDER BY total DESC
");
while ($row = mysqli_fetch_assoc($res4)) { $categorySales[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics | Admin Panel</title>
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
          <button class="sidebar-toggle" type="button" data-sidebar-toggle><span></span><span></span><span></span></button>
          <span class="ms-3 fw-semibold text-muted">Analytics</span>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-3">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-bar-chart"></i></span>
              <div><h1 class="h3 mb-1">Analytics</h1><p class="text-muted mb-0">Real sales data from the last 6 months.</p></div>
            </div>
          </div>

          <?php if (empty($revenueByMonth) && empty($topItems)): ?>
            <div class="alert alert-info">No order data yet. Charts will populate once orders come in.</div>
          <?php endif; ?>

          <div class="row g-3">
            <div class="col-12 col-xl-7">
              <div class="panel p-3">
                <h2 class="h6 mb-3">Revenue (Delivered Orders)</h2>
                <canvas id="revenueChart" height="110"></canvas>
              </div>
            </div>
            <div class="col-12 col-xl-5">
              <div class="panel p-3">
                <h2 class="h6 mb-3">Orders by Status</h2>
                <canvas id="statusChart" height="140"></canvas>
              </div>
            </div>
            <div class="col-12 col-xl-6">
              <div class="panel p-3">
                <h2 class="h6 mb-3">Top Selling Items</h2>
                <canvas id="topItemsChart" height="140"></canvas>
              </div>
            </div>
            <div class="col-12 col-xl-6">
              <div class="panel p-3">
                <h2 class="h6 mb-3">Category Sales</h2>
                <canvas id="categoryChart" height="140"></canvas>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script>
    const revenueData = <?php echo json_encode($revenueByMonth); ?>;
    const statusData = <?php echo json_encode($statusCounts); ?>;
    const topItemsData = <?php echo json_encode($topItems); ?>;
    const categoryData = <?php echo json_encode($categorySales); ?>;

    new Chart(document.getElementById('revenueChart'), {
      type: 'line',
      data: {
        labels: revenueData.map(r => r.ym),
        datasets: [{ label: 'Revenue', data: revenueData.map(r => parseFloat(r.revenue)), borderColor: '#e11d48', backgroundColor: 'rgba(225,29,72,0.1)', fill: true, tension: 0.3 }]
      },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('statusChart'), {
      type: 'doughnut',
      data: {
        labels: Object.keys(statusData),
        datasets: [{ data: Object.values(statusData), backgroundColor: ['#f59e0b','#3b82f6','#8b5cf6','#22c55e','#ef4444'] }]
      },
      options: { responsive: true }
    });

    new Chart(document.getElementById('topItemsChart'), {
      type: 'bar',
      data: {
        labels: topItemsData.map(i => i.title),
        datasets: [{ label: 'Qty Sold', data: topItemsData.map(i => parseInt(i.qty)), backgroundColor: '#e11d48' }]
      },
      options: { responsive: true, plugins: { legend: { display: false } }, indexAxis: 'y' }
    });

    new Chart(document.getElementById('categoryChart'), {
      type: 'pie',
      data: {
        labels: categoryData.map(c => c.title),
        datasets: [{ data: categoryData.map(c => parseFloat(c.total)), backgroundColor: ['#e11d48','#f59e0b','#3b82f6','#22c55e','#8b5cf6'] }]
      },
      options: { responsive: true }
    });
  </script>
</body>
</html>
