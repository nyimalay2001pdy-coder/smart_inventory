<?php
include "../includes/auth_check.php";
requireAdmin();
include "../config/database.php";
include "../config/helpers.php";

$page_title = "Reports";
$tab = $_GET['tab'] ?? 'sales';

// Revenue period filter
$period = $_GET['period'] ?? 'monthly';
switch ($period) {
    case 'daily':
        $from = date('Y-m-d');
        $to = date('Y-m-d');
        break;
    case 'yearly':
        $from = date('Y-01-01');
        $to = date('Y-12-31');
        break;
    case 'custom':
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-t');
        break;
    default: // monthly
        $from = date('Y-m-01');
        $to = date('Y-m-t');
}
$safe_from = mysqli_real_escape_string($conn, $from);
$safe_to = mysqli_real_escape_string($conn, $to);

// Category filter
$category_id = (int)($_GET['category_id'] ?? 0);
$category_join = '';
$category_where = '';
if ($category_id > 0) {
    $category_join = "JOIN sale_details sd ON s.id = sd.sale_id
                      JOIN products p ON sd.product_id = p.id";
    $category_where = "AND p.category_id = $category_id";
}

$categories = mysqli_query($conn, "SELECT id, name FROM categories WHERE status='Active' ORDER BY name");

// Sales Report
$sales_report = mysqli_query($conn, "
    SELECT DISTINCT s.*, u.name AS cashier, sp.payment_method
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN sale_payments sp ON sp.id = (SELECT id FROM sale_payments WHERE sale_id = s.id ORDER BY id ASC LIMIT 1)
    $category_join
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    $category_where
    ORDER BY s.created_at DESC
");

$sales_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT s.id) AS total_sales,
           COALESCE(SUM(s.total_amount), 0) AS total_revenue
    FROM sales s
    $category_join
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    $category_where
"));

