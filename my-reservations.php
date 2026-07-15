<?php
require_once __DIR__ . '/config/customer_auth.php';
require_customer_login();

$id = (int) $_SESSION['customer_user']['id'];

$msg = '';
// Allow a customer to cancel their own pending reservation
if (isset($_POST['cancel_id']) && is_numeric($_POST['cancel_id'])) {
    verify_csrf();
    $rid = intval($_POST['cancel_id']);
    $stmt = mysqli_prepare($conn, "UPDATE reservations SET status='Cancelled' WHERE id=? AND user_id=? AND status='Pending'");
    mysqli_stmt_bind_param($stmt, 'ii', $rid, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: my-reservations.php?cancelled=1");
    exit();
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 5;
$offset = ($page - 1) * $perPage;
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM reservations WHERE user_id=$id"))['c'];
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = mysqli_prepare($conn, "SELECT * FROM reservations WHERE user_id=? ORDER BY id DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, 'iii', $id, $perPage, $offset);
mysqli_stmt_execute($stmt);
$reservations = mysqli_stmt_get_result($stmt);

require_once 'header.php';
?>

<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center">
      <h2 style="font-weight: 700; margin-bottom: 10px;">My Reservations</h2>
      <p style="color: #888; margin-bottom: 0;"><a href="my-account.php" style="color:#888;">My Account</a> / Reservations</p>
   </div>
</div>

<div class="container my-5">
  <?php if (isset($_GET['cancelled'])): ?><div class="alert alert-info">Reservation cancelled.</div><?php endif; ?>
  <?php if (mysqli_num_rows($reservations) === 0): ?>
    <div class="text-center py-5">
      <i class="fas fa-calendar-alt" style="font-size:3rem;color:#ddd;"></i>
      <p class="text-muted mt-3 mb-4">You don't have any reservations yet.</p>
      <a href="index.php#reservation" class="btn-red py-3 px-5" style="border-radius:30px;">Book a Table</a>
    </div>
  <?php else: ?>
    <?php while ($r = mysqli_fetch_assoc($reservations)):
        $statusColor = ['Pending'=>'warning','Confirmed'=>'success','Completed'=>'secondary','Cancelled'=>'danger'][$r['status']] ?? 'secondary';
    ?>
      <div class="p-4 bg-white rounded shadow-sm border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <h6 class="mb-1"><?php echo date('M d, Y', strtotime($r['reservation_date'])); ?> at <?php echo date('g:i A', strtotime($r['reservation_time'])); ?></h6>
          <p class="text-muted small mb-0"><?php echo (int) $r['guests']; ?> guest(s)<?php if (!empty($r['message'])) echo ' &middot; ' . htmlspecialchars($r['message']); ?></p>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge text-bg-<?php echo $statusColor; ?> px-3 py-2"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php if ($r['status'] === 'Pending'): ?>
            <form method="POST" onsubmit="return confirm('Cancel this reservation?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="cancel_id" value="<?php echo (int) $r['id']; ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
    <?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
      <ul class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
