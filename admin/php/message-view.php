<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_permission('messages.view');

$id = intval($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT cm.*, u.full_name AS replied_by_name FROM contact_messages cm LEFT JOIN users u ON u.id = cm.replied_by WHERE cm.id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$message = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$message) { header("Location: messages.php"); exit(); }

// Auto-mark as read when opened
if (!$message['is_read']) {
    $u = mysqli_prepare($conn, "UPDATE contact_messages SET is_read=1 WHERE id=?");
    mysqli_stmt_bind_param($u, 'i', $id);
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);
    $message['is_read'] = 1;
}

$success_msg = '';
if (isset($_POST['reply_btn'])) {
    require_permission('messages.reply');
    verify_csrf();
    $reply = trim($_POST['reply'] ?? '');
    if (empty($reply)) {
        $error_msg = "Reply cannot be empty.";
    } else {
        $adminId = $_SESSION['admin_user']['id'];
        $stmt = mysqli_prepare($conn, "UPDATE contact_messages SET reply=?, replied_by=?, replied_at=NOW() WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sii', $reply, $adminId, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        log_activity($conn, 'message_replied', "Replied to message #$id", null, ['reply' => $reply]);

        require_once '../../config/mailer.php';
        try { send_message_reply_email($conn, [
            'full_name' => $message['full_name'], 'email' => $message['email'],
            'subject' => $message['subject'], 'reply' => $reply,
        ]); } catch (\Throwable $e) { error_log('reply email failed: ' . $e->getMessage()); }

        header("Location: message-view.php?id=$id&replied=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Message from <?php echo htmlspecialchars($message['full_name']); ?> | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Message</span>
          <div class="navbar-actions ms-auto">
            <a href="messages.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Messages</a>
          </div>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <?php if (isset($_GET['replied'])): ?>
            <div class="alert alert-success">Reply saved successfully.</div>
          <?php endif; ?>
          <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

          <div class="panel p-4" style="max-width:760px;">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h2 class="h5 mb-1"><?php echo htmlspecialchars($message['full_name']); ?></h2>
                <p class="text-muted small mb-0"><a href="mailto:<?php echo htmlspecialchars($message['email']); ?>"><?php echo htmlspecialchars($message['email']); ?></a><?php if($message['phone']) echo ' &middot; ' . htmlspecialchars($message['phone']); ?></p>
              </div>
              <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></small>
            </div>
            <p class="fw-semibold mb-1"><?php echo htmlspecialchars($message['subject'] ?: 'General Inquiry'); ?></p>
            <div class="border rounded p-3 bg-light mb-4"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>

            <?php if (!empty($message['reply'])): ?>
              <div class="border-start border-primary border-3 ps-3 mb-4">
                <p class="fw-semibold mb-1 text-primary">Reply by <?php echo htmlspecialchars($message['replied_by_name'] ?? 'Admin'); ?> &middot; <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($message['replied_at'])); ?></small></p>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['reply'])); ?></p>
              </div>
            <?php endif; ?>

            <?php if (user_has_permission('messages.reply')): ?>
            <form method="POST">
              <?php csrf_field(); ?>
              <label class="form-label fw-semibold"><?php echo $message['reply'] ? 'Update reply' : 'Write a reply'; ?></label>
              <textarea class="form-control" name="reply" rows="4" required><?php echo htmlspecialchars($message['reply'] ?? ''); ?></textarea>
              <button type="submit" name="reply_btn" class="btn btn-primary mt-3"><i class="bi bi-reply"></i> Send Reply</button>
            </form>
            <?php endif; ?>

            <?php if (user_has_permission('messages.delete')): ?>
              <form method="POST" action="messages.php" class="mt-4" onsubmit="return confirm('Delete this message?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="delete_id" value="<?php echo (int) $message['id']; ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete Message</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
