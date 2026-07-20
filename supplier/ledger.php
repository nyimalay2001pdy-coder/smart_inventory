<?php
include "../includes/auth_check.php";
requireAdmin();
include "../config/database.php";
include "../config/helpers.php";

$page_title = "Supplier Ledger";
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Search & filter params
$search       = trim($_GET['search'] ?? '');
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';

$supplier = null;
if ($selected_id > 0) {
    $supplier = fetchOne($conn, "SELECT * FROM suppliers WHERE id = ?", [$selected_id], "i");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ===== PRINT / PDF STATEMENT ===== */
        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { background: #fff !important; color: #000 !important; margin: 0; padding: 0; }
            .no-print, aside, header, .sidebar, nav, footer, .btn, .filter-bar, .toast-container { display: none !important; }
            .print-area { display: block !important; margin: 0 !important; padding: 1rem !important; max-width: 100% !important; width: 100% !important; box-shadow: none !important; border: none !important; }
            .print-area .card { box-shadow: none !important; border: none !important; border-radius: 0 !important; }
            .print-area .card-header { border-bottom: 2px solid #000 !important; }
            .print-area .card-body { padding: 0.75rem 0 !important; }
            .print-area table.data-table { font-size: 11px !important; }
            .print-area table.data-table th { background: #f0f0f0 !important; border-bottom: 2px solid #000 !important; padding: 6px 8px !important; }
            .print-area table.data-table td { padding: 5px 8px !important; border-bottom: 1px solid #ccc !important; }
            .print-area .badge { border: 1px solid #999 !important; background: #f5f5f5 !important; color: #333 !important; }
            .print-area .badge .badge-dot { background: #666 !important; }
            .print-area .text-red-600, .print-area .text-red-400 { color: #c00 !important; }
            .print-area .text-blue-600, .print-area .text-blue-400 { color: #006 !important; }
            .print-area .text-emerald-600, .print-area .text-emerald-400 { color: #060 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 1rem; }
            .print-header h1 { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
            .print-header p { font-size: 11px; color: #666; }
            .print-stamp { display: block !important; text-align: right; margin-top: 1.5rem; font-size: 11px; color: #888; }
        }
        @media screen {
            .print-area { display: block; }
            .print-header, .print-stamp { display: none; }
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6 no-print">
                <div class="max-w-7xl mx-auto">

                    <?php if ($supplier): ?>
                        <?php
                        // ── Read GET date range for transactions ──
                        $txn_from = $_GET['txn_from'] ?? '';
                        $txn_to   = $_GET['txn_to']   ?? '';

                        // ── Fetch all transactions for this supplier (oldest first for running balance) ──
                        $amtCol = getPaymentAmountCol($conn, 'purchase_payments');
                        $txn_sql = "
                            SELECT 'Purchase' AS type, p.invoice_no AS ref_no,
                                   p.purchase_date AS txn_date, p.total_amount AS amount
                            FROM purchases p WHERE p.supplier_id = $selected_id
                        ";
                        if ($txn_from !== '') $txn_sql .= " AND p.purchase_date >= '" . mysqli_real_escape_string($conn, $txn_from) . "'";
                        if ($txn_to !== '')   $txn_sql .= " AND p.purchase_date <= '" . mysqli_real_escape_string($conn, $txn_to)   . "'";

                        $txn_sql .= "
                            UNION ALL
                            SELECT 'Payment' AS type, pu.invoice_no AS ref_no,
                                   CAST(pp.payment_date AS DATE) AS txn_date, pp.{$amtCol} AS amount
                            FROM purchase_payments pp
                            INNER JOIN purchases pu ON pp.purchase_id = pu.id
                            WHERE pu.supplier_id = $selected_id
                        ";
                        if ($txn_from !== '') $txn_sql .= " AND CAST(pp.payment_date AS DATE) >= '" . mysqli_real_escape_string($conn, $txn_from) . "'";
                        if ($txn_to !== '')   $txn_sql .= " AND CAST(pp.payment_date AS DATE) <= '" . mysqli_real_escape_string($conn, $txn_to)   . "'";

                        $txn_sql .= " ORDER BY txn_date ASC";
                        $txn_result = mysqli_query($conn, $txn_sql);

                        $transactions = [];
                        if ($txn_result) {
                            while ($t = mysqli_fetch_assoc($txn_result)) {
                                $transactions[] = $t;
                            }
                        }
                        // Compute running balance (oldest → newest)
                        $running = 0;
                        foreach ($transactions as &$txn) {
                            if ($txn['type'] === 'Purchase') {
                                $running += (float)$txn['amount'];
                            } else {
                                $running -= (float)$txn['amount'];
                            }
                            $txn['running_balance'] = $running;
                        }
                        unset($txn);

                        // Reverse for display (newest first)
                        $display_txns = array_reverse($transactions);
                        ?>

                        <!-- ===== SUPPLIER LEDGER VIEW ===== -->
                        <!-- Print / PDF Buttons -->
                        <div class="flex items-center justify-between mb-6 no-print">
                            <div class="flex items-center gap-3">
                                <a href="ledger.php" class="btn btn-outline gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    Back
                                </a>
                                <div>
                                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Supplier Ledger</h1>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($supplier['supplier_name']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="window.print()" class="btn btn-outline gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Print
                                </button>
                                <button onclick="exportPDF()" class="btn btn-primary gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Export PDF
                                </button>
                            </div>
                        </div>

                        <div class="print-area">
                            <!-- Print Header (only visible when printing) -->
                            <div class="print-header">
                                <h1>Supplier Statement</h1>
                                <p><?= htmlspecialchars($supplier['supplier_name']) ?> — Generated <?= date('d M Y, h:i A') ?></p>
                            </div>

                            <!-- Current Balance Banner -->
                            <?php
                            $cur_bal = (float)($supplier['current_balance'] ?? 0);
                            $cur_type = $supplier['balance_type'] ?? 'Clear';
                            ?>
                            <div class="card mb-6">
                                <div class="card-body py-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 <?= $cur_type === 'Payable' ? 'bg-red-100 dark:bg-red-900/30' : ($cur_type === 'Advance' ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-emerald-100 dark:bg-emerald-900/30') ?>">
                                                <svg class="w-5 h-5 <?= $cur_type === 'Payable' ? 'text-red-600 dark:text-red-400' : ($cur_type === 'Advance' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400') ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current Balance</p>
                                                <p class="text-2xl font-extrabold <?= $cur_type === 'Payable' ? 'text-red-600 dark:text-red-400' : ($cur_type === 'Advance' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400') ?>">
                                                    <?= number_format($cur_bal, 2) ?> MMK
                                                </p>
                                            </div>
                                        </div>
                                        <span class="badge <?= $cur_type === 'Payable' ? 'badge-danger' : ($cur_type === 'Advance' ? 'badge-info' : 'badge-success') ?> text-sm">
                                            <span class="badge-dot"></span>
                                            <?= htmlspecialchars($cur_type) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Supplier Info Card -->
                            <div class="card mb-6">
                                <div class="card-header">
                                    <h2 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Supplier Information
                                    </h2>
                                </div>
                                <div class="card-body">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Name</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($supplier['supplier_name']) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Contact Person</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($supplier['contact_person'] ?? '-') ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Phone</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($supplier['phone'] ?? '-') ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Email</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($supplier['email'] ?? '-') ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Address</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($supplier['address'] ?? '-') ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Status</p>
                                            <?php if ($supplier['status'] === 'Active'): ?>
                                                <span class="badge badge-success"><span class="badge-dot"></span> Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger"><span class="badge-dot"></span> Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Date Range Filter (for transactions) -->
                            <div class="card mb-6 no-print">
                                <div class="card-body py-3">
                                    <form method="GET" class="flex flex-wrap items-end gap-3">
                                        <input type="hidden" name="id" value="<?= $selected_id ?>">
                                        <div>
                                            <label class="form-label text-xs">From</label>
                                            <input type="date" name="txn_from" value="<?= htmlspecialchars($txn_from) ?>" class="form-input w-auto text-sm">
                                        </div>
                                        <div>
                                            <label class="form-label text-xs">To</label>
                                            <input type="date" name="txn_to" value="<?= htmlspecialchars($txn_to) ?>" class="form-input w-auto text-sm">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                            Filter
                                        </button>
                                        <?php if ($txn_from !== '' || $txn_to !== ''): ?>
                                            <a href="ledger.php?id=<?= $selected_id ?>" class="btn btn-outline btn-sm gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                Clear
                                            </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>

                            <!-- Transaction History Table -->
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Transaction History
                                    </h2>
                                    <span class="text-xs text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full font-medium"><?= count($display_txns) ?> transaction(s)</span>
                                </div>
                                <div class="card-body">
                                    <?php if (count($display_txns) > 0): ?>
                                        <div class="table-wrap">
                                            <table class="data-table w-full">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Date</th>
                                                        <th>Reference</th>
                                                        <th>Type</th>
                                                        <th class="num">Amount</th>
                                                        <th class="num">Running Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $i = 1; foreach ($display_txns as $txn): ?>
                                                        <?php
                                                        $rb = $txn['running_balance'];
                                                        $rbClass = $rb > 0 ? 'text-red-600 dark:text-red-400' : ($rb < 0 ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400');
                                                        ?>
                                                        <tr>
                                                            <td><?= $i++ ?></td>
                                                            <td class="text-sm"><?= date('d M Y', strtotime($txn['txn_date'])) ?></td>
                                                            <td class="text-sm font-medium"><?= htmlspecialchars($txn['ref_no']) ?></td>
                                                            <td>
                                                                <?php if ($txn['type'] === 'Purchase'): ?>
                                                                    <span class="badge badge-info">
                                                                        <span class="badge-dot"></span>
                                                                        Purchase
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-success">
                                                                        <span class="badge-dot"></span>
                                                                        Payment
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="num text-sm font-semibold"><?= number_format((float)$txn['amount'], 2) ?></td>
                                                            <td class="num text-sm font-bold <?= $rbClass ?>"><?= number_format($rb, 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-12">
                                            <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                            </svg>
                                            <h3 class="text-lg font-semibold text-gray-500 dark:text-gray-400 mb-1">No transactions yet</h3>
                                            <p class="text-sm text-gray-400 dark:text-gray-500">Purchases and payments will appear here.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Print Stamp (only visible when printing) -->
                            <div class="print-stamp">
                                Printed on <?= date('d M Y, h:i A') ?> — Smart Inventory
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- ===== SUPPLIER LIST VIEW ===== -->
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Supplier Ledger</h1>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Select a supplier to view their ledger</p>
                            </div>
                        </div>

                        <!-- Search & Date Range Filter Bar -->
                        <div class="card mb-6">
                            <div class="card-body py-3">
                                <form method="GET" class="flex flex-wrap items-end gap-3">
                                    <div class="flex-1 min-w-[200px]">
                                        <label class="form-label text-xs">Search Suppliers</label>
                                        <div class="relative">
                                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or phone..." class="form-input pl-10 text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Date From</label>
                                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-input w-auto text-sm">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Date To</label>
                                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-input w-auto text-sm">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                        Filter
                                    </button>
                                    <?php if ($search !== '' || $date_from !== '' || $date_to !== ''): ?>
                                        <a href="ledger.php" class="btn btn-outline btn-sm gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Clear
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <?php
                        // ── Build supplier query with filters ──
                        $sup_params = ['Active'];
                        $sup_types  = "s";
                        $sup_where  = "WHERE s.status = ?";

                        if ($search !== '') {
                            $sup_where .= " AND (s.supplier_name LIKE ? OR s.phone LIKE ?)";
                            $sup_params[] = "%{$search}%";
                            $sup_params[] = "%{$search}%";
                            $sup_types  .= "ss";
                        }

                        if ($date_from !== '' || $date_to !== '') {
                            // Only show suppliers who had activity in the date range
                            $sup_where .= " AND s.id IN (
                                SELECT DISTINCT p.supplier_id FROM purchases p WHERE 1=1";
                            if ($date_from !== '') {
                                $sup_where .= " AND p.purchase_date >= ?";
                                $sup_params[] = $date_from;
                                $sup_types  .= "s";
                            }
                            if ($date_to !== '') {
                                $sup_where .= " AND p.purchase_date <= ?";
                                $sup_params[] = $date_to;
                                $sup_types  .= "s";
                            }
                            $sup_where .= ")";
                        }

                        $sup_sql = "SELECT s.* FROM suppliers s {$sup_where} ORDER BY s.supplier_name ASC";
                        $sup_stmt = mysqli_prepare($conn, $sup_sql);
                        if ($sup_stmt) {
                            mysqli_stmt_bind_param($sup_stmt, $sup_types, ...$sup_params);
                            mysqli_stmt_execute($sup_stmt);
                            $suppliers = mysqli_stmt_get_result($sup_stmt);
                        } else {
                            $suppliers = false;
                        }
                        ?>

                        <?php if ($suppliers && mysqli_num_rows($suppliers) > 0): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php while ($s = mysqli_fetch_assoc($suppliers)):
                                    $bt = $s['balance_type'] ?? 'Clear';
                                    $bal = (float)($s['current_balance'] ?? 0);
                                    $btClass = match($bt) {
                                        'Payable' => 'badge-danger',
                                        'Advance' => 'badge-info',
                                        default => 'badge-success'
                                    };
                                ?>
                                    <a href="ledger.php?id=<?= $s['id'] ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="card hover:shadow-md transition-shadow duration-200 group">
                                        <div class="card-body">
                                            <div class="flex items-start justify-between mb-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/50 rounded-full flex items-center justify-center flex-shrink-0">
                                                        <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400"><?= strtoupper(substr($s['supplier_name'], 0, 1)) ?></span>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                                            <?= htmlspecialchars($s['supplier_name']) ?>
                                                        </h3>
                                                        <?php if ($s['contact_person']): ?>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= htmlspecialchars($s['contact_person']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-indigo-500 transition-colors flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>

                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400"><?= htmlspecialchars($s['phone'] ?? '-') ?></span>
                                                <span class="badge <?= $btClass ?> text-[11px]"><?= htmlspecialchars($bt) ?></span>
                                            </div>

                                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Balance</span>
                                                <span class="text-sm font-bold <?= $bt === 'Payable' ? 'text-red-600 dark:text-red-400' : ($bt === 'Advance' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400') ?>">
                                                    <?= $bt === 'Clear' ? '0.00' : number_format($bal, 2) ?> MMK
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="text-center py-12">
                                        <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-500 dark:text-gray-400 mb-1">No active suppliers</h3>
                                        <p class="text-sm text-gray-400 dark:text-gray-500">Add suppliers to get started.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/footer.php"; ?>

    <?php if ($supplier): ?>
    <script>
        function exportPDF() {
            window.print();
        }
    </script>
    <?php endif; ?>
</body>

</html>
