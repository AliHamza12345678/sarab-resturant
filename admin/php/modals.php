<?php
require_once 'include/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Admin Panel professional admin dashboard template">
  <title>Modals | Admin Panel</title>

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
                <span class="notification-dot"></span>
                <i class="bi bi-bell" aria-hidden="true"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end notification-menu">
                <div class="dropdown-header fw-bold text-body">Notifications</div>
                <a class="dropdown-item" href="users.php">
                  <span class="notification-title">New user registered</span>
                  <span class="notification-time">4 minutes ago</span>
                </a>
                <a class="dropdown-item" href="charts.php">
                  <span class="notification-title">Revenue target reached</span>
                  <span class="notification-time">32 minutes ago</span>
                </a>
                <a class="dropdown-item" href="settings.php">
                  <span class="notification-title">Security review completed</span>
                  <span class="notification-time">1 hour ago</span>
                </a>
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
              <span class="page-icon"><i class="bi bi-window-stack" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Overlay</p>
                <h1 class="h3 mb-1">Modals</h1>
                <p class="text-muted mb-0">Modal dialogs for confirmations and compact workflows.</p>
              </div>
            </div>
            
          </div>

          <section class="row g-3">
            <div class="col-12 col-xl-6"><div class="panel h-100"><h2 class="h5 mb-3 section-title"><i class="bi bi-window-stack" aria-hidden="true"></i><span>Modal Examples</span></h2><div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#confirmModal">Open Confirm Modal</button><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#formModal">Open Form Modal</button></div></div></div>
            <div class="col-12 col-xl-6"><div class="panel h-100"><h2 class="h5 mb-3 section-title"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Usage</span></h2><p class="text-muted mb-0">Use modals for focused confirmations, compact forms, and high-priority decisions without leaving the page.</p></div></div>
          </section>

          <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="confirmModalLabel">Confirm Action</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">This action will update the selected record.</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary">Confirm</button></div></div></div></div>
          <div class="modal fade" id="formModal" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form class="needs-validation" novalidate><div class="modal-header"><h2 class="modal-title h5" id="formModalLabel">Quick Note</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="form-label" for="modalNote">Note</label><textarea class="form-control" id="modalNote" rows="4" required></textarea><div class="invalid-feedback">A note is required.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Note</button></div></form></div></div></div>
        </div>
      </main>

      <footer class="admin-footer">
        <div class="container-fluid px-3 px-lg-4">
          <span>Copyright 2026 Admin Panel. <br> Developed by <a target="_blank" class="fw-bold text-success" href="https://github.com/HasanMahmudDev">Md. Hasan Mahmud</a> • Distributed by <a target="_blank" class="fw-bold text-success" href="https://themewagon.com">ThemeWagon</a> </span>
          <span>Professional dashboard template.</span>
          <span>Modal component examples.</span>
        </div>
      </footer>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
