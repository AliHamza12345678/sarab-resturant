<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('messages.view');

// Mark as read (POST + CSRF, was a GET link before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['read_id']) && is_numeric($_POST['read_id'])) {
    verify_csrf();
    $id = intval($_POST['read_id']);
    $stmt = mysqli_prepare($conn, "UPDATE contact_messages SET is_read=1 WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: messages.php"); exit;
}

// Delete (POST + CSRF, was a GET link before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    verify_csrf();
    require_permission('messages.delete');
    $id = intval($_POST['delete_id']);
    $oldMsg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, full_name, email, subject FROM contact_messages WHERE id=" . (int) $id));
    $stmt = mysqli_prepare($conn, "DELETE FROM contact_messages WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($oldMsg) log_activity($conn, 'message_deleted', "Deleted message #$id from {$oldMsg['full_name']}", $oldMsg, null);
    header("Location: messages.php?msg=deleted"); exit;
}

// Mark all read (POST + CSRF, was a GET link before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['readall'])) {
    verify_csrf();
    mysqli_query($conn, "UPDATE contact_messages SET is_read=1");
    header("Location: messages.php"); exit;
}

require_once 'include/pagination.php';

$allowed_filters = ['all', 'unread', 'read'];
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters, true)) ? $_GET['filter'] : 'all';
$search = trim($_GET['q'] ?? '');
[$sortCol, $sortDir] = sort_params(['id', 'full_name', 'created_at'], 'id');
[$page, $perPage, $offset] = paginate_params(15);

$where = [];
$params = [];
$types = '';
if ($filter === 'unread') { $where[] = 'is_read=0'; }
elseif ($filter === 'read') { $where[] = 'is_read=1'; }
if ($search !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM contact_messages $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalMsgs = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, (int) ceil($totalMsgs / $perPage));

$listStmt = mysqli_prepare($conn, "SELECT * FROM contact_messages $whereSql ORDER BY $sortCol $sortDir LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($listStmt, $types . 'ii', ...$allParams);
mysqli_stmt_execute($listStmt);
$messages = mysqli_stmt_get_result($listStmt);
$unread_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE is_read=0"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Contact Messages</span>
          <div class="navbar-actions ms-auto">
            <?php if ($unread_count > 0): ?>
              <form method="POST" class="d-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="readall" value="1">
                <button type="submit" class="btn btn-sm btn-outline-success me-2"><i class="bi bi-check2-all"></i> Mark All Read</button>
              </form>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-4">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-chat-left-text"></i></span>
              <div>
                <h1 class="h3 mb-1">Messages <?php if ($unread_count > 0) echo "<span class='badge bg-danger ms-2'>$unread_count</span>"; ?></h1>
                <p class="text-muted mb-0">Customer contact form submissions</p>
              </div>
            </div>
          </div>

          <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">Message deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          <?php endif; ?>

          <!-- Filter -->
          <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
            <a href="<?php echo htmlspecialchars(qs(['filter'=>'all','page'=>1])); ?>" class="btn btn-sm <?php echo $filter==='all'?'btn-primary':'btn-outline-secondary'; ?>">All</a>
            <a href="<?php echo htmlspecialchars(qs(['filter'=>'unread','page'=>1])); ?>" class="btn btn-sm <?php echo $filter==='unread'?'btn-primary':'btn-outline-secondary'; ?>">Unread <?php if($unread_count>0) echo "<span class='badge bg-danger'>$unread_count</span>"; ?></a>
            <a href="<?php echo htmlspecialchars(qs(['filter'=>'read','page'=>1])); ?>" class="btn btn-sm <?php echo $filter==='read'?'btn-primary':'btn-outline-secondary'; ?>">Read</a>
            <form class="d-flex gap-2 ms-auto" method="GET">
              <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
              <input class="form-control form-control-sm" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, subject" style="min-width:220px;">
              <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
              <?php if ($search !== ''): ?><a href="<?php echo htmlspecialchars(qs(['q'=>null,'page'=>1])); ?>" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </form>
          </div>

          <div class="panel">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr><th><?php echo sort_header('id','#',$sortCol,$sortDir); ?></th><th><?php echo sort_header('full_name','Name',$sortCol,$sortDir); ?></th><th>Email</th><th>Subject</th><th>Message</th><th>Status</th><th><?php echo sort_header('created_at','Date',$sortCol,$sortDir); ?></th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php if (mysqli_num_rows($messages) === 0): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No messages found.</td></tr>
                  <?php else: ?>
                    <?php while ($m = mysqli_fetch_assoc($messages)): ?>
                      <tr class="<?php echo !$m['is_read'] ? 'table-light fw-semibold' : ''; ?>">
                        <td><?php echo $m['id']; ?></td>
                        <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                        <td><a href="mailto:<?php echo htmlspecialchars($m['email']); ?>"><?php echo htmlspecialchars($m['email']); ?></a></td>
                        <td><?php echo htmlspecialchars($m['subject'] ?? '—'); ?></td>
                        <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($m['message']); ?>">
                          <?php
                            $msgPreview = $m['message'];
                            $truncated = function_exists('mb_substr') ? mb_substr($msgPreview, 0, 60) : substr($msgPreview, 0, 60);
                            $fullLength = function_exists('mb_strlen') ? mb_strlen($msgPreview) : strlen($msgPreview);
                            echo htmlspecialchars($truncated) . ($fullLength > 60 ? '...' : '');
                          ?>
                        </td>
                        <td><?php echo $m['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-warning text-dark">Unread</span>'; ?></td>
                        <td><small class="text-muted"><?php echo date('M d, Y', strtotime($m['created_at'])); ?></small></td>
                        <td>
                          <a href="message-view.php?id=<?php echo (int) $m['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View &amp; Reply"><i class="bi bi-envelope-open"></i></a>
                          <?php if (user_has_permission('messages.delete')): ?>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message?')">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="delete_id" value="<?php echo (int) $m['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                          </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center p-2">
              <p class="text-muted small mb-0">Showing <?php echo $totalMsgs === 0 ? 0 : ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalMsgs); ?> of <?php echo $totalMsgs; ?></p>
              <?php echo render_pagination($page, $totalPages); ?>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/vendors/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
