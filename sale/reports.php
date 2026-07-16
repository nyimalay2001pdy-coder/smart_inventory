<?php
include "../includes/auth_check.php";
// All authenticated users can access sale reports
include "../config/database.php";
include "../config/helpers.php";

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$safe_from = mysqli_real_escape_string($conn, $date_from);
$safe_to = mysqli_real_escape_string($conn, $date_to);

// ============ TODAY'S SALES ============
$today_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS revenue
    FROM sales WHERE DATE(created_at) = CURDATE()
"));

// ============ WEEKLY SALES (last 7 days) ============
$week_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS revenue
    FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
"));

// ============ MONTHLY SALES (current month) ============
$month_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS revenue
    FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
"));

// ============ OVERALL REVENUE (filtered range) ============
$revenue_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM sales WHERE DATE(created_at) BETWEEN '$safe_from' AND '$safe_to'
"));

// ============ PROFIT ============
$profit_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(sd.subtotal), 0) AS revenue,
           COALESCE(SUM(sd.purchase_price * sd.quantity), 0) AS cost,
           COALESCE(SUM(sd.profit), 0) AS profit
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
"));

// ============ BEST SELLING PRODUCTS ============
$top_products = mysqli_query($conn, "
    SELECT p.product_name, p.sku, SUM(sd.quantity) AS total_qty,
           SUM(sd.subtotal) AS total_revenue,
           SUM(sd.profit) AS total_profit
    FROM sale_details sd
    JOIN products p ON sd.product_id = p.id
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY sd.product_id
    ORDER BY total_qty DESC LIMIT 10
");

// ============ SALES BY CATEGORY ============
$category_sales = mysqli_query($conn, "
    SELECT c.name AS category_name, SUM(sd.quantity) AS total_qty,
           SUM(sd.subtotal) AS total_revenue,
           COUNT(DISTINCT s.id) AS sale_count
    FROM sale_details sd
    JOIN products p ON sd.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY c.id
    ORDER BY total_revenue DESC
");

// ============ PAYMENT METHOD SUMMARY ============
$spAmtCol = getPaymentAmountCol($conn, 'sale_payments');
$payment_summary = mysqli_query($conn, "
    SELECT COALESCE(sp.payment_method, 'Cash') AS payment_method,
           COALESCE(SUM(sp.$spAmtCol), s.total_amount) AS total, COUNT(*) AS count
    FROM sales s
    LEFT JOIN sale_payments sp ON sp.id = (
        SELECT id FROM sale_payments WHERE sale_id = s.id ORDER BY id ASC LIMIT 1
    )
    WHERE DATE(s.created_at) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY COALESCE(sp.payment_method, 'Cash')
");
$payment_totals = ['Cash' => 0, 'KBZPay' => 0, 'Mixed' => 0];
$payment_counts = ['Cash' => 0, 'KBZPay' => 0, 'Mixed' => 0];
while ($pt = mysqli_fetch_assoc($payment_summary)) {
    $pm = $pt['payment_method'] ?? 'Cash';
    if (!isset($payment_totals[$pm])) $payment_totals[$pm] = 0;
    if (!isset($payment_counts[$pm])) $payment_counts[$pm] = 0;
    $payment_totals[$pm] += (float)$pt['total'];
    $payment_counts[$pm] += (int)$pt['count'];
}
$has_payments = array_sum($payment_totals) > 0;

// ============ DAILY SALES ============
$daily_sales = mysqli_query($conn, "
    SELECT DATE(created_at) AS day, COUNT(*) AS count, SUM(total_amount) AS total
    FROM sales WHERE DATE(created_at) BETWEEN '$safe_from' AND '$safe_to'
    GROUP BY DATE(created_at) ORDER BY day DESC
");

$page_title = "Sales Reports";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.4s ease-out both; }
        .delay-1 { animation-delay: 0.05s; } .delay-2 { animation-delay: 0.1s; }
        .delay-3 { animation-delay: 0.15s; } .delay-4 { animation-delay: 0.2s; }
        .progress-bar { height: 8px; border-radius: 4px; background: #e5e7eb; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="flex gap-2 mb-6">
                        <button onclick="exportPDF()" class="btn btn-outline gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export PDF
                        </button>
                        <button onclick="exportExcel()" class="btn btn-outline gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export Excel
                        </button>
                    </div>

                    <form method="GET" class="filter-bar mb-6">
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">From Date</label>
                            <input type="date" name="date_from" value="<?= $date_from ?>" class="form-input text-sm">
                        </div>
                        <div class="min-w-[160px]">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 block">To Date</label>
                            <input type="date" name="date_to" value="<?= $date_to ?>" class="form-input text-sm">
                        </div>
                        <div class="flex gap-2 items-end">
                            <button class="btn btn-primary text-sm">Generate Report</button>
                            <a href="reports.php" class="btn btn-outline text-sm">Reset</a>
                        </div>
                    </form>

                    <!-- Quick Stats Row -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="exportArea">
                        <!-- Today -->
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 fade-in delay-1">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-[11px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Today</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Today's Sales</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1"><?= number_format($today_stats['count']) ?></p>
                            <p class="text-sm font-semibold text-emerald-600 mt-0.5"><?= number_format($today_stats['revenue']) ?> Ks</p>
                        </div>
                        <!-- This Week -->
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 fade-in delay-2">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <span class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">7 Days</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Weekly Sales</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1"><?= number_format($week_stats['count']) ?></p>
                            <p class="text-sm font-semibold text-blue-600 mt-0.5"><?= number_format($week_stats['revenue']) ?> Ks</p>
                        </div>
                        <!-- This Month -->
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 fade-in delay-3">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </div>
                                <span class="text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Month</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Monthly Sales</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1"><?= number_format($month_stats['count']) ?></p>
                            <p class="text-sm font-semibold text-indigo-600 mt-0.5"><?= number_format($month_stats['revenue']) ?> Ks</p>
                        </div>
                        <!-- Revenue -->
                        <div class="stat-card bg-white rounded-xl border border-gray-200 p-5 fade-in delay-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-[11px] font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Range</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Period Revenue</p>
                            <p class="text-2xl font-bold text-amber-600 mt-1"><?= number_format($revenue_stats['total_revenue']) ?> Ks</p>
                            <p class="text-xs text-gray-400 mt-0.5"><?= $revenue_stats['total_sales'] ?> sales</p>
                        </div>
                    </div>

                    <!-- Profit + Payment Methods Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Profit Card -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                    Profit Overview
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div class="text-center p-3 bg-emerald-50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Revenue</p>
                                        <p class="text-lg font-bold text-emerald-600 mt-1"><?= number_format($profit_stats['revenue']) ?></p>
                                    </div>
                                    <div class="text-center p-3 bg-red-50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Cost</p>
                                        <p class="text-lg font-bold text-red-600 mt-1"><?= number_format($profit_stats['cost']) ?></p>
                                    </div>
                                    <div class="text-center p-3 <?= $profit_stats['profit'] < 0 ? 'bg-red-50' : 'bg-indigo-50' ?> rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium"><?= $profit_stats['profit'] < 0 ? 'Net Loss' : 'Profit' ?></p>
                                        <p class="text-lg font-bold <?= $profit_stats['profit'] < 0 ? 'text-red-600' : 'text-indigo-600' ?> mt-1"><?= number_format($profit_stats['profit']) ?></p>
                                    </div>
                                </div>
                                <?php
                                $margin = $profit_stats['revenue'] > 0 ? ($profit_stats['profit'] / $profit_stats['revenue']) * 100 : 0;
                                ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-500 dark:text-gray-400">Profit Margin</span>
                                        <span class="font-bold <?= $margin >= 20 ? 'text-emerald-600' : ($margin >= 10 ? 'text-amber-600' : 'text-red-600') ?>"><?= number_format($margin, 1) ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?= $margin >= 20 ? 'bg-emerald-500' : ($margin >= 10 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= min(100, $margin) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    Payment Methods
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="space-y-4">
                                    <?php
                                    $total_payment = array_sum($payment_totals);
                                    $payment_colors = ['Cash' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-600', 'fill' => 'bg-emerald-500', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'],
                                        'KBZPay' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'fill' => 'bg-blue-500', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>'],
                                        'Mixed' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'fill' => 'bg-purple-500', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>']
                                    ];
                                    foreach ($payment_totals as $method => $amount):
                                        $pct = $total_payment > 0 ? ($amount / $total_payment) * 100 : 0;
                                        $colors = $payment_colors[$method] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'fill' => 'bg-gray-500', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'];
                                    ?>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 <?= $colors['bg'] ?> rounded-full flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 <?= $colors['text'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $colors['icon'] ?></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?= $method ?></span>
                                                <span class="text-sm font-bold <?= $colors['text'] ?>"><?= number_format($amount) ?> Ks</span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill <?= $colors['fill'] ?>" style="width: <?= $pct ?>%"></div>
                                            </div>
                                            <p class="text-[11px] text-gray-400 mt-0.5"><?= $payment_counts[$method] ?> transactions (<?= number_format($pct, 1) ?>%)</p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Best Selling Products -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <h2 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                Best Selling Products
                            </h2>
                        </div>
                        <div class="table-wrap">
                            <table class="data-table w-full">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th class="num">Qty Sold</th>
                                        <th class="num">Revenue</th>
                                        <th class="num">Profit</th>
                                        <th class="w-40">Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $max_revenue = 0;
                                    $tp_rows = [];
                                    while ($tp = mysqli_fetch_assoc($top_products)) {
                                        $tp_rows[] = $tp;
                                        if ($tp['total_revenue'] > $max_revenue) $max_revenue = $tp['total_revenue'];
                                    }
                                    $total_revenue_all = array_sum(array_column($tp_rows, 'total_revenue'));
                                    $rank = 1;
                                    foreach ($tp_rows as $tp):
                                        $share = $total_revenue_all > 0 ? ($tp['total_revenue'] / $total_revenue_all) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?= $rank++ ?></td>
                                        <td><?= htmlspecialchars($tp['product_name']) ?></td>
                                        <td><?= htmlspecialchars($tp['sku'] ?? 'N/A') ?></td>
                                        <td class="num"><?= number_format($tp['total_qty']) ?></td>
                                        <td class="num"><?= number_format($tp['total_revenue']) ?> Ks</td>
                                        <td class="num <?= $tp['total_profit'] < 0 ? 'text-red-600' : '' ?>"><?= ($tp['total_profit'] < 0 ? 'Loss ' : '') . number_format($tp['total_profit']) ?> Ks</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="progress-bar flex-1">
                                                    <div class="progress-fill bg-indigo-500" style="width: <?= $share ?>%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 w-10 text-right"><?= number_format($share, 1) ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($tp_rows)): ?>
                                    <tr><td colspan="7" class="text-center py-8 text-gray-400">No product sales in this period</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sales by Category -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <h2 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                Sales by Category
                            </h2>
                        </div>
                        <div class="table-wrap">
                            <table class="data-table w-full">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Category</th>
                                        <th class="num">Count</th>
                                        <th class="num">Qty Sold</th>
                                        <th class="num">Revenue</th>
                                        <th class="w-48">Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $cat_rows = [];
                                    while ($cs = mysqli_fetch_assoc($category_sales)) $cat_rows[] = $cs;
                                    $cat_total = array_sum(array_column($cat_rows, 'total_revenue'));
                                    $cat_colors = ['bg-indigo-500', 'bg-emerald-500', 'bg-blue-500', 'bg-amber-500', 'bg-purple-500', 'bg-red-500', 'bg-cyan-500'];
                                    $ci = 0;
                                    foreach ($cat_rows as $cs):
                                        $share = $cat_total > 0 ? ($cs['total_revenue'] / $cat_total) * 100 : 0;
                                        $color = $cat_colors[$ci % count($cat_colors)];
                                        $ci++;
                                    ?>
                                    <tr>
                                        <td><?= $ci ?></td>
                                        <td><?= htmlspecialchars($cs['category_name']) ?></td>
                                        <td class="num"><?= number_format($cs['sale_count']) ?></td>
                                        <td class="num"><?= number_format($cs['total_qty']) ?></td>
                                        <td class="num"><?= number_format($cs['total_revenue']) ?> Ks</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="progress-bar flex-1">
                                                    <div class="progress-fill <?= $color ?>" style="width: <?= $share ?>%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 w-10 text-right"><?= number_format($share, 1) ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($cat_rows)): ?>
                                    <tr><td colspan="6" class="text-center py-8 text-gray-400">No category data in this period</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Daily Sales Breakdown -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <h2 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Daily Sales
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="h-64 mb-4">
                                <canvas id="dailyChart"></canvas>
                            </div>
                            <div class="table-wrap">
                                <table class="data-table w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="num">Sales</th>
                                            <th class="num">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($d = mysqli_fetch_assoc($daily_sales)): ?>
                                        <tr>
                                            <td><?= date('d M Y (D)', strtotime($d['day'])) ?></td>
                                            <td class="num"><?= $d['count'] ?></td>
                                            <td class="num"><?= number_format($d['total']) ?> Ks</td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>

    <script>
    // Daily Sales Chart
    <?php
    $chart_labels = [];
    $chart_data = [];
    mysqli_data_seek($daily_sales, 0);
    while ($d = mysqli_fetch_assoc($daily_sales)) {
        $chart_labels[] = date('d M', strtotime($d['day']));
        $chart_data[] = (float)$d['total'];
    }
    ?>
    const ctx = document.getElementById('dailyChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_reverse($chart_labels)) ?>,
                datasets: [{
                    label: 'Revenue (Ks)',
                    data: <?= json_encode(array_reverse($chart_data)) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.8)',
                    borderColor: 'rgb(99, 102, 241)',
                    borderWidth: 1,
                    borderRadius: 6,
                    barThickness: 'flex',
                    maxBarThickness: 40,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.parsed.y.toLocaleString() + ' Ks';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) { return val.toLocaleString(); }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Export PDF (print)
    function exportPDF() {
        window.print();
    }

    // Export Excel (CSV download)
    function exportExcel() {
        const rows = [];
        rows.push(['Sales Report - <?= $date_from ?> to <?= $date_to ?>']);
        rows.push([]);
        rows.push(['Summary']);
        rows.push(['Total Sales', <?= $revenue_stats['total_sales'] ?>]);
        rows.push(['Total Revenue', <?= $revenue_stats['total_revenue'] ?>]);
        rows.push(['Total Profit', <?= $profit_stats['profit'] ?>]);
        rows.push(['Profit Margin', '<?= number_format($margin, 1) ?>%']);
        rows.push([]);
        rows.push(['Payment Method', 'Amount', 'Transactions']);
        <?php foreach ($payment_totals as $method => $amount): ?>
        rows.push(['<?= $method ?>', <?= $amount ?>, <?= $payment_counts[$method] ?>]);
        <?php endforeach; ?>
        rows.push([]);
        rows.push(['Top Products', 'Qty Sold', 'Revenue', 'Profit']);
        <?php foreach ($tp_rows as $tp): ?>
        rows.push(['<?= addslashes($tp['product_name']) ?>', <?= $tp['total_qty'] ?>, <?= $tp['total_revenue'] ?>, <?= $tp['total_profit'] ?>]);
        <?php endforeach; ?>

        const csv = rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'sales_report_<?= $date_from ?>_to_<?= $date_to ?>.csv';
        link.click();
    }
    </script>
</body>
</html>
