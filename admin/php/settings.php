<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_once '../../config/upload.php';
require_once '../../config/cache.php';
require_permission('settings.edit');

$msg = '';

if (isset($_POST['save_settings_btn'])) {
    verify_csrf();

    $site_name       = trim($_POST['site_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $opening_hours    = trim($_POST['opening_hours'] ?? '');
    $facebook         = trim($_POST['facebook'] ?? '');
    $instagram        = trim($_POST['instagram'] ?? '');
    $twitter          = trim($_POST['twitter'] ?? '');
    $youtube          = trim($_POST['youtube'] ?? '');
    $currency_symbol  = trim($_POST['currency_symbol'] ?? '$');

    if (empty($site_name)) {
        $msg = '<div class="alert alert-danger">Site name is required.</div>';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '<div class="alert alert-danger">Please enter a valid email address.</div>';
    } else {
        $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings LIMIT 1"));
        $logo = $existing['logo'] ?? '';

        $upload = handle_image_upload('logo_file', 'settings', $logo ?: null);
        if ($upload['success']) {
            $logo = $upload['path'];
        } elseif ($upload['error'] !== 'no_file') {
            $msg = '<div class="alert alert-danger">Logo upload failed: ' . htmlspecialchars($upload['error']) . '</div>';
        }

        if (empty($msg)) {
            if ($existing) {
                $stmt = mysqli_prepare($conn, "UPDATE settings SET site_name=?, phone=?, email=?, address=?, opening_hours=?, facebook=?, instagram=?, twitter=?, youtube=?, currency_symbol=?, logo=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssssssssssi', $site_name, $phone, $email, $address, $opening_hours, $facebook, $instagram, $twitter, $youtube, $currency_symbol, $logo, $existing['id']);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO settings (site_name, phone, email, address, opening_hours, facebook, instagram, twitter, youtube, currency_symbol, logo) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'sssssssssss', $site_name, $phone, $email, $address, $opening_hours, $facebook, $instagram, $twitter, $youtube, $currency_symbol, $logo);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            log_field_changes($conn, 'settings_updated', 'Site settings updated', $existing ?: [], [
                'site_name' => $site_name, 'phone' => $phone, 'email' => $email, 'address' => $address,
                'opening_hours' => $opening_hours, 'facebook' => $facebook, 'instagram' => $instagram,
                'twitter' => $twitter, 'youtube' => $youtube, 'currency_symbol' => $currency_symbol, 'logo' => $logo,
            ]);
            cache_forget('site_settings');
            $msg = '<div class="alert alert-success">Settings saved successfully.</div>';
        }
    }
}

$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings LIMIT 1")) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Settings | adminHMD</title>

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
              <span class="page-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Workspace</p>
                <h1 class="h3 mb-1">Settings</h1>
                <p class="text-muted mb-0">Customize workspace defaults, security options, and notification preferences.</p>
              </div>
            </div>
            
          </div>

          <?php echo $msg; ?>

          <section class="row g-3">
            <div class="col-12 col-xl-7">
              <form class="panel needs-validation" method="POST" enctype="multipart/form-data" novalidate>
                <?php csrf_field(); ?>
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-sliders" aria-hidden="true"></i><span>Site Settings</span></h2><p class="text-muted mb-0">Shown across the public website (header, footer, contact page).</p></div></div>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Site Logo</label>
                    <?php if (!empty($settings['logo'])): ?>
                      <div class="mb-2"><img src="../../<?php echo htmlspecialchars($settings['logo']); ?>" style="height:48px;object-fit:contain;"></div>
                    <?php endif; ?>
                    <input type="file" name="logo_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG, or WEBP. Max 5MB.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="siteName">Site name *</label>
                    <input class="form-control" id="siteName" name="site_name" type="text" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="currencySymbol">Currency symbol</label>
                    <input class="form-control" id="currencySymbol" name="currency_symbol" type="text" maxlength="5" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? '$'); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="sitePhone">Phone</label>
                    <input class="form-control" id="sitePhone" name="phone" type="text" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="siteEmail">Email</label>
                    <input class="form-control" id="siteEmail" name="email" type="email" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="siteAddress">Address</label>
                    <input class="form-control" id="siteAddress" name="address" type="text" value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="openingHours">Opening hours</label>
                    <input class="form-control" id="openingHours" name="opening_hours" type="text" value="<?php echo htmlspecialchars($settings['opening_hours'] ?? ''); ?>" placeholder="e.g. Wed - Sun: 9 AM - 11 PM">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="siteFacebook">Facebook URL</label>
                    <input class="form-control" id="siteFacebook" name="facebook" type="text" value="<?php echo htmlspecialchars($settings['facebook'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="siteInstagram">Instagram URL</label>
                    <input class="form-control" id="siteInstagram" name="instagram" type="text" value="<?php echo htmlspecialchars($settings['instagram'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="siteTwitter">Twitter / X URL</label>
                    <input class="form-control" id="siteTwitter" name="twitter" type="text" value="<?php echo htmlspecialchars($settings['twitter'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="siteYoutube">YouTube URL</label>
                    <input class="form-control" id="siteYoutube" name="youtube" type="text" value="<?php echo htmlspecialchars($settings['youtube'] ?? ''); ?>">
                  </div>
                </div>
                <button class="btn btn-primary mt-4" type="submit" name="save_settings_btn"><i class="bi bi-check2-circle" aria-hidden="true"></i> Save Settings</button>
              </form>
            </div>
            <div class="col-12 col-xl-5">
              <div class="panel h-100">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-eye" aria-hidden="true"></i><span>Live Preview</span></h2><p class="text-muted mb-0">This is what visitors currently see.</p></div></div>
                <div class="settings-list">
                  <p class="mb-2"><strong><?php echo htmlspecialchars($settings['site_name'] ?? 'Not set'); ?></strong></p>
                  <p class="text-muted small mb-1"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($settings['phone'] ?? '—'); ?></p>
                  <p class="text-muted small mb-1"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($settings['email'] ?? '—'); ?></p>
                  <p class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($settings['address'] ?? '—'); ?></p>
                  <p class="text-muted small mb-0"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($settings['opening_hours'] ?? '—'); ?></p>
                </div>
              </div>
            </div>
          </section>
        </div>
      </main>

      <footer class="admin-footer">
        <div class="container-fluid px-3 px-lg-4">
          <span>Copyright 2026 adminHMD. <br> Developed by <a target="_blank" class="fw-bold text-success" href="https://github.com/HasanMahmudDev">Md. Hasan Mahmud</a> • Distributed by <a target="_blank" class="fw-bold text-success" href="https://themewagon.com">ThemeWagon</a> </span>
          <span>Professional dashboard template.</span>
          <span>Workspace settings page.</span>
        </div>
      </footer>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
