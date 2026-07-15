<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_once '../../config/upload.php';
require_permission('menu.view');

$msg = '';

// Add menu item
if (isset($_POST['add_item'])) {
    verify_csrf();
    require_permission('menu.create');
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = floatval($_POST['price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $featured    = intval($_POST['featured'] ?? 0);
    $status      = intval($_POST['status'] ?? 1);

    if (empty($title) || $price <= 0) {
        $msg = '<div class="alert alert-danger">Please fill all required fields.</div>';
    } else {
        $image = ''; // no image is fine for a new item
        $upload = handle_image_upload('image_file', 'menu');
        if ($upload['success']) {
            $image = $upload['path'];
        } elseif ($upload['error'] !== 'no_file') {
            $msg = '<div class="alert alert-danger">Image upload failed: ' . htmlspecialchars($upload['error']) . '</div>';
        }

        if (empty($msg)) {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-')) . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
            $stmt = mysqli_prepare($conn, "INSERT INTO menu_items (title, slug, description, price, category_id, image, featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssdisii', $title, $slug, $description, $price, $category_id, $image, $featured, $status);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            log_activity($conn, 'menu_item_created', "Created menu item: {$title}", null, [
                'title' => $title, 'price' => $price, 'category_id' => $category_id, 'status' => $status,
            ]);
            $msg = '<div class="alert alert-success">Menu item added successfully.</div>';
        }
    }
}

// Update menu item
if (isset($_POST['update_item'])) {
    verify_csrf();
    require_permission('menu.edit');
    $id          = intval($_POST['id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = floatval($_POST['price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $featured    = intval($_POST['featured'] ?? 0);
    $status      = intval($_POST['status'] ?? 1);
    $remove_image = isset($_POST['remove_image']);

    if ($id <= 0 || empty($title) || $price <= 0) {
        $msg = '<div class="alert alert-danger">Please fill all required fields.</div>';
    } else {
        $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM menu_items WHERE id=" . (int) $id));
        $image = $existing['image'] ?? '';

        $upload = handle_image_upload('image_file', 'menu', $image ?: null);
        if ($upload['success']) {
            $image = $upload['path'];
        } elseif ($upload['error'] !== 'no_file') {
            $msg = '<div class="alert alert-danger">Image upload failed: ' . htmlspecialchars($upload['error']) . '</div>';
        } elseif ($remove_image) {
            delete_uploaded_image($image);
            $image = '';
        }

        if (empty($msg)) {
            $stmt = mysqli_prepare($conn, "UPDATE menu_items SET title=?, description=?, price=?, category_id=?, image=?, featured=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssdisiii', $title, $description, $price, $category_id, $image, $featured, $status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            log_field_changes($conn, 'menu_item_updated', "Updated menu item #{$id}", $existing, [
                'title' => $title, 'description' => $description, 'price' => $price,
                'category_id' => $category_id, 'image' => $image, 'featured' => $featured, 'status' => $status,
            ]);
            $msg = '<div class="alert alert-success">Menu item updated successfully.</div>';
        }
    }
}

// Delete (POST + CSRF, was a GET link before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    verify_csrf();
    require_permission('menu.delete');
    $id = intval($_POST['delete_id']);
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM menu_items WHERE id=" . (int) $id));
    $stmt = mysqli_prepare($conn, "DELETE FROM menu_items WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!empty($existing['image'])) delete_uploaded_image($existing['image']);
    if ($existing) log_activity($conn, 'menu_item_deleted', "Deleted menu item #{$id}", $existing, null);
    header("Location: menu.php?msg=deleted"); exit;
}

$edit_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM menu_items WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $edit_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

require_once 'include/pagination.php';

$categories = mysqli_query($conn, "SELECT * FROM categories WHERE status=1 ORDER BY title ASC");
$cats_arr = [];
while ($c = mysqli_fetch_assoc($categories)) $cats_arr[] = $c;

$search = trim($_GET['q'] ?? '');
$catFilter = intval($_GET['cat'] ?? 0);
[$sortCol, $sortDir] = sort_params(['id', 'title', 'price', 'status'], 'id');
[$page, $perPage, $offset] = paginate_params(12);

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = 'm.title LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}
if ($catFilter > 0) {
    $where[] = 'm.category_id = ?';
    $params[] = $catFilter;
    $types .= 'i';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sortColSql = $sortCol === 'title' || $sortCol === 'price' || $sortCol === 'status' ? 'm.' . $sortCol : 'm.id';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM menu_items m $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalItems = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, (int) ceil($totalItems / $perPage));

$listStmt = mysqli_prepare($conn, "SELECT m.*, c.title as cat_name FROM menu_items m LEFT JOIN categories c ON m.category_id=c.id $whereSql ORDER BY $sortColSql $sortDir LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($listStmt, $types . 'ii', ...$allParams);
mysqli_stmt_execute($listStmt);
$items = mysqli_stmt_get_result($listStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menu Items | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Menu Items</span>
          <div class="navbar-actions ms-auto">
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-4">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-egg-fried"></i></span>
              <div>
                <h1 class="h3 mb-1">Menu Items</h1>
                <p class="text-muted mb-0">Add, edit, or remove menu items from your restaurant</p>
              </div>
            </div>
          </div>

          <?php echo $msg; ?>
          <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">Item deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          <?php endif; ?>

          <div class="row g-4">
            <!-- Form -->
            <div class="col-lg-4">
              <div class="panel p-4">
                <h5 class="mb-3"><?php echo $edit_item ? 'Edit Item' : 'Add New Item'; ?></h5>
                <form method="POST" enctype="multipart/form-data">
                  <?php csrf_field(); ?>
                  <?php if ($edit_item): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>">
                  <?php endif; ?>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required value="<?php echo $edit_item ? htmlspecialchars($edit_item['title']) : ''; ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo $edit_item ? htmlspecialchars($edit_item['description']) : ''; ?></textarea>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Price ($) <span class="text-danger">*</span></label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?php echo $edit_item ? $edit_item['price'] : ''; ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category_id" class="form-select">
                      <?php foreach ($cats_arr as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php if ($edit_item && $edit_item['category_id']==$c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['title']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Photo</label>
                    <?php if ($edit_item && !empty($edit_item['image'])): ?>
                      <div class="mb-2 d-flex align-items-center gap-2">
                        <img src="../../<?php echo htmlspecialchars($edit_item['image']); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="removeImage" name="remove_image" value="1">
                          <label class="form-check-label small text-danger" for="removeImage">Remove current photo</label>
                        </div>
                      </div>
                    <?php endif; ?>
                    <input type="file" name="image_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG, or WEBP. Max 5MB. Images are automatically resized and re-encoded for security.</div>
                  </div>
                  <div class="row g-2 mb-3">
                    <div class="col-6">
                      <label class="form-label fw-semibold">Featured</label>
                      <select name="featured" class="form-select">
                        <option value="0" <?php if ($edit_item && $edit_item['featured']==0) echo 'selected'; ?>>No</option>
                        <option value="1" <?php if ($edit_item && $edit_item['featured']==1) echo 'selected'; ?>>Yes</option>
                      </select>
                    </div>
                    <div class="col-6">
                      <label class="form-label fw-semibold">Status</label>
                      <select name="status" class="form-select">
                        <option value="1" <?php if ($edit_item && $edit_item['status']==1) echo 'selected'; ?>>Active</option>
                        <option value="0" <?php if ($edit_item && $edit_item['status']==0) echo 'selected'; ?>>Inactive</option>
                      </select>
                    </div>
                  </div>
                  <?php if ($edit_item): ?>
                    <button type="submit" name="update_item" class="btn btn-primary w-100">Update Item</button>
                    <a href="menu.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                  <?php else: ?>
                    <button type="submit" name="add_item" class="btn btn-primary w-100">Add Item</button>
                  <?php endif; ?>
                </form>
              </div>
            </div>

            <!-- Table -->
            <div class="col-lg-8">
              <form class="d-flex gap-2 mb-2" method="GET">
                <input class="form-control form-control-sm" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title...">
                <select name="cat" class="form-select form-select-sm" style="max-width:180px;" onchange="this.form.submit()">
                  <option value="0">All Categories</option>
                  <?php foreach ($cats_arr as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $catFilter == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['title']); ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                <?php if ($search !== '' || $catFilter > 0): ?><a href="menu.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
                <span class="badge bg-secondary align-self-center ms-auto"><?php echo $totalItems; ?> items</span>
              </form>
              <div class="panel">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr><th><?php echo sort_header('id', '#', $sortCol, $sortDir); ?></th><th>Image</th><th><?php echo sort_header('title', 'Title', $sortCol, $sortDir); ?></th><th>Category</th><th><?php echo sort_header('price', 'Price', $sortCol, $sortDir); ?></th><th>Featured</th><th><?php echo sort_header('status', 'Status', $sortCol, $sortDir); ?></th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                      <?php if (mysqli_num_rows($items) === 0): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No menu items found.</td></tr>
                      <?php else: ?>
                        <?php while ($item = mysqli_fetch_assoc($items)): ?>
                          <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><img src="../../<?php echo htmlspecialchars($item['image']); ?>" style="width:45px;height:45px;object-fit:cover;border-radius:6px;" onerror="this.src='../assets/images/avatar/avatar.jpg'" loading="lazy"></td>
                            <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['cat_name'] ?? 'N/A'); ?></span></td>
                            <td class="fw-bold text-success">$<?php echo number_format($item['price'],2); ?></td>
                            <td><?php echo $item['featured'] ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>'; ?></td>
                            <td><?php echo $item['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                            <td>
                              <a href="menu.php?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo (int) $item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                              </form>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2">
                  <p class="text-muted small mb-0">Showing <?php echo $totalItems === 0 ? 0 : ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalItems); ?> of <?php echo $totalItems; ?></p>
                  <?php echo render_pagination($page, $totalPages); ?>
                </div>
              </div>
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
