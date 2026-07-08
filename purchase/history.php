<?php
include "../includes/auth_check.php";
include "../config/database.php";
include "../config/helpers.php";
if (!isAdmin() && !isStaff()) {
    header("Location: ../dashboard/index.php");
    exit;
}

// ============ DELETE PURCHASE ============
if (isset($_GET['delete_id']) && isAdmin()) {
    $id = (int)$_GET['delete_id'];
    $details = mysqli_query($conn, "SELECT * FROM purchase_details WHERE purchase_id='$id'");
    while ($row = mysqli_fetch_assoc($details)) {
        $pid = $row['product_id'];
        $qty = $row['quantity'];
        mysqli_query($conn, "UPDATE products SET quantity = quantity - $qty WHERE id='$pid'");
    }
    mysqli_query($conn, "DELETE FROM purchase_details WHERE purchase_id='$id'");
    mysqli_query($conn, "DELETE FROM purchases WHERE id='$id'");
    header("Location: history.php?success=deleted");
    exit;
}

// Filters
$search = $_GET['search'] ?? '';
$supplier = $_GET['supplier'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT p.*, s.supplier_name
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE 1";

if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $sql .= " AND p.invoice_no LIKE '%$safe%'";
}
if ($supplier !== '') {
    $safe = mysqli_real_escape_string($conn, $supplier);
    $sql .= " AND s.supplier_name LIKE '%$safe%'";
}
if ($payment_status !== '') {
    $safe = mysqli_real_escape_string($conn, $payment_status);
    $sql .= " AND p.payment_status = '$safe'";
}
if ($date_from !== '') {
    $sql .= " AND DATE(p.purchase_date) >= '$date_from'";
}
if ($date_to !== '') {
    $sql .= " AND DATE(p.purchase_date) <= '$date_to'";
}

$sql .= " ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);

// Summary stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total_purchases, COALESCE(SUM(total_amount), 0) AS total_spent FROM purchases"));

