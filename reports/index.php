<?php
include "../includes/auth_check.php";
requireAdmin();
include "../config/database.php";

$page_title = "Reports";
$tab = $_GET['tab'] ?? 'sales';

// Date filters
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$safe_from = mysqli_real_escape_string($conn, $from);
$safe_to = mysqli_real_escape_string($conn, $to);

// Sales Report
$sales_report = mysqli_query($conn, "
    SELECT s.*, u.name AS cashier
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE DATE(s.sale_date) BETWEEN '$safe_from' AND '$safe_to'
    ORDER BY s.sale_date DESC
");

$sales_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(grand_total), 0) AS total_revenue,
           COALESCE(SUM(paid_amount), 0) AS total_paid
    FROM sales
    WHERE DATE(sale_date) BETWEEN '$safe_from' AND '$safe_to'
"));

$sales_daily = mysqli_query($conn, "
    SELECT DATE(sale_date) AS day, COUNT(*) AS count, SUM(grand_total) AS total
    FROM sales
    WHERE DATE(sale_date) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY DATE(sale_date) ORDER BY day DESC
");

// Payment method summaries (from payments table)
$payment_summary = mysqli_query($conn, "
    SELECT p.payment_method, COALESCE(SUM(p.amount), 0) AS total
    FROM payments p
    JOIN sales s ON p.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY p.payment_method
");
$payment_totals = ['Cash' => 0, 'Card' => 0, 'Transfer' => 0];
while ($pt = mysqli_fetch_assoc($payment_summary)) {
    $payment_totals[$pt['payment_method']] = (float)$pt['total'];
}

// Legacy fallback: if no payments table records, use sales table
$has_payments = array_sum($payment_totals) > 0;
if (!$has_payments) {
    $legacy_pm = mysqli_query($conn, "
        SELECT payment_method, COALESCE(SUM(paid_amount), 0) AS total
        FROM sales
        WHERE DATE(sale_date) BETWEEN '$safe_from' AND '$safe_to'
        GROUP BY payment_method
    ");
    while ($lpm = mysqli_fetch_assoc($legacy_pm)) {
        if (isset($payment_totals[$lpm['payment_method']])) {
            $payment_totals[$lpm['payment_method']] = (float)$lpm['total'];
        }
    }
}

// Purchase Report
$purchase_report = mysqli_query($conn, "
    SELECT p.*, s.supplier_name
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.purchase_date BETWEEN '$safe_from' AND '$safe_to'
    ORDER BY p.purchase_date DESC
");

$purchase_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total, COALESCE(SUM(total_amount), 0) AS total_amount
    FROM purchases
    WHERE purchase_date BETWEEN '$safe_from' AND '$safe_to'
"));

// Profit Report
$profit_data = mysqli_query($conn, "
    SELECT DATE(s.sale_date) AS day,
           COUNT(DISTINCT s.id) AS sales_count,
           COALESCE(SUM(sd.subtotal), 0) AS revenue,
           COALESCE(SUM(sd.purchase_price * sd.quantity), 0) AS cost,
           COALESCE(SUM(sd.subtotal - (sd.purchase_price * sd.quantity)), 0) AS profit
    FROM sales s
    JOIN sale_details sd ON s.id = sd.sale_id
    WHERE DATE(s.sale_date) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY DATE(s.sale_date)
    ORDER BY day DESC
");

$profit_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(sd.subtotal), 0) AS total_revenue,
           COALESCE(SUM(sd.purchase_price * sd.quantity), 0) AS total_cost,
           COALESCE(SUM(sd.subtotal - (sd.purchase_price * sd.quantity)), 0) AS total_profit
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$safe_from' AND '$safe_to'
"));

$top_products = mysqli_query($conn, "
    SELECT p.product_name, SUM(sd.quantity) AS total_qty,
           SUM(sd.subtotal) AS total_revenue,
           SUM(sd.subtotal - (sd.purchase_price * sd.quantity)) AS total_profit
    FROM sale_details sd
    JOIN products p ON sd.product_id = p.id
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY sd.product_id
    ORDER BY total_qty DESC LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-6">
            <div class="flex justify-end mb-6">
                <button onclick="window.print()" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl hover:bg-indigo-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print Report
                </button>
            </div>

            <div class="flex gap-1 mb-6 bg-white p-1 rounded-xl shadow-sm w-fit">
                <a href="?tab=sales&from=<?= $from ?>&to=<?= $to ?>"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold <?= $tab == 'sales' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100' ?>">Sales Report</a>
                <a href="?tab=purchase&from=<?= $from ?>&to=<?= $to ?>"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold <?= $tab == 'purchase' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100' ?>">Purchase Report</a>
                <a href="?tab=profit&from=<?= $from ?>&to=<?= $to ?>"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold <?= $tab == 'profit' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100' ?>">Profit Report</a>
            </div>

            <form method="GET" class="bg-white p-4 rounded-xl shadow-sm flex gap-4 items-end mb-6">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                <div>
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">From Date</label>
                    <input type="date" name="from" value="<?= $from ?>" class="border rounded-lg p-2.5 mt-1">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">To Date</label>
                    <input type="date" name="to" value="<?= $to ?>" class="border rounded-lg p-2.5 mt-1">
                </div>
                <button class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700">Generate</button>
            </form>

            <?php if ($tab == 'sales'): ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Sales</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200 mt-1"><?= $sales_summary['total_sales'] ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-bold text-green-600 mt-1"><?= number_format($sales_summary['total_revenue']) ?> Ks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Paid</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1"><?= number_format($sales_summary['total_paid']) ?> Ks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg per Sale</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">
                        <?= $sales_summary['total_sales'] > 0 ? number_format($sales_summary['total_revenue'] / $sales_summary['total_sales']) : 0 ?> Ks
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-lg font-bold mb-4">Payment Method Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-emerald-700 uppercase">Cash</p>
                                <p class="text-xl font-bold text-emerald-800"><?= number_format($payment_totals['Cash']) ?> Ks</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-blue-700 uppercase">Card</p>
                                <p class="text-xl font-bold text-blue-800"><?= number_format($payment_totals['Card']) ?> Ks</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-purple-700 uppercase">Transfer</p>
                                <p class="text-xl font-bold text-purple-800"><?= number_format($payment_totals['Transfer']) ?> Ks</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-bold mb-4">Daily Sales</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr class="border-b text-gray-500 dark:text-gray-400 text-left"><th class="p-3">Date</th><th>Sales</th><th>Revenue</th></tr></thead>
                        <tbody>
                            <?php while ($d = mysqli_fetch_assoc($sales_daily)): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-semibold"><?= date('d-m-Y', strtotime($d['day'])) ?></td>
                                <td><?= $d['count'] ?></td>
                                <td class="text-green-600 font-bold"><?= number_format($d['total']) ?> Ks</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 mt-6">
                <h2 class="text-lg font-bold mb-4">All Sales</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr class="border-b text-gray-500 dark:text-gray-400 text-left"><th class="p-3">Invoice</th><th>Date</th><th>Customer</th><th>Cashier</th><th>Method</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php while ($s = mysqli_fetch_assoc($sales_report)): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-semibold"><?= htmlspecialchars($s['invoice_no']) ?></td>
                                <td><?= date('d-m-Y h:i A', strtotime($s['sale_date'])) ?></td>
                                <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                                <td><?= htmlspecialchars($s['cashier'] ?? 'Admin') ?></td>
                                <td><span class="bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full text-xs"><?= $s['payment_method'] ?? 'Cash' ?></span></td>
                                <td class="text-green-600 font-bold"><?= number_format($s['grand_total']) ?> Ks</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($tab == 'purchase'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Purchases</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200 mt-1"><?= $purchase_summary['total'] ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Amount</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1"><?= number_format($purchase_summary['total_amount']) ?> Ks</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-bold mb-4">Purchase History</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr class="border-b text-gray-500 dark:text-gray-400 text-left"><th class="p-3">Date</th><th>Supplier</th><th>Amount</th><th>Payment</th></tr></thead>
                        <tbody>
                            <?php while ($p = mysqli_fetch_assoc($purchase_report)): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3"><?= $p['purchase_date'] ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($p['supplier_name']) ?></td>
                                <td class="text-orange-600 font-bold"><?= number_format($p['total_amount']) ?> Ks</td>
                                <td>
                                    <span class="<?= $p['payment_status'] == 'Paid' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?> px-2 py-0.5 rounded-full text-xs">
                                        <?= $p['payment_status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($tab == 'profit'): ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-bold text-green-600 mt-1"><?= number_format($profit_summary['total_revenue']) ?> Ks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Cost</p>
                    <p class="text-2xl font-bold text-red-600 mt-1"><?= number_format($profit_summary['total_cost']) ?> Ks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Gross Profit</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1"><?= number_format($profit_summary['total_profit']) ?> Ks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Profit Margin</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1">
                        <?= $profit_summary['total_revenue'] > 0 ? number_format(($profit_summary['total_profit'] / $profit_summary['total_revenue']) * 100, 1) : 0 ?>%
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">Daily Profit Breakdown</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead><tr class="border-b text-gray-500 dark:text-gray-400 text-left"><th class="p-3">Date</th><th>Sales</th><th>Revenue</th><th>Cost</th><th>Profit</th></tr></thead>
                            <tbody>
                                <?php while ($d = mysqli_fetch_assoc($profit_data)): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-semibold"><?= date('d-m-Y', strtotime($d['day'])) ?></td>
                                    <td><?= $d['sales_count'] ?></td>
                                    <td class="text-green-600"><?= number_format($d['revenue']) ?> Ks</td>
                                    <td class="text-red-600"><?= number_format($d['cost']) ?> Ks</td>
                                    <td class="text-indigo-600 font-bold"><?= number_format($d['profit']) ?> Ks</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">Top Selling Products</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead><tr class="border-b text-gray-500 dark:text-gray-400 text-left"><th class="p-3">Product</th><th>Sold</th><th>Revenue</th><th>Profit</th></tr></thead>
                            <tbody>
                                <?php while ($tp = mysqli_fetch_assoc($top_products)): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-semibold"><?= htmlspecialchars($tp['product_name']) ?></td>
                                    <td><?= $tp['total_qty'] ?></td>
                                    <td class="text-green-600"><?= number_format($tp['total_revenue']) ?> Ks</td>
                                    <td class="text-indigo-600 font-bold"><?= number_format($tp['total_profit']) ?> Ks</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
        </div>
    </div>
    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>
</html>
