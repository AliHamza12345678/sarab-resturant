<?php
require_once 'include/auth.php';
require_once '../../config/db.php';
require_once '../../config/upload.php';
require_permission('categories.view');

$msg = '';

// Add new category
if (isset($_POST['add_category'])) {
    verify_csrf();
    require_permission('categories.create');
    $title = trim($_POST['title'] ?? '');
    $status = intval($_POST['status'] ?? 1);
    if (!empty($title)) {
        $image = '';
        $upload = handle_image_upload('image_file', 'categories');
        if ($upload['success']) {
            $image = $upload['path'];
        } elseif ($upload['error'] !== 'no_file') {
            $msg = '<div class="alert alert-danger">Image upload failed: ' . htmlspecialchars($upload['error']) . '</div>';
        }
        if (empty($msg)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO categories (title, slug, image, status) VALUES (?, ?, ?, ?)");
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-')) . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
            mysqli_stmt_bind_param($stmt, 'sssi', $title, $slug, $image, $status);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            log_activity($conn, 'category_created', "Created category: {$title}", null, ['title' => $title, 'status' => $status]);
            $msg = '<div class="alert alert-success">Category added successfully.</div>';
        }
    } else {
        $msg = '<div class="alert alert-danger">Category name is required.</div>';
    }
}

// Update category
if (isset($_POST['update_category'])) {
    verify_csrf();
    require_permission('categories.edit');
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $status = intval($_POST['status'] ?? 1);
    $remove_image = isset($_POST['remove_image']);
    if ($id > 0 && !empty($title)) {
        $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM categories WHERE id=" . (int) $id));
        $image = $existing['image'] ?? '';

        $upload = handle_image_upload('image_file', 'categories', $image ?: null);
        if ($upload['success']) {
            $image = $upload['path'];
        } elseif ($upload['error'] !== 'no_file') {
            $msg = '<div class="alert alert-danger">Image upload failed: ' . htmlspecialchars($upload['error']) . '</div>';
        } elseif ($remove_image) {
            delete_uploaded_image($image);
            $image = '';
        }

        if (empty($msg)) {
            $stmt = mysqli_prepare($conn, "UPDATE categories SET title=?, image=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssii', $title, $image, $status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            log_field_changes($conn, 'category_updated', "Updated category #{$id}", $existing, ['title' => $title, 'image' => $image, 'status' => $status]);
            $msg = '<div class="alert alert-success">Category updated successfully.</div>';
        }
    }
}

// Delete category (POST + CSRF, was a GET link before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    verify_csrf();
    require_permission('categories.delete');
    $id = intval($_POST['delete_id']);
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM categories WHERE id=" . (int) $id));
    $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        if (!empty($existing['image'])) delete_uploaded_image($existing['image']);
        if ($existing) log_activity($conn, 'category_deleted', "Deleted category #{$id}", $existing, null);
        header("Location: categories.php?msg=deleted"); exit;
    } else {
        // Likely a foreign key constraint: category still has menu items.
        header("Location: categories.php?msg=in_use"); exit;
    }
}

// Fetch for edit
$edit_cat = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $edit_cat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

require_once 'include/pagination.php';

$search = trim($_GET['q'] ?? '');
[$sortCol, $sortDir] = sort_params(['id', 'title', 'status'], 'id');
[$page, $perPage, $offset] = paginate_params(12);

$whereSql = '';
$params = [];
$types = '';
if ($search !== '') {
    $whereSql = 'WHERE c.title LIKE ?';
    $params[] = '%' . $search . '%';
    $types = 's';
}
$sortColSql = 'c.' . $sortCol;

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM categories c $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalCats = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, (int) ceil($totalCats / $perPage));