$page_title = "Purchase History";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - Smart Inventory</title>
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
                        <div class="flex gap-2">
                            <button onclick="exportExcel()" class="btn btn-outline gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Export Excel
                            </button>
                            <a href="add.php" class="btn btn-primary gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                New Purchase
                            </a>
                        </div>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl flex items-start gap-3 shadow-sm">
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm font-medium">Purchase deleted successfully.</span>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Purchases</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100"><?= number_format($stats['total_purchases']) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Spent</p>
                                    <p class="text-xl font-bold text-emerald-600"><?= number_format($stats['total_spent']) ?> Ks</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Average Purchase</p>
                                    <p class="text-xl font-bold text-blue-600">
                                        <?= $stats['total_purchases'] > 0 ? number_format($stats['total_spent'] / $stats['total_purchases']) : '0' ?> Ks
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Today's Purchases</p>
                                    <p class="text-xl font-bold text-amber-600">
                                        <?php
                                        $today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM purchases WHERE DATE(purchase_date) = CURDATE()"));
                                        echo number_format($today['cnt']);
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search & Filter -->
                    <form method="GET" class="filter-bar mb-6">
                        <div class="flex-1 min-w-[200px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">Invoice No</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search invoice..." class="form-input text-sm">
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">Supplier</label>
                            <input type="text" name="supplier" value="<?= htmlspecialchars($supplier) ?>" placeholder="Supplier name..." class="form-input text-sm">
                        </div>
                        <div class="min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">Payment</label>
                            <select name="payment_status" class="form-input text-sm">
                                <option value="">All Status</option>
                                <option value="Paid" <?= $payment_status === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="Unpaid" <?= $payment_status === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                        </div>
                        <div class="min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">From Date</label>
                            <input type="date" name="date_from" value="<?= $date_from ?>" class="form-input text-sm">
                        </div>
                        <div class="min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">To Date</label>
                            <input type="date" name="date_to" value="<?= $date_to ?>" class="form-input text-sm">
                        </div>
                        <div class="flex gap-2 items-end">
                            <button class="btn btn-primary text-sm">Search</button>
                            <a href="history.php" class="btn btn-outline text-sm">Reset</a>
                        </div>
                    </form>

                    <!-- Purchases Table -->
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice No</th>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th class="text-right">Amount</th>
                                        <th>Payment</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): $count = 1;
                                        while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td class="text-gray-400 font-mono"><?= $count++ ?></td>
                                                <td class="font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($row['invoice_no'] ?? '#' . $row['id']) ?></td>
                                                <td class="text-gray-600 dark:text-gray-400"><?= date('d M Y, h:i A', strtotime($row['purchase_date'])) ?></td>
                                                <td class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($row['supplier_name'] ?? '-') ?></td>
                                                <td class="text-right font-bold text-emerald-600"><?= number_format($row['total_amount'], 2) ?> Ks</td>
                                                <td>
                                                    <?php
                                                    $status = $row['payment_status'] ?? 'Unpaid';
                                                    $badge = $status === 'Paid' ? 'badge-success' : 'badge-danger';
                                                    ?>
                                                    <span class="badge <?= $badge ?>">
                                                        <span class="badge-dot"></span>
                                                        <?= $status ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-group justify-center">
                                                        <a href="?view_id=<?= $row['id'] ?>" class="btn btn-sm bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg">View</a>
                                                        <?php if (isAdmin()): ?>
                                                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['invoice_no'] ?? '#' . $row['id'])) ?>')" class="btn btn-sm bg-red-50 text-red-600 hover:bg-red-100 rounded-lg">Delete</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-16">
                                                <div class="empty-state">
                                                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                    </svg>
                                                    <h3>No purchases found</h3>
                                                    <p>No purchases match your filters. Try adjusting the search criteria.</p>
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

    <!-- View Modal -->
    <?php if (isset($_GET['view_id'])):
        $view_id = (int)$_GET['view_id'];
        $view_purchase = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT pu.*, s.supplier_name, s.phone, s.address
             FROM purchases pu
             LEFT JOIN suppliers s ON pu.supplier_id = s.id
             WHERE pu.id='$view_id'"
        ));
        if ($view_purchase):
            $view_details = mysqli_query(
                $conn,
                "SELECT d.*, p.product_name FROM purchase_details d
                 INNER JOIN products p ON d.product_id = p.id
                 WHERE d.purchase_id='$view_id'"
            );
    ?>
            <div id="viewModal" class="modal-overlay">
                <div class="bg-white rounded-2xl p-6 lg:p-8 w-full max-w-3xl relative mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                    <button onclick="window.location.href='history.php'" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 dark:text-gray-300 text-2xl leading-none">&times;</button>

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
                            <p class="text-sm">Created: <span class="font-medium"><?= $view_purchase['created_at'] ?? '-' ?></span></p>
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
                                    <td class="text-right font-semibold text-emerald-600"><?= number_format($row['subtotal'], 2) ?> Ks</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <div class="text-right border-t pt-4">
                        <p class="text-lg text-gray-500 dark:text-gray-400">Total Amount</p>
                        <p class="text-3xl font-extrabold text-indigo-600"><?= number_format($view_purchase['total_amount'], 2) ?> Ks</p>
                    </div>
                </div>
            </div>
    <?php endif;
    endif; ?>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl" style="animation: modalIn 0.2s ease-out;">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200">Delete Purchase</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" id="deleteInvoiceText">This purchase will be permanently deleted and stock will be rolled back.</p>
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

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/footer.php"; ?>

    <script>
        function confirmDelete(id, invoice) {
            document.getElementById('deleteConfirmLink').href = 'history.php?delete_id=' + id;
            document.getElementById('deleteInvoiceText').textContent = 'Delete purchase ' + invoice + '? Stock will be rolled back.';
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function exportExcel() {
            const rows = [];
            rows.push(['Purchase History Report']);
            rows.push([]);
            rows.push(['Summary']);
            rows.push(['Total Purchases', <?= $stats['total_purchases'] ?>]);
            rows.push(['Total Spent', <?= $stats['total_spent'] ?>]);
            rows.push(['Average Purchase', <?= $stats['total_purchases'] > 0 ? round($stats['total_spent'] / $stats['total_purchases']) : 0 ?>]);
            rows.push([]);
            rows.push(['#', 'Invoice No', 'Date', 'Supplier', 'Amount', 'Payment Status']);
            <?php
            mysqli_data_seek($result, 0);
            $row_num = 1;
            while ($row = mysqli_fetch_assoc($result)):
            ?>
            rows.push([<?= $row_num++ ?>, '<?= addslashes($row['invoice_no'] ?? '#' . $row['id']) ?>', '<?= date('d M Y', strtotime($row['purchase_date'])) ?>', '<?= addslashes($row['supplier_name'] ?? '-') ?>', <?= $row['total_amount'] ?>, '<?= $row['payment_status'] ?>']);
            <?php endwhile; ?>

            const csv = rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'purchase_history_<?= date('Y-m-d') ?>.csv';
            link.click();
        }
    </script>
</body>

</html>