<?php
include "../includes/auth_check.php";
include "../config/database.php";
include "../config/helpers.php";

$is_admin = isAdmin();
if (!$is_admin) {
    requireAdmin();
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$show_edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : null;
$show_view_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : null;

// ============ DELETE ============
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];

    $details = mysqli_query($conn, "SELECT * FROM purchase_details WHERE purchase_id='$id'");
    while ($row = mysqli_fetch_assoc($details)) {
        $product_id = $row['product_id'];
        $qty = $row['quantity'];
        mysqli_query($conn, "UPDATE products SET quantity = quantity - $qty WHERE id='$product_id'");
    }

    mysqli_query($conn, "DELETE FROM purchase_details WHERE purchase_id='$id'");
    mysqli_query($conn, "DELETE FROM purchases WHERE id='$id'");
    header("Location: index.php?success=" . urlencode("Purchase deleted and stock rolled back."));
    exit;
}

// ============ UPDATE (EDIT) ============
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $payment_status = $_POST['payment_status'];
    $detail_ids = $_POST['detail_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['purchase_price'] ?? [];
    $product_ids = $_POST['product_id'] ?? [];

    $new_total = 0;
    foreach ($detail_ids as $i => $detail_id) {
        $detail_id = (int)$detail_id;
        $qty = (int)($quantities[$i] ?? 0);
        $price = (float)($prices[$i] ?? 0);
        $product_id = (int)($product_ids[$i] ?? 0);
        if ($detail_id <= 0 || $product_id <= 0) continue;
        $subtotal = $qty * $price;

        $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT quantity FROM purchase_details WHERE id='$detail_id'"));
        $old_qty = $old ? (int)$old['quantity'] : 0;
        $diff = $qty - $old_qty;

        mysqli_query($conn, "UPDATE products SET quantity = quantity + $diff, purchase_price='$price' WHERE id='$product_id'");
        mysqli_query($conn, "UPDATE purchase_details SET quantity='$qty', purchase_price='$price', subtotal='$subtotal' WHERE id='$detail_id'");
        $new_total += $subtotal;
    }

    mysqli_query($conn, "UPDATE purchases SET supplier_id='$supplier_id', total_amount='$new_total', payment_status='$payment_status' WHERE id='$id'");
    header("Location: index.php?success=" . urlencode("Purchase #$id updated successfully."));
    exit;
}

// ============ SEARCH / FILTER ============
$sql = "SELECT p.*, s.supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE 1";
if ($search) {
    $safe = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (s.supplier_name LIKE '%$safe%' OR p.invoice_no LIKE '%$safe%')";
}
if ($status_filter) {
    $sql .= " AND p.payment_status = '$status_filter'";
}
$sql .= " ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);

$all_products = mysqli_query($conn, "SELECT * FROM products WHERE status='Active'");
$all_suppliers = mysqli_query($conn, "SELECT * FROM suppliers WHERE status='Active'");

// ============ EDIT MODAL DATA ============
$edit_purchase = null;
$edit_details = null;
if ($show_edit_id) {
    $edit_purchase = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM purchases WHERE id='$show_edit_id'"));
    if ($edit_purchase) {
        $edit_details = mysqli_query(
            $conn,
            "SELECT d.*, p.product_name FROM purchase_details d
             INNER JOIN products p ON d.product_id = p.id
             WHERE d.purchase_id='$show_edit_id'"
        );
    }
}

// ============ VIEW MODAL DATA ============
$view_purchase = null;
$view_details = null;
if ($show_view_id) {
    $view_purchase = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT pu.*, s.supplier_name, s.phone, s.address
         FROM purchases pu
         LEFT JOIN suppliers s ON pu.supplier_id = s.id
         WHERE pu.id='$show_view_id'"
    ));
    if ($view_purchase) {
        $view_details = mysqli_query(
            $conn,
            "SELECT d.*, p.product_name FROM purchase_details d
             INNER JOIN products p ON d.product_id = p.id
             WHERE d.purchase_id='$show_view_id'"
        );
    }
}

