<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="sidebar-header">
        <a class="brand-mark" href="index.php" aria-label="adminHMD dashboard">
          <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
          <span class="brand-copy">
            <span class="brand-title">AdminPanel</span>
            <span class="brand-subtitle">Restaurant Ctrl</span>
          </span>
        </a>
      </div>

      <nav class="sidebar-nav">
        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
          <span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
          <span class="nav-text">Dashboard</span>
        </a>
        <?php if (user_has_permission('orders.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>" href="orders.php">
          <span class="nav-icon"><i class="bi bi-cart-check" aria-hidden="true"></i></span>
          <span class="nav-text">Orders</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_permission('menu.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'menu.php') ? 'active' : ''; ?>" href="menu.php">
          <span class="nav-icon"><i class="bi bi-egg-fried" aria-hidden="true"></i></span>
          <span class="nav-text">Menu Items</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_permission('categories.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>" href="categories.php">
          <span class="nav-icon"><i class="bi bi-tags" aria-hidden="true"></i></span>
          <span class="nav-text">Categories</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_permission('reservations.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'reservations.php') ? 'active' : ''; ?>" href="reservations.php">
          <span class="nav-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
          <span class="nav-text">Reservations</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_permission('users.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'users.php' || $current_page == 'add-user.php') ? 'active' : ''; ?>" href="users.php">
          <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
          <span class="nav-text">Users / Staff</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_permission('messages.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>" href="messages.php">
          <span class="nav-icon"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
          <span class="nav-text">Messages</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_permission('settings.edit')): ?>
        <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
          <span class="nav-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
          <span class="nav-text">Settings</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_permission('orders.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'charts.php') ? 'active' : ''; ?>" href="charts.php">
          <span class="nav-icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></span>
          <span class="nav-text">Analytics</span>
        </a>
        <?php endif; ?>
        <a class="nav-link <?php echo ($current_page == 'alerts.php') ? 'active' : ''; ?>" href="alerts.php">
          <span class="nav-icon"><i class="bi bi-bell" aria-hidden="true"></i></span>
          <span class="nav-text">Alerts</span>
        </a>
        <?php if (user_has_permission('activity_logs.view')): ?>
        <a class="nav-link <?php echo ($current_page == 'activity-logs.php') ? 'active' : ''; ?>" href="activity-logs.php">
          <span class="nav-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
          <span class="nav-text">Activity Log</span>
        </a>
        <?php endif; ?>
        <?php if (user_has_role(['Admin'])): ?>
        <a class="nav-link <?php echo ($current_page == 'backup.php') ? 'active' : ''; ?>" href="backup.php">
          <span class="nav-icon"><i class="bi bi-hdd-stack" aria-hidden="true"></i></span>
          <span class="nav-text">Backups</span>
        </a>
        <?php endif; ?>
      </nav>

      <div class="sidebar-user">
        <a href="profile.php" class="d-block text-decoration-none text-reset">
        <img class="avatar-img avatar-md sidebar-user-avatar" src="../assets/images/avatar/avatar.jpg" alt="Admin">
        <strong><?php echo htmlspecialchars($_SESSION['admin_user']['full_name']); ?></strong>
        <small><?php echo htmlspecialchars($_SESSION['admin_user']['role_name']); ?></small>
        </a>
      </div>

      <div class="sidebar-footer">
        <span class="status-dot"></span>
        <span class="sidebar-footer-text">Restaurant Online</span>
      </div>
    </aside>
