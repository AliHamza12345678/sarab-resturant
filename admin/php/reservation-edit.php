<?php
require_once 'include/auth.php';
require_once '../../config/db.php';

$id = intval($_GET['id'] ?? 0);
$isEdit = $id > 0;
require_permission($isEdit ? 'reservations.edit' : 'reservations.view');

$valid_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
$error_msg = '';
$reservation = ['full_name'=>'','email'=>'','phone'=>'','reservation_date'=>'','reservation_time'=>'','guests'=>2,'message'=>'','status'=>'Pending'];

if ($isEdit) {
    require_permission('reservations.edit');
    $stmt = mysqli_prepare($conn, "SELECT * FROM reservations WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $found = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$found) { header("Location: reservations.php"); exit(); }
    $reservation = $found;
} else {
    require_permission('reservations.edit'); // creating also requires edit-level access (admin manual entry)
}

if (isset($_POST['save_reservation_btn'])) {
    verify_csrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $reservation_date = trim($_POST['reservation_date'] ?? '');
    $reservation_time = trim($_POST['reservation_time'] ?? '');
    $guests = intval($_POST['guests'] ?? 1);
    $message = trim($_POST['message'] ?? '');
    $status = in_array($_POST['status'] ?? '', $valid_statuses, true) ? $_POST['status'] : 'Pending';

    if (empty($full_name) || empty($phone) || empty($reservation_date) || empty($reservation_time)) {
        $error_msg = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } else {
        $oldReservation = $isEdit ? $reservation : null; // $reservation already holds the pre-edit row for edits
        if ($isEdit) {
            $stmt = mysqli_prepare($conn, "UPDATE reservations SET full_name=?, email=?, phone=?, reservation_date=?, reservation_time=?, guests=?, message=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssssissi', $full_name, $email, $phone, $reservation_date, $reservation_time, $guests, $message, $status, $id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO reservations (full_name, email, phone, reservation_date, reservation_time, guests, message, status) VALUES (?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sssssiss', $full_name, $email, $phone, $reservation_date, $reservation_time, $guests, $message, $status);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $newState = ['full_name'=>$full_name,'email'=>$email,'phone'=>$phone,'reservation_date'=>$reservation_date,'reservation_time'=>$reservation_time,'guests'=>$guests,'status'=>$status];
        if ($isEdit) {
            log_field_changes($conn, 'reservation_updated', "Reservation for {$full_name} (#$id) updated by admin", $oldReservation, $newState);
        } else {
            log_activity($conn, 'reservation_created', "Reservation for {$full_name} created by admin", null, $newState);
        }
        header("Location: reservations.php?msg=" . ($isEdit ? 'updated' : 'created'));
        exit();
    }
    $reservation = compact('full_name','email','phone','reservation_date','reservation_time','guests','message','status');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $isEdit ? 'Edit' : 'Add'; ?> Reservation | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted"><?php echo $isEdit ? 'Edit' : 'Add'; ?> Reservation</span>
        </div>
      </nav>
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-3">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-calendar-event"></i></span>
              <div><h1 class="h3 mb-1"><?php echo $isEdit ? 'Edit Reservation #' . $id : 'Add Reservation'; ?></h1></div>
            </div>
            <div class="heading-actions"><a href="reservations.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a></div>
          </div>

          <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

          <div class="panel p-4" style="max-width:700px;">
            <form method="POST">
              <?php csrf_field(); ?>
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Full name *</label><input class="form-control" name="full_name" value="<?php echo htmlspecialchars($reservation['full_name']); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($reservation['email']); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Phone *</label><input class="form-control" name="phone" value="<?php echo htmlspecialchars($reservation['phone']); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Guests *</label><input class="form-control" type="number" min="1" max="50" name="guests" value="<?php echo (int) $reservation['guests']; ?>" required></div>
                <div class="col-md-6"><label class="form-label">Date *</label><input class="form-control" type="date" name="reservation_date" value="<?php echo htmlspecialchars($reservation['reservation_date']); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Time *</label><input class="form-control" type="time" name="reservation_time" value="<?php echo htmlspecialchars(substr($reservation['reservation_time'],0,5)); ?>" required></div>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <select class="form-select" name="status">
                    <?php foreach ($valid_statuses as $st): ?>
                      <option value="<?php echo $st; ?>" <?php echo ($reservation['status'] === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12"><label class="form-label">Special requests</label><textarea class="form-control" name="message" rows="3"><?php echo htmlspecialchars($reservation['message']); ?></textarea></div>
              </div>
              <button type="submit" name="save_reservation_btn" class="btn btn-primary mt-4"><i class="bi bi-save"></i> Save Reservation</button>
            </form>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