$page_title = "Purchase Management";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <form method="GET" class="flex flex-wrap items-center gap-3 flex-1">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search supplier or invoice..." class="form-input flex-1 min-w-[200px]">
                            <select name="status" class="form-input w-auto">
                                <option value="">All Status</option>
                                <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="Unpaid" <?= $status_filter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                            <button class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn btn-outline">Reset</a>
                        </form>
                        <a href="add.php" class="btn btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Purchase
                        </a>
                    </div>

                    <!-- Table -->
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice</th>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th class="text-right">Amount</th>
                                        <th>Payment</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): $count = 1;
                                        while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?= $count++ ?></td>
                                                <td class="font-semibold"><?= htmlspecialchars($row['invoice_no'] ?? '#' . $row['id']) ?></td>
                                                <td><?= $row['purchase_date'] ?></td>
                                                <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                                                <td class="text-right font-bold text-green-600"><?= number_format($row['total_amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge <?= $row['payment_status'] === 'Paid' ? 'badge-success' : 'badge-danger' ?>">
                                                        <span class="badge-dot"></span>
                                                        <?= $row['payment_status'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-group justify-center">
                                                        <a href="?view_id=<?= $row['id'] ?>" class="btn btn-sm bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg">View</a>
                                                        <?php if ($is_admin): ?>
                                                            <a href="?edit_id=<?= $row['id'] ?>" class="btn btn-sm bg-green-50 text-green-600 hover:bg-green-100 rounded-lg">Edit</a>
                                                            <button onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-sm bg-red-50 text-red-600 hover:bg-red-100 rounded-lg">Delete</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-12">
                                                <div class="empty-state">
                                                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                    </svg>
                                                    <h3>No purchases found</h3>
                                                    <p>Create your first purchase to get started.</p>
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

    <!-- ============ DELETE CONFIRM MODAL ============ -->
    <div id="deleteModal" class="modal-overlay hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200">Delete Purchase</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Stock will be rolled back. This cannot be undone.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
                <a href="#" id="deleteConfirmLink" class="btn btn-danger flex-1 justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete
                </a>
            </div>
        </div>
    </div>

    <!-- ============ VIEW MODAL ============ -->
    <?php if ($view_purchase): ?>
        <div id="viewModal" class="modal-overlay">
            <div class="bg-white rounded-2xl p-6 lg:p-8 w-full max-w-3xl relative mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <button onclick="window.location.href='index.php'" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 dark:text-gray-300 text-2xl leading-none">&times;</button>

                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Purchase Invoice</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">#<?= htmlspecialchars($view_purchase['invoice_no'] ?? 'ID: ' . $view_purchase['id']) ?></p>
                    </div>
                    <span class="badge <?= $view_purchase['payment_status'] === 'Paid' ? 'badge-success' : 'badge-danger' ?>">
                        <span class="badge-dot"></span><?= $view_purchase['payment_status'] ?>
                    </span>
                </div>

                <hr class="mb-6">

                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Supplier</h4>
                        <p class="font-medium"><?= htmlspecialchars($view_purchase['supplier_name']) ?></p>
                        <?php if ($view_purchase['phone']): ?><p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($view_purchase['phone']) ?></p><?php endif; ?>
                        <?php if ($view_purchase['address']): ?><p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($view_purchase['address']) ?></p><?php endif; ?>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Details</h4>
                        <p class="text-sm">Date: <span class="font-medium"><?= $view_purchase['purchase_date'] ?></span></p>
                        <p class="text-sm">Created: <span class="font-medium"><?= $view_purchase['created_at'] ?></span></p>
                    </div>
                </div>

                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Items</h4>
                <table class="data-table mb-6">
                    <thead>
                        <tr>
                            <th class="text-left">Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Price</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($view_details)): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($row['product_name']) ?></td>
                                <td class="text-center"><?= $row['quantity'] ?></td>
                                <td class="text-center"><?= number_format($row['purchase_price'], 2) ?></td>
                                <td class="text-right font-semibold">$<?= number_format($row['subtotal'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <div class="text-right border-t pt-4">
                    <p class="text-lg text-gray-500 dark:text-gray-400">Total Amount</p>
                    <p class="text-3xl font-extrabold text-indigo-600"><?= number_format($view_purchase['total_amount'], 2) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ EDIT MODAL ============ -->
    <?php if ($edit_purchase && $edit_details): ?>
        <div id="editModal" class="modal-overlay">
            <div class="bg-white rounded-2xl p-6 lg:p-8 w-full max-w-4xl relative mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <a href="index.php" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 dark:text-gray-300 text-2xl leading-none">&times;</a>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Edit Purchase #<?= htmlspecialchars($edit_purchase['invoice_no'] ?? $edit_purchase['id']) ?></h2>

                <form method="POST">
                    <input type="hidden" name="id" value="<?= $edit_purchase['id'] ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-input">
                                <?php mysqli_data_seek($all_suppliers, 0);
                                while ($s = mysqli_fetch_assoc($all_suppliers)): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $edit_purchase['supplier_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['supplier_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-input">
                                <option value="Paid" <?= $edit_purchase['payment_status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="Unpaid" <?= $edit_purchase['payment_status'] === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                        </div>
                    </div>

                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Products</h4>
                    <div class="overflow-x-auto mb-6">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="text-left">Product</th>
                                    <th class="text-center w-28">Quantity</th>
                                    <th class="text-center w-36">Price</th>
                                    <th class="text-right w-32">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $edit_total = 0;
                                while ($row = mysqli_fetch_assoc($edit_details)): $edit_total += $row['subtotal']; ?>
                                    <tr>
                                        <td>
                                            <select name="product_id[]" class="form-input text-sm">
                                                <?php mysqli_data_seek($all_products, 0);
                                                while ($p = mysqli_fetch_assoc($all_products)): ?>
                                                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $row['product_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p['product_name']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <input type="hidden" name="detail_id[]" value="<?= $row['id'] ?>">
                                        </td>
                                        <td><input type="number" name="quantity[]" value="<?= $row['quantity'] ?>" min="0" class="form-input text-sm text-center"></td>
                                        <td><input type="number" name="purchase_price[]" value="<?= $row['purchase_price'] ?>" step="0.01" min="0" class="form-input text-sm text-center"></td>
                                        <td class="text-right font-semibold"><?= number_format($row['subtotal'], 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <span class="text-lg font-bold text-gray-800 dark:text-gray-200">Total: <span class="text-indigo-600"><?= number_format($edit_total, 2) ?></span></span>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button name="update" class="btn btn-primary">Update Purchase</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/footer.php"; ?>

    <script>
        function confirmDelete(id) {
            document.getElementById('deleteConfirmLink').href = '?delete_id=' + id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>

</html>