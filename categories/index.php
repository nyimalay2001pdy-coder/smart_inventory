<?php
include "../includes/auth_check.php";
requireAdmin();
include "../config/database.php";
include "../config/helpers.php";

$page_title = "Categories";

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

if (isset($_GET['confirm_delete'])) {
    $delete_id = (int)$_GET['confirm_delete'];
    $del_check = mysqli_query($conn, "SELECT name FROM categories WHERE id = $delete_id");
    if (mysqli_num_rows($del_check) > 0) {
        mysqli_query($conn, "DELETE FROM categories WHERE id = $delete_id");
        header("Location: index.php?success=" . urlencode("Category deleted successfully"));
        exit;
    }
}

$where = "WHERE 1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($status_filter !== '') {
    $where .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "SELECT * FROM categories $where ORDER BY id DESC";
$result = executeQuery($conn, $sql, $params, $types);
if (!$result) {
    $result = mysqli_query($conn, "SELECT * FROM categories ORDER BY id DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col min-w-0">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">
                <div class="page-header">
                    <div>
                        <h1 class="page-title"><?= $page_title ?></h1>
                        <p class="page-subtitle">Manage all product categories</p>
                    </div>
                    <a href="add.php" class="btn btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New Category
                    </a>
                </div>

                <form method="GET" class="filter-bar">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search category..." class="form-input w-auto flex-1">
                    <select name="status" class="form-input w-auto">
                        <option value="">All Status</option>
                        <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn btn-secondary">Reset</a>
                </form>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Category Name</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                        <?php $count = 1; ?>
                                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td class="text-gray-500"><?= $count++ ?></td>
                                                <td>
                                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($row['name']) ?></p>
                                                    <?php if (!empty($row['description'])): ?>
                                                        <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($row['description']) ?></p>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['status'] === 'Active'): ?>
                                                        <span class="badge badge-success"><span class="badge-dot"></span>Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger"><span class="badge-dot"></span>Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-gray-500"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                                <td class="text-right">
                                                    <div class="action-group justify-end">
                                                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                            Edit
                                                        </a>
                                                        <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', 'index.php')" class="btn btn-sm btn-danger">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5">
                                                <div class="empty-state">
                                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                    </svg>
                                                    <h3>No categories found</h3>
                                                    <p>Get started by adding a new category.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>
</html>
