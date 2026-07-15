<?php
require_once 'include/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Admin Panel professional admin dashboard template">
  <title>Components | Admin Panel</title>

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
              <span class="page-icon"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">UI Kit</p>
                <h1 class="h3 mb-1">Components</h1>
                <p class="text-muted mb-0">Buttons, badges, progress, accordions, and reusable interface blocks.</p>
              </div>
            </div>
            
          </div>

          <section class="row g-3">
            <div class="col-12 col-xl-6"><div class="panel h-100"><h2 class="h5 mb-3 section-title"><i class="bi bi-cursor" aria-hidden="true"></i><span>Buttons</span></h2><div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="button">Primary</button><button class="btn btn-outline-secondary" type="button">Secondary</button><button class="btn btn-success" type="button">Success</button><button class="btn btn-danger" type="button">Danger</button><button class="btn btn-warning" type="button">Warning</button></div><h2 class="h5 mt-4 mb-3">Badges</h2><div class="d-flex flex-wrap gap-2"><span class="badge text-bg-primary">Primary</span><span class="badge text-bg-success">Success</span><span class="badge text-bg-warning">Warning</span><span class="badge text-bg-danger">Danger</span></div></div></div>
            <div class="col-12 col-xl-6"><div class="panel h-100"><h2 class="h5 mb-3 section-title"><i class="bi bi-reception-4" aria-hidden="true"></i><span>Progress</span></h2><div class="progress mb-3"><div class="progress-bar progress-72" role="progressbar" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100">72%</div></div><div class="progress mb-3"><div class="progress-bar bg-success progress-58" role="progressbar" aria-valuenow="58" aria-valuemin="0" aria-valuemax="100">58%</div></div><div class="progress"><div class="progress-bar bg-warning progress-42" role="progressbar" aria-valuenow="42" aria-valuemin="0" aria-valuemax="100">42%</div></div></div></div>
            <div class="col-12"><div class="panel"><h2 class="h5 mb-3 section-title"><i class="bi bi-menu-button-wide" aria-hidden="true"></i><span>Accordion</span></h2><div class="accordion" id="componentAccordion"><div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">Reusable layout</button></h3><div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#componentAccordion"><div class="accordion-body">Use the admin shell, panel, metric-card, and table styles for consistent pages.</div></div></div><div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">Responsive behavior</button></h3><div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#componentAccordion"><div class="accordion-body">The sidebar becomes a mobile overlay and a compact mini sidebar on desktop.</div></div></div></div></div></div>
          </section>
        </div>
      </main>

      <footer class="admin-footer">
        <div class="container-fluid px-3 px-lg-4">
          <span>Copyright 2026 Admin Panel. <br> Developed by <a target="_blank" class="fw-bold text-success" href="https://github.com/HasanMahmudDev">Md. Hasan Mahmud</a> • Distributed by <a target="_blank" class="fw-bold text-success" href="https://themewagon.com">ThemeWagon</a> </span>
          <span>Professional dashboard template.</span>
          <span>Reusable UI components.</span>
        </div>
      </footer>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
