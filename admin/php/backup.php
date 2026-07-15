<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_once '../../config/backup.php';
require_role(['Admin']); // most sensitive operation in the panel: full data export

$msg = '';

if (isset($_POST['create_backup_btn'])) {
    verify_csrf();
    $dbName = mysqli_fetch_assoc(mysqli_query($conn, "SELECT DATABASE() as db"))['db'];
    $filename = generate_database_backup($conn, $dbName);
    if ($filename) {
        prune_old_backups(10);
        log_activity($conn, 'backup_created', "Created database backup: $filename");
        $msg = '<div class="alert alert-success">Backup created: ' . htmlspecialchars($filename) . '</div>';
    } else {
        $msg = '<div class="alert alert-danger">Backup failed. Check server file permissions.</div>';
    }
}

if (isset($_POST['delete_backup_btn'])) {
    verify_csrf();
    $filename = $_POST['filename'] ?? '';
    if (delete_database_backup($filename)) {
        log_activity($conn, 'backup_deleted', "Deleted backup: $filename");
        $msg = '<div class="alert alert-success">Backup deleted.</div>';
    } else {
        $msg = '<div class="alert alert-danger">Could not delete that backup.</div>';
    }
}

// Secure download: streams the file through PHP (never a direct public URL)
if (isset($_GET['download']) && preg_match('/^backup_[0-9-]+_[0-9-]+\.sql$/', $_GET['download'])) {
    $path = BACKUP_DIR . '/' . $_GET['download'];
    if (is_file($path)) {
        log_activity($conn, 'backup_downloaded', "Downloaded backup: " . $_GET['download']);
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit();
    }
}

$backups = list_database_backups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Backups | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Database Backups</span>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-3">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-hdd-stack"></i></span>
              <div>
                <h1 class="h3 mb-1">Database Backups</h1>
                <p class="text-muted mb-0">Admin only. Full SQL export of every table — download and store somewhere safe.</p>
              </div>
            </div>
            <div class="heading-actions">
              <form method="POST">
                <?php csrf_field(); ?>
                <button type="submit" name="create_backup_btn" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Create Backup Now</button>
              </form>
            </div>
          </div>

          <?php echo $msg; ?>
          <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Only the 10 most recent backups are kept automatically. For true disaster recovery, also download backups periodically and store them off-server (e.g. your own computer or cloud storage).</div>

          <section class="panel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>File</th><th>Size</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                  <?php if (empty($backups)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">No backups yet. Click "Create Backup Now" to make one.</td></tr>
                  <?php else: ?>
                    <?php foreach ($backups as $b): ?>
                      <tr>
                        <td><i class="bi bi-file-earmark-zip me-1"></i><?php echo htmlspecialchars($b['filename']); ?></td>
                        <td><?php echo number_format($b['size'] / 1024, 1); ?> KB</td>
                        <td><?php echo date('M d, Y H:i', $b['created_at']); ?></td>
                        <td class="text-end">
                          <a href="backup.php?download=<?php echo urlencode($b['filename']); ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-download"></i> Download</a>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this backup permanently?')">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($b['filename']); ?>">
                            <button type="submit" name="delete_backup_btn" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