$listStmt = mysqli_prepare($conn, "SELECT c.*, COUNT(m.id) as item_count FROM categories c LEFT JOIN menu_items m ON m.category_id = c.id $whereSql GROUP BY c.id ORDER BY $sortColSql $sortDir LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($listStmt, $types . 'ii', ...$allParams);
mysqli_stmt_execute($listStmt);
$categories = mysqli_stmt_get_result($listStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Categories | Admin Panel</title>
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
          <span class="ms-3 fw-semibold text-muted">Categories</span>
          <div class="navbar-actions ms-auto">
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading mb-4">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-tags"></i></span>
              <div>
                <h1 class="h3 mb-1">Categories</h1>
                <p class="text-muted mb-0">Manage menu categories</p>
              </div>
            </div>
          </div>

          <?php echo $msg; ?>
          <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">Category deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'in_use'): ?>
            <div class="alert alert-warning alert-dismissible fade show">Can't delete: this category still has menu items. Move or delete those items first. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          <?php endif; ?>

          <div class="row g-4">
            <!-- Form -->
            <div class="col-lg-4">
              <div class="panel p-4">
                <h5 class="mb-3"><?php echo $edit_cat ? 'Edit Category' : 'Add New Category'; ?></h5>
                <form method="POST" enctype="multipart/form-data">
                  <?php csrf_field(); ?>
                  <?php if ($edit_cat): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_cat['id']; ?>">
                  <?php endif; ?>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Category Name</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo $edit_cat ? htmlspecialchars($edit_cat['title']) : ''; ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Photo</label>
                    <?php if ($edit_cat && !empty($edit_cat['image'])): ?>
                      <div class="mb-2 d-flex align-items-center gap-2">
                        <img src="../../<?php echo htmlspecialchars($edit_cat['image']); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px;">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="removeCatImage" name="remove_image" value="1">
                          <label class="form-check-label small text-danger" for="removeCatImage">Remove current photo</label>
                        </div>
                      </div>
                    <?php endif; ?>
                    <input type="file" name="image_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG, or WEBP. Max 5MB.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                      <option value="1" <?php if ($edit_cat && $edit_cat['status']==1) echo 'selected'; ?>>Active</option>
                      <option value="0" <?php if ($edit_cat && $edit_cat['status']==0) echo 'selected'; ?>>Inactive</option>
                    </select>
                  </div>
                  <?php if ($edit_cat): ?>
                    <button type="submit" name="update_category" class="btn btn-primary w-100">Update Category</button>
                    <a href="categories.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                  <?php else: ?>
                    <button type="submit" name="add_category" class="btn btn-primary w-100">Add Category</button>
                  <?php endif; ?>
                </form>
              </div>
            </div>

            <!-- Table -->
            <div class="col-lg-8">
              <form class="d-flex gap-2 mb-2" method="GET">
                <input class="form-control form-control-sm" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search category name...">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                <?php if ($search !== ''): ?><a href="categories.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
                <span class="badge bg-secondary align-self-center ms-auto"><?php echo $totalCats; ?> categories</span>
              </form>
              <div class="panel">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr><th><?php echo sort_header('id', '#', $sortCol, $sortDir); ?></th><th><?php echo sort_header('title', 'Category Name', $sortCol, $sortDir); ?></th><th>Items</th><th><?php echo sort_header('status', 'Status', $sortCol, $sortDir); ?></th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                      <?php if (mysqli_num_rows($categories) === 0): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No categories found.</td></tr>
                      <?php endif; ?>
                      <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                        <tr>
                          <td><?php echo $cat['id']; ?></td>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <?php if (!empty($cat['image'])): ?>
                                <img src="../../<?php echo htmlspecialchars($cat['image']); ?>" style="width:32px;height:32px;object-fit:cover;border-radius:6px;" loading="lazy">
                              <?php endif; ?>
                              <strong><?php echo htmlspecialchars($cat['title']); ?></strong>
                            </div>
                          </td>
                          <td><span class="badge bg-primary"><?php echo $cat['item_count']; ?></span></td>
                          <td><?php echo $cat['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                          <td>
                            <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                              <?php csrf_field(); ?>
                              <input type="hidden" name="delete_id" value="<?php echo (int) $cat['id']; ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    </tbody>
                  </table>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2">
                  <p class="text-muted small mb-0">Showing <?php echo $totalCats === 0 ? 0 : ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalCats); ?> of <?php echo $totalCats; ?></p>
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
