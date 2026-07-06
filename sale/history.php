<?php
include "../includes/auth_check.php";
include "../config/database.php";
include "../config/helpers.php";
include "../includes/header.php";

if (!isAdmin() && !isStaff()) {
    header("Location: ../dashboard/index.php");
    exit;
}

// ============ DELETE SALE ============
if (isset($_GET['delete_id']) && isAdmin()) {
    $id = (int)$_GET['delete_id'];
    $details = mysqli_query($conn, "SELECT * FROM sale_details WHERE sale_id='$id'");
    while ($row = mysqli_fetch_assoc($details)) {
        $pid = $row['product_id'];
        $qty = $row['quantity'];
        mysqli_query($conn, "UPDATE products SET quantity = quantity + $qty WHERE id='$pid'");
    }
    mysqli_query($conn, "DELETE FROM payments WHERE sale_id='$id'");
    mysqli_query($conn, "DELETE FROM sale_details WHERE sale_id='$id'");
    mysqli_query($conn, "DELETE FROM sales WHERE id='$id'");
    header("Location: history.php?success=deleted");
    exit;
}

// Filters
$search = $_GET['search'] ?? '';
$customer = $_GET['customer'] ?? '';
$cashier = $_GET['cashier'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT s.*, u.name AS cashier_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE 1";

if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $sql .= " AND s.invoice_no LIKE '%$safe%'";
}
if ($customer !== '') {
    $safe = mysqli_real_escape_string($conn, $customer);
    $sql .= " AND s.customer_name LIKE '%$safe%'";
}
if ($cashier !== '') {
    $safe = mysqli_real_escape_string($conn, $cashier);
    $sql .= " AND u.name LIKE '%$safe%'";
}
if ($payment_method !== '') {
    $safe = mysqli_real_escape_string($conn, $payment_method);
    $sql .= " AND s.payment_method = '$safe'";
}
if ($date_from !== '') {
    $sql .= " AND DATE(s.sale_date) >= '$date_from'";
}
if ($date_to !== '') {
    $sql .= " AND DATE(s.sale_date) <= '$date_to'";
}

$sql .= " ORDER BY s.id DESC";
$result = mysqli_query($conn, $sql);

// Summary stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total_sales, COALESCE(SUM(grand_total), 0) AS total_revenue FROM sales"));

$page_title = "Sales History";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Header -->
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <div>
                            <nav class="flex items-center gap-1.5 text-sm text-gray-400 mb-1">
                                <a href="../dashboard/index.php" class="hover:text-indigo-600 transition-colors">Dashboard</a>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="text-gray-700 font-medium">Sales History</span>
                            </nav>
                            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 tracking-tight">Sales History</h1>
                            <p class="text-sm text-gray-500 mt-0.5">View and manage all completed sales</p>
                        </div>
                        <a href="pos.php" class="btn btn-primary gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            New Sale
                        </a>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl flex items-start gap-3 shadow-sm">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm font-medium">Sale deleted successfully.</span>
                    </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Sales</p>
                                    <p class="text-xl font-bold text-gray-900"><?= number_format($stats['total_sales']) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Revenue</p>
                                    <p class="text-xl font-bold text-emerald-600"><?= number_format($stats['total_revenue']) ?> Ks</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Average Sale</p>
                                    <p class="text-xl font-bold text-blue-600">
                                        <?= $stats['total_sales'] > 0 ? number_format($stats['total_revenue'] / $stats['total_sales']) : '0' ?> Ks
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Today's Sales</p>
                                    <p class="text-xl font-bold text-amber-600">
                                        <?php
                                        $today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM sales WHERE DATE(sale_date) = CURDATE()"));
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
                            <label class="text-xs font-semibold text-gray-500 mb-1 block">Invoice No</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search invoice..." class="form-input text-sm">
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 mb-1 block">Customer</label>
                            <input type="text" name="customer" value="<?= htmlspecialchars($customer) ?>" placeholder="Customer name..." class="form-input text-sm">
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 mb-1 block">Cashier</label>
                            <input type="text" name="cashier" value="<?= htmlspecialchars($cashier) ?>" placeholder="Cashier name..." class="form-input text-sm">
                        </div>
                        <div class="min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 mb-1 block">Payment</label>
                            <select name="payment_method" class="form-input text-sm">
                                <option value="">All Methods</option>
                                <option value="Cash" <?= $payment_method === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Card" <?= $payment_method === 'Card' ? 'selected' : '' ?>>Card</option>
                                <option value="Transfer" <?= $payment_method === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                            </select>
                        </div>
                        <div class="min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 mb-1 block">From Date</label>
                            <input type="date" name="date_from" value="<?= $date_from ?>" class="form-input text-sm">
                        </div>
                        <div class="min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 mb-1 block">To Date</label>
                            <input type="date" name="date_to" value="<?= $date_to ?>" class="form-input text-sm">
                        </div>
                        <div class="flex gap-2 items-end">
                            <button class="btn btn-primary text-sm">Search</button>
                            <a href="history.php" class="btn btn-outline text-sm">Reset</a>
                        </div>
                    </form>

                    <!-- Sales Table -->
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice No</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Cashier</th>
                                        <th class="text-right">Amount</th>
                                        <th>Payment</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): $count = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td class="text-gray-400 font-mono"><?= $count++ ?></td>
                                        <td class="font-semibold text-gray-900"><?= htmlspecialchars($row['invoice_no']) ?></td>
                                        <td class="text-gray-600"><?= date('d M Y, h:i A', strtotime($row['sale_date'])) ?></td>
                                        <td class="text-gray-700"><?= htmlspecialchars($row['customer_name'] ?? 'Walk-in') ?></td>
                                        <td class="text-gray-700"><?= htmlspecialchars($row['cashier_name'] ?? 'Admin') ?></td>
                                        <td class="text-right font-bold text-emerald-600"><?= number_format($row['grand_total']) ?> Ks</td>
                                        <td>
                                            <?php
                                            $method = $row['payment_method'] ?? 'Cash';
                                            $badge = match ($method) {
                                                'Card' => 'badge-info',
                                                'Transfer' => 'badge-purple',
                                                default => 'badge-success',
                                            };
                                            ?>
                                            <span class="badge <?= $badge ?>">
                                                <span class="badge-dot"></span>
                                                <?= $method ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-group justify-center">
                                                <a href="invoice.php?id=<?= $row['id'] ?>" class="btn btn-sm bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg">View</a>
                                                <a href="invoice.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm bg-gray-50 text-gray-600 hover:bg-gray-100 rounded-lg">Print</a>
                                                <?php if (isAdmin()): ?>
                                                <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['invoice_no'])) ?>')" class="btn btn-sm bg-red-50 text-red-600 hover:bg-red-100 rounded-lg">Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-16">
                                            <div class="empty-state">
                                                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                                                <h3>No sales found</h3>
                                                <p>No sales match your filters. Try adjusting the search criteria.</p>
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl" style="animation: modalIn 0.2s ease-out;">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Delete Sale</h3>
                <p class="text-sm text-gray-500 mt-1" id="deleteInvoiceText">This sale will be permanently deleted and stock will be restored.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
                <a href="#" id="deleteConfirmLink" class="btn btn-danger flex-1 justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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
        document.getElementById('deleteInvoiceText').textContent = 'Delete sale ' + invoice + '? Stock will be restored.';
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
    </script>
</body>
</html>