// Chart data (ASC order for chart)
$sales_chart = mysqli_query($conn, "
    SELECT DATE(s.created_at) AS day, COUNT(DISTINCT s.id) AS count, COALESCE(SUM(s.total_amount), 0) AS total
    FROM sales s
    $category_join
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    $category_where
    GROUP BY DATE(s.created_at) ORDER BY day ASC
");

// Best Selling Category
$best_category = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT c.name, SUM(sd.quantity) AS total_qty, SUM(sd.subtotal) AS total_rev
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    JOIN products p ON sd.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    " . ($category_id > 0 ? "AND p.category_id = $category_id " : "") . "
    GROUP BY c.id
    ORDER BY total_qty DESC
    LIMIT 1
"));

// Daily sales table data (DESC order)
$sales_daily = mysqli_query($conn, "
    SELECT DATE(s.created_at) AS day, COUNT(DISTINCT s.id) AS count, COALESCE(SUM(s.total_amount), 0) AS total
    FROM sales s
    $category_join
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    $category_where
    GROUP BY DATE(s.created_at) ORDER BY day DESC
");

// Payment method summaries (from sale_payments table)
$spAmtCol = getPaymentAmountCol($conn, 'sale_payments');
$payment_summary = mysqli_query($conn, "
    SELECT COALESCE(sp.payment_method, 'Cash') AS payment_method,
           COALESCE(SUM(sp.$spAmtCol), s.total_amount) AS total
    FROM sales s
    LEFT JOIN sale_payments sp ON sp.id = (
        SELECT id FROM sale_payments WHERE sale_id = s.id ORDER BY id ASC LIMIT 1
    )
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY COALESCE(sp.payment_method, 'Cash')
");
$payment_totals = ['Cash' => 0, 'KBZPay' => 0, 'Mixed' => 0];
while ($pt = mysqli_fetch_assoc($payment_summary)) {
    $pm = $pt['payment_method'] ?? 'Cash';
    if (!isset($payment_totals[$pm])) $payment_totals[$pm] = 0;
    $payment_totals[$pm] += (float)$pt['total'];
}

// Purchase Report - calculate status from payment data
$rptPurAmtCol = getPaymentAmountCol($conn, 'purchase_payments');
$purchase_report = mysqli_query($conn, "
    SELECT p.*, s.supplier_name,
           COALESCE((SELECT SUM(pp.$rptPurAmtCol) FROM purchase_payments pp WHERE pp.purchase_id = p.id), 0) AS total_paid
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
    SELECT DATE(s.created_at) AS day,
           COUNT(DISTINCT s.id) AS sales_count,
           COALESCE(SUM(sd.subtotal), 0) AS revenue,
           COALESCE(SUM(sd.purchase_price * sd.quantity), 0) AS cost,
           COALESCE(SUM(sd.profit), 0) AS profit
    FROM sales s
    JOIN sale_details sd ON s.id = sd.sale_id
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY DATE(s.created_at)
    ORDER BY day DESC
");

$profit_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(sd.subtotal), 0) AS total_revenue,
           COALESCE(SUM(sd.purchase_price * sd.quantity), 0) AS total_cost,
           COALESCE(SUM(sd.profit), 0) AS total_profit
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
"));

$top_products = mysqli_query($conn, "
    SELECT p.product_name, SUM(sd.quantity) AS total_qty,
           SUM(sd.subtotal) AS total_revenue,
           SUM(sd.profit) AS total_profit
    FROM sale_details sd
    JOIN products p ON sd.product_id = p.id
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
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
            <div class="flex gap-1 mb-6 bg-white p-1 rounded-xl shadow-sm w-fit">
                <a href="?tab=sales&period=<?= $period ?>&category_id=<?= $category_id ?>&from=<?= $from ?>&to=<?= $to ?>"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold <?= $tab == 'sales' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100' ?>">Sales Report</a>
                <a href="?tab=purchase&from=<?= $from ?>&to=<?= $to ?>"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold <?= $tab == 'purchase' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100' ?>">Purchase Report</a>
                <a href="?tab=profit&from=<?= $from ?>&to=<?= $to ?>"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold <?= $tab == 'profit' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100' ?>">Profit Report</a>
            </div>

            <form method="GET" id="revenueFilterForm" class="bg-white p-5 rounded-xl shadow-sm mb-6">
                <input type="hidden" name="tab" value="sales">
                <div class="flex flex-wrap gap-x-6 gap-y-3 items-end">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5 block">Period</label>
                        <div class="flex gap-1 bg-gray-100 p-0.5 rounded-lg">
                            <?php foreach (['daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly', 'custom' => 'Custom'] as $p => $pl): ?>
                            <button type="button" data-period="<?= $p ?>"
                                class="period-btn px-4 py-1.5 rounded-md text-sm font-medium transition <?= $period == $p ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>"><?= $pl ?></button>
                            <?php endforeach; ?>
                            <input type="hidden" name="period" id="periodInput" value="<?= $period ?>">
                        </div>
                    </div>
                    <div id="customDateFields" class="flex gap-3 items-end <?= $period !== 'custom' ? 'opacity-40 pointer-events-none' : '' ?>">
                        <div>
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">From</label>
                            <input type="date" name="from" value="<?= $from ?>" class="border border-gray-200 rounded-lg p-2 mt-1 text-sm w-40">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">To</label>
                            <input type="date" name="to" value="<?= $to ?>" class="border border-gray-200 rounded-lg p-2 mt-1 text-sm w-40">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5 block">Category</label>
                        <select name="category_id" onchange="this.form.submit()" class="border border-gray-200 rounded-lg p-2 text-sm bg-white min-w-[180px]">
                            <option value="0">All Categories</option>
                            <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium">Generate</button>
                </div>
            </form>

            <?php if ($tab == 'sales'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-emerald-500">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1"><?= number_format($sales_summary['total_revenue']) ?> Ks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-indigo-500">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Orders</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1"><?= $sales_summary['total_sales'] ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-amber-500">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Best Selling Category</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1"><?= htmlspecialchars($best_category['name'] ?? 'N/A') ?></p>
                    <?php if ($best_category): ?>
                    <p class="text-xs text-gray-400 mt-0.5"><?= $best_category['total_qty'] ?> items sold</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-lg font-bold mb-4">Revenue Overview</h2>
                <canvas id="revenueChart" height="100"></canvas>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-lg font-bold mb-4">Payment Method Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-blue-700 uppercase">KBZPay</p>
                                <p class="text-xl font-bold text-blue-800"><?= number_format($payment_totals['KBZPay']) ?> Ks</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">Daily Revenue</h2>
                    <div class="table-wrap max-h-64 overflow-y-auto">
                        <table class="data-table w-full">
                            <thead><tr><th class="text-left">Date</th><th class="num">Orders</th><th class="num">Revenue</th></tr></thead>
                            <tbody>
                                <?php while ($d = mysqli_fetch_assoc($sales_daily)): ?>
                                <tr>
                                    <td class="font-semibold"><?= date('d-m-Y', strtotime($d['day'])) ?></td>
                                    <td class="num"><?= $d['count'] ?></td>
                                    <td class="num"><?= number_format($d['total']) ?> Ks</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">All Sales</h2>
                    <div class="table-wrap max-h-64 overflow-y-auto">
                        <table class="data-table w-full">
                            <thead><tr><th>Invoice</th><th class="text-left">Date</th><th>Cashier</th><th class="center">Method</th><th class="num">Total</th></tr></thead>
                            <tbody>
                                <?php while ($s = mysqli_fetch_assoc($sales_report)): ?>
                                <tr>
                                    <td class="font-semibold"><?= htmlspecialchars($s['invoice_no']) ?></td>
                                    <td><?= date('d-m-Y h:i A', strtotime($s['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($s['cashier'] ?? 'Admin') ?></td>
                                    <td class="center"><span class="bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full text-xs"><?= $s['payment_method'] ?? 'Cash' ?></span></td>
                                    <td class="num"><?= number_format($s['total_amount']) ?> Ks</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
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
                <div class="table-wrap">
                    <table class="data-table w-full">
                        <thead><tr><th class="text-left">Date</th><th>Invoice</th><th>Supplier</th><th class="num">Total</th><th class="center">Status</th></tr></thead>
                        <tbody>
                            <?php while ($p = mysqli_fetch_assoc($purchase_report)): ?>
                            <tr>
                                <td><?= $p['purchase_date'] ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($p['invoice_no'] ?? '#' . $p['id']) ?></td>
                                <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                                <td class="num"><?= number_format($p['total_amount']) ?> Ks</td>
                                <td class="center">
                                    <?php
                                    $rpt_ta = (float)$p['total_amount'];
                                    $rpt_tp = (float)$p['total_paid'];
                                    if ($rpt_ta > 0 && $rpt_tp >= $rpt_ta) $rpt_st = 'Paid';
                                    elseif ($rpt_tp > 0) $rpt_st = 'Partial';
                                    else $rpt_st = 'Unpaid';
                                    ?>
                                    <span class="badge <?= $rpt_st == 'Paid' ? 'badge-success' : 'badge-danger' ?>">
                                        <span class="badge-dot"></span>
                                        <?= $rpt_st ?>
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
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= $profit_summary['total_profit'] < 0 ? 'Net Loss' : 'Gross Profit' ?></p>
                    <p class="text-2xl font-bold <?= $profit_summary['total_profit'] < 0 ? 'text-red-600' : 'text-indigo-600' ?> mt-1"><?= number_format($profit_summary['total_profit']) ?> Ks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Profit Margin</p>
                    <p class="text-2xl font-bold <?= $profit_summary['total_profit'] < 0 ? 'text-red-600' : 'text-amber-600' ?> mt-1">
                        <?= $profit_summary['total_revenue'] > 0 ? number_format(($profit_summary['total_profit'] / $profit_summary['total_revenue']) * 100, 1) : 0 ?>%
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">Daily Profit Breakdown</h2>
                    <div class="table-wrap">
                        <table class="data-table w-full">
                            <thead><tr><th class="text-left">Date</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th></tr></thead>
                            <tbody>
                                <?php while ($d = mysqli_fetch_assoc($profit_data)): ?>
                                <tr>
                                    <td class="font-semibold"><?= date('d-m-Y', strtotime($d['day'])) ?></td>
                                    <td class="num"><?= number_format($d['revenue']) ?> Ks</td>
                                    <td class="num"><?= number_format($d['cost']) ?> Ks</td>
                                    <td class="num <?= $d['profit'] < 0 ? 'text-red-600' : '' ?>"><?= ($d['profit'] < 0 ? 'Loss ' : '') . number_format($d['profit']) ?> Ks</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">Top Selling Products</h2>
                    <div class="table-wrap">
                        <table class="data-table w-full">
                            <thead><tr><th>#</th><th>Product</th><th class="num">Qty Sold</th><th class="num">Revenue</th></tr></thead>
                            <tbody>
                                <?php $tp_idx = 1; while ($tp = mysqli_fetch_assoc($top_products)): ?>
                                <tr>
                                    <td><?= $tp_idx++ ?></td>
                                    <td class="font-semibold"><?= htmlspecialchars($tp['product_name']) ?></td>
                                    <td class="num"><?= $tp['total_qty'] ?></td>
                                    <td class="num"><?= number_format($tp['total_revenue']) ?> Ks</td>
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
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Period buttons
        document.querySelectorAll('.period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.period-btn').forEach(function (b) {
                    b.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm');
                    b.classList.add('text-gray-500');
                });
                this.classList.remove('text-gray-500');
                this.classList.add('bg-white', 'text-indigo-600', 'shadow-sm');
                document.getElementById('periodInput').value = this.dataset.period;

                var customFields = document.getElementById('customDateFields');
                if (this.dataset.period === 'custom') {
                    customFields.classList.remove('opacity-40', 'pointer-events-none');
                } else {
                    customFields.classList.add('opacity-40', 'pointer-events-none');
                }
                document.getElementById('revenueFilterForm').submit();
            });
        });

        // Revenue chart
        var ctx = document.getElementById('revenueChart');
        if (ctx) {
            var chartData = [
            <?php mysqli_data_seek($sales_chart, 0); while ($rc = mysqli_fetch_assoc($sales_chart)): ?>
                { day: '<?= $rc['day'] ?>', total: <?= (float)$rc['total'] ?> },
            <?php endwhile; ?>
            ];
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.map(function (d) { return d.day; }),
                    datasets: [{
                        label: 'Revenue (Ks)',
                        data: chartData.map(function (d) { return d.total; }),
                        backgroundColor: 'rgba(99, 102, 241, 0.6)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (v) { return v.toLocaleString() + ' Ks'; }
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });
    </script>
    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>
</html>
