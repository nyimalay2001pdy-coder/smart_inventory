<?php
include "../includes/auth_check.php";
include "../config/database.php";
include "../config/helpers.php";
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

// ============ AJAX INVOICE DETAILS ============
if (isset($_GET['view_id'])) {
    header('Content-Type: application/json');
    $vid = (int)$_GET['view_id'];
    $sale = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT s.*, u.name AS cashier_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = $vid
    "));
    if (!$sale) { echo json_encode(['error' => 'Sale not found']); exit; }
    $details = mysqli_query($conn, "
        SELECT sd.*, p.product_name
        FROM sale_details sd
        JOIN products p ON sd.product_id = p.id
        WHERE sd.sale_id = $vid
        ORDER BY sd.id ASC
    ");
    $items = [];
    while ($d = mysqli_fetch_assoc($details)) $items[] = $d;
    echo json_encode(['sale' => $sale, 'items' => $items]);
    exit;
}

// Filters
$search = $_GET['search'] ?? '';
$customer = $_GET['customer'] ?? '';
$cashier = $_GET['cashier'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT s.*, u.name AS cashier_name,
               COALESCE(d.items_count, 0) AS items_count,
               COALESCE(d.total_qty, 0) AS total_qty,
               COALESCE(d.subtotal, 0) AS subtotal
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN (
            SELECT sale_id, COUNT(*) AS items_count, SUM(quantity) AS total_qty, SUM(subtotal) AS subtotal
            FROM sale_details GROUP BY sale_id
        ) d ON d.sale_id = s.id
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

// Overall stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total_sales, COALESCE(SUM(grand_total), 0) AS total_revenue FROM sales"));

// Today's sales count
$today_stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count, COALESCE(SUM(grand_total), 0) AS revenue FROM sales WHERE DATE(sale_date) = CURDATE()"));

// Filtered period stats (for the current filter range, for summary cards)
$period_sql = "SELECT COUNT(*) AS total_sales, COALESCE(SUM(grand_total), 0) AS total_revenue FROM sales WHERE 1";
if ($date_from !== '') $period_sql .= " AND DATE(sale_date) >= '$date_from'";
if ($date_to !== '') $period_sql .= " AND DATE(sale_date) <= '$date_to'";
$period_stats = mysqli_fetch_assoc(mysqli_query($conn, $period_sql));

$avg_sale = $period_stats['total_sales'] > 0 ? round($period_stats['total_revenue'] / $period_stats['total_sales']) : 0;

// Cashier list for filter dropdown
$cashiers = mysqli_query($conn, "SELECT DISTINCT u.id, u.name FROM sales s JOIN users u ON s.user_id = u.id WHERE u.name IS NOT NULL ORDER BY u.name");

$page_title = "Sales History";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .table-header-sticky th { position: sticky; top: 0; z-index: 10; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    <?php if (isset($_GET['success'])): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl flex items-start gap-3 shadow-sm">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm font-medium">Sale deleted successfully.</span>
                    </div>
                    <?php endif; ?>

                    <div class="flex gap-2 mb-6">
                        <button onclick="exportExcel()" class="btn btn-outline gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export Excel
                        </button>
                        <a href="pos.php" class="btn btn-primary gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            New Sale
                        </a>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-4">
                                <div class="w-11 h-11 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Today's Sales</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-0.5"><?= number_format($today_stats['count']) ?></p>
                                    <p class="text-sm font-semibold text-amber-600"><?= number_format($today_stats['revenue']) ?> Ks</p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-4">
                                <div class="w-11 h-11 bg-emerald-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Revenue</p>
                                    <p class="text-xl font-bold text-emerald-600 mt-0.5"><?= number_format($period_stats['total_revenue']) ?> Ks</p>
                                    <p class="text-sm text-gray-400"><?= $date_from || $date_to ? 'Filtered period' : 'All time' ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-4">
                                <div class="w-11 h-11 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Transactions</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-0.5"><?= number_format($period_stats['total_sales']) ?></p>
                                    <p class="text-sm text-gray-400"><?= $date_from || $date_to ? 'Filtered period' : 'All time' ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5">
                            <div class="flex items-center gap-4">
                                <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Average Sale</p>
                                    <p class="text-xl font-bold text-blue-600 mt-0.5"><?= number_format($avg_sale) ?> Ks</p>
                                    <p class="text-sm text-gray-400">Per transaction</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <form method="GET" class="filter-bar mb-6">
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">From Date</label>
                            <input type="date" name="date_from" value="<?= $date_from ?>" class="form-input text-sm">
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">To Date</label>
                            <input type="date" name="date_to" value="<?= $date_to ?>" class="form-input text-sm">
                        </div>
                        <div class="min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">Payment Method</label>
                            <select name="payment_method" class="form-input text-sm">
                                <option value="">All Methods</option>
                                <option value="Cash" <?= $payment_method === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Card" <?= $payment_method === 'Card' ? 'selected' : '' ?>>Card</option>
                                <option value="Transfer" <?= $payment_method === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                            </select>
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">Cashier</label>
                            <select name="cashier" class="form-input text-sm">
                                <option value="">All Cashiers</option>
                                <?php mysqli_data_seek($cashiers, 0); while ($ca = mysqli_fetch_assoc($cashiers)): ?>
                                <option value="<?= htmlspecialchars($ca['name']) ?>" <?= $cashier === $ca['name'] ? 'selected' : '' ?>><?= htmlspecialchars($ca['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">Customer</label>
                            <input type="text" name="customer" value="<?= htmlspecialchars($customer) ?>" placeholder="Customer name..." class="form-input text-sm">
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">Search Invoice</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Invoice no..." class="form-input text-sm">
                        </div>
                        <div class="flex gap-2 items-end">
                            <button class="btn btn-primary text-sm">Search</button>
                            <a href="history.php" class="btn btn-outline text-sm">Reset</a>
                        </div>
                    </form>

                    <!-- Sales Table -->
                    <div class="card overflow-hidden shadow-sm border border-gray-200 rounded-xl">
                        <div class="overflow-x-auto">
                            <table class="data-table w-full">
                                <thead class="bg-gray-100 border-b border-gray-200">
                                    <tr>
                                        <th class="w-12">No</th>
                                        <th>Invoice No</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Cashier</th>
                                        <th class="text-right">Grand Total</th>
                                        <th>Payment</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): $count = 1; while ($row = mysqli_fetch_assoc($result)): 
                                        $method = $row['payment_method'] ?? 'Cash';
                                        $method_badge = match ($method) {
                                            'Card' => 'badge-info',
                                            'Transfer' => 'badge-purple',
                                            default => 'badge-success',
                                        };
                                    ?>
                                    <tr class="hover:bg-indigo-50/40 transition-colors border-b border-gray-100 last:border-0">
                                        <td class="text-gray-400 font-mono text-sm"><?= $count++ ?></td>
                                        <td class="font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($row['invoice_no']) ?></td>
                                        <td class="text-gray-600 dark:text-gray-400 text-sm whitespace-nowrap"><?= date('d M Y, h:i A', strtotime($row['sale_date'])) ?></td>
                                        <td class="text-gray-700 dark:text-gray-300 max-w-[130px] truncate" title="<?= htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer') ?>"><?= htmlspecialchars($row['customer_name'] ?? 'Walk-in') ?></td>
                                        <td class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($row['cashier_name'] ?? '—') ?></td>
                                        <td class="text-right font-bold text-emerald-600"><?= number_format((float)$row['grand_total']) ?> Ks</td>
                                        <td>
                                            <span class="badge <?= $method_badge ?> whitespace-nowrap text-xs">
                                                <span class="badge-dot"></span>
                                                <?= $method ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-1.5 justify-center">
                                                <button onclick="viewInvoice(<?= $row['id'] ?>)" class="btn btn-sm bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg font-medium transition text-xs px-3">View</button>
                                                <a href="invoice.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm bg-gray-50 text-gray-600 dark:text-gray-400 hover:bg-gray-100 rounded-lg font-medium transition text-xs px-3">Print</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-16">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-14 h-14 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                                                <h3 class="text-base font-semibold text-gray-500 dark:text-gray-400">No sales found</h3>
                                                <p class="text-sm text-gray-400 mt-1">No sales match your filters. Try adjusting the search criteria.</p>
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

    <!-- Invoice Detail Modal -->
    <div id="invoiceModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40" onclick="closeInvoiceModal()"></div>
        <div class="flex items-center justify-center min-h-full p-4">
            <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-[slideUp_0.2s_ease-out] max-h-[90vh] flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Invoice Details</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400" id="modalInvoiceNo">INV-XXXX</p>
                        </div>
                    </div>
                    <button onclick="closeInvoiceModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center transition">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-6" id="modalBody">
                    <div class="text-center py-8 text-gray-400">
                        <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                        <p class="text-sm">Loading invoice...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/footer.php"; ?>

    <script>
    // ============ Invoice Modal ============
    function viewInvoice(id) {
        const modal = document.getElementById('invoiceModal');
        const body = document.getElementById('modalBody');
        modal.classList.remove('hidden');
        body.innerHTML = '<div class="text-center py-8 text-gray-400"><div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div><p class="text-sm">Loading invoice...</p></div>';

        fetch('history.php?view_id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) { body.innerHTML = '<p class="text-center text-red-500 py-8">' + data.error + '</p>'; return; }
                var s = data.sale, items = data.items;
                document.getElementById('modalInvoiceNo').textContent = s.invoice_no;

                var subtotal = 0, totalQty = 0;
                for (var i = 0; i < items.length; i++) {
                    subtotal += parseFloat(items[i].subtotal) || 0;
                    totalQty += parseInt(items[i].quantity) || 0;
                }
                var discount = parseFloat(s.discount) || 0;
                var grandTotal = parseFloat(s.grand_total) || 0;

                var html = '';
                // Info header
                html += '<div class="grid grid-cols-2 gap-4 mb-5 pb-5 border-b border-gray-100">';
                html += '<div><p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice No</p><p class="text-sm font-bold text-gray-900 dark:text-gray-100 mt-0.5">' + s.invoice_no + '</p></div>';
                html += '<div class="text-right"><p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</p><p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">' + new Date(s.sale_date).toLocaleString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) + '</p></div>';
                html += '<div><p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</p><p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">' + (s.customer_name || 'Walk-in Customer') + '</p></div>';
                html += '<div class="text-right"><p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cashier</p><p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">' + (s.cashier_name || '—') + '</p></div>';
                html += '</div>';

                // Items table
                html += '<div class="overflow-x-auto mb-5"><table class="w-full text-sm">';
                html += '<thead><tr class="bg-gray-50 border-b border-gray-200"><th class="text-left py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">#</th><th class="text-left py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Product</th><th class="text-center py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Qty</th><th class="text-right py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Unit Price</th><th class="text-right py-2.5 px-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">Subtotal</th></tr></thead>';
                html += '<tbody>';
                for (var i = 0; i < items.length; i++) {
                    var it = items[i];
                    html += '<tr class="border-b border-gray-50 hover:bg-gray-50/50"><td class="py-2.5 px-3 text-gray-400 font-mono">' + (i + 1) + '</td><td class="py-2.5 px-3 font-medium text-gray-800 dark:text-gray-200">' + (it.product_name || 'Product #' + it.product_id) + '</td><td class="py-2.5 px-3 text-center font-semibold text-gray-800 dark:text-gray-200">' + it.quantity + '</td><td class="py-2.5 px-3 text-right text-gray-700 dark:text-gray-300">' + Number(it.selling_price).toLocaleString() + ' Ks</td><td class="py-2.5 px-3 text-right font-semibold text-gray-800 dark:text-gray-200">' + Number(it.subtotal).toLocaleString() + ' Ks</td></tr>';
                }
                html += '</tbody></table></div>';

                // Totals
                html += '<div class="border-t border-gray-200 pt-4 space-y-1.5">';
                html += '<div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Subtotal</span><span class="font-semibold text-gray-800 dark:text-gray-200">' + subtotal.toLocaleString() + ' Ks</span></div>';
                if (discount > 0) html += '<div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Discount</span><span class="font-semibold text-red-500">- ' + discount.toLocaleString() + ' Ks</span></div>';
                html += '<div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Tax</span><span class="text-gray-400">— Ks</span></div>';
                html += '<div class="flex justify-between text-base pt-2 border-t border-gray-100"><span class="font-bold text-gray-900 dark:text-gray-100">Grand Total</span><span class="font-bold text-emerald-600">' + grandTotal.toLocaleString() + ' Ks</span></div>';
                html += '<div class="flex justify-between text-sm pt-1"><span class="text-gray-500 dark:text-gray-400">Payment Method</span><span class="font-semibold text-gray-700 dark:text-gray-300">' + (s.payment_method || 'Cash') + '</span></div>';
                html += '</div>';

                body.innerHTML = html;
            })
            .catch(function() {
                body.innerHTML = '<p class="text-center text-red-500 py-8">Failed to load invoice. Please try again.</p>';
            });
    }

    function closeInvoiceModal() {
        document.getElementById('invoiceModal').classList.add('hidden');
    }

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeInvoiceModal();
    });

    // ============ Export Excel ============
    function exportExcel() {
        const rows = [];
        rows.push(['Sales Report']);
        rows.push([]);
        rows.push(['Summary']);
        rows.push(['Today\'s Sales', <?= $today_stats['count'] ?>]);
        rows.push(['Total Revenue', <?= $period_stats['total_revenue'] ?>]);
        rows.push(['Total Transactions', <?= $period_stats['total_sales'] ?>]);
        rows.push(['Average Sale', <?= $avg_sale ?>]);
        rows.push([]);
        rows.push(['No', 'Invoice No', 'Date', 'Customer', 'Cashier', 'Grand Total', 'Payment Method']);
        <?php
        mysqli_data_seek($result, 0);
        $row_num = 1;
        while ($row = mysqli_fetch_assoc($result)):
        ?>
        rows.push([<?= $row_num++ ?>, '<?= addslashes($row['invoice_no']) ?>', '<?= date('d M Y, h:i A', strtotime($row['sale_date'])) ?>', '<?= addslashes($row['customer_name'] ?? 'Walk-in') ?>', '<?= addslashes($row['cashier_name'] ?? '—') ?>', <?= (float)$row['grand_total'] ?>, '<?= $row['payment_method'] ?? 'Cash' ?>']);
        <?php endwhile; ?>

        const csv = rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'sales_report_<?= date('Y-m-d') ?>.csv';
        link.click();
    }
    </script>
</body>
</html>
