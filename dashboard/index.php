<?php
include "../includes/auth_check.php";
include "../config/database.php";

$role = $_SESSION['role'] ?? 'staff';
$page_title = "Dashboard";

// ── Common Queries ──
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products WHERE status='Active'"))['c'];
$total_suppliers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM suppliers WHERE status='Active'"))['c'];
$low_stock_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products WHERE quantity <= minimum_stock AND status='Active'"))['c'];

// ── Admin-only Queries ──
if ($role === 'admin') {
    $total_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM categories WHERE status='Active'"))['c'];
    $total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
    $today_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(grand_total),0) AS c FROM sales WHERE DATE(sale_date)=CURDATE()"))['c'];
    $monthly_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(grand_total),0) AS c FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())"))['c'];
    $forecast_summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN demand_level='High' THEN 1 ELSE 0 END) AS high_d, SUM(CASE WHEN demand_level='Medium' THEN 1 ELSE 0 END) AS med_d, SUM(CASE WHEN demand_level='Low' THEN 1 ELSE 0 END) AS low_d FROM forecasts"));

    $low_stock_products = [];
    $ls_res = mysqli_query($conn, "SELECT id, product_name, quantity, minimum_stock FROM products WHERE quantity<=minimum_stock AND status='Active' ORDER BY quantity ASC LIMIT 5");
    while ($r = mysqli_fetch_assoc($ls_res)) $low_stock_products[] = $r;

    // Sales overview last 7 days
    $sales_chart_rows = [];
    $sc_res = mysqli_query($conn, "SELECT DATE(sale_date) AS d, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS revenue FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(sale_date) ORDER BY d");
    while ($r = mysqli_fetch_assoc($sc_res)) $sales_chart_rows[] = $r;

    // Top selling products today
    $top_products = [];
    $tp_res = mysqli_query($conn, "SELECT p.product_name, SUM(sd.quantity) AS total_sold, SUM(sd.subtotal) AS revenue FROM sale_details sd JOIN products p ON sd.product_id=p.id JOIN sales s ON sd.sale_id=s.id WHERE DATE(s.sale_date)=CURDATE() GROUP BY sd.product_id ORDER BY total_sold DESC LIMIT 5");
    while ($r = mysqli_fetch_assoc($tp_res)) $top_products[] = $r;

    // Recent sales
    $recent_sales = [];
    $rs_res = mysqli_query($conn, "SELECT s.invoice_no, s.grand_total, s.payment_method, s.sale_date, u.name AS user_name FROM sales s LEFT JOIN users u ON s.user_id=u.id ORDER BY s.created_at DESC LIMIT 5");
    while ($r = mysqli_fetch_assoc($rs_res)) $recent_sales[] = $r;
}

// ── Staff-only Queries ──
if ($role === 'staff') {
    $today_stock_in = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM purchases WHERE purchase_date=CURDATE()"));
    $forecast_summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN demand_level='High' THEN 1 ELSE 0 END) AS high_d, SUM(CASE WHEN demand_level='Medium' THEN 1 ELSE 0 END) AS med_d, SUM(CASE WHEN demand_level='Low' THEN 1 ELSE 0 END) AS low_d FROM forecasts"));

    $recent_purchases = [];
    $rp_res = mysqli_query($conn, "SELECT p.invoice_no, p.total_amount, p.payment_status, p.purchase_date, s.supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id ORDER BY p.created_at DESC LIMIT 5");
    while ($r = mysqli_fetch_assoc($rp_res)) $recent_purchases[] = $r;

    // Stock movement data for chart (last 7 days)
    $stock_in_chart = [];
    $si_res = mysqli_query($conn, "SELECT purchase_date AS d, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM purchases WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY purchase_date ORDER BY d");
    while ($r = mysqli_fetch_assoc($si_res)) $stock_in_chart[] = $r;

    $stock_out_chart = [];
    $so_res = mysqli_query($conn, "SELECT DATE(sale_date) AS d, COUNT(*) AS cnt, COALESCE(SUM(grand_total),0) AS total FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(sale_date) ORDER BY d");
    while ($r = mysqli_fetch_assoc($so_res)) $stock_out_chart[] = $r;
}

// ── Cashier-only Queries ──
if ($role === 'cashier') {
    $today_stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS revenue FROM sales WHERE DATE(sale_date)=CURDATE()"));

    $recent_sales = [];
    $rs_res = mysqli_query($conn, "SELECT s.invoice_no, s.grand_total, s.payment_method, s.sale_date FROM sales s ORDER BY s.created_at DESC LIMIT 5");
    while ($r = mysqli_fetch_assoc($rs_res)) $recent_sales[] = $r;

    $best_products = [];
    $bp_res = mysqli_query($conn, "SELECT p.product_name, SUM(sd.quantity) AS total_sold FROM sale_details sd JOIN products p ON sd.product_id=p.id JOIN sales s ON sd.sale_id=s.id WHERE DATE(s.sale_date)=CURDATE() GROUP BY sd.product_id ORDER BY total_sold DESC LIMIT 5");
    while ($r = mysqli_fetch_assoc($bp_res)) $best_products[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .greeting-fade { animation: fadeIn 0.5s ease-out both; }
    </style>
</head>

<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">

                <!-- Greeting -->
                <div class="mb-6 greeting-fade">
                    <h2 class="text-xl lg:text-2xl font-bold text-gray-900 dark:text-slate-100">
                        Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>!
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                        Here's what's happening with your inventory today.
                    </p>
                </div>

                <?php if ($role === 'admin'): ?>
                <!-- ═══════════════ ADMIN DASHBOARD ═══════════════ -->

                <!-- Stat Cards Row 1 -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5 mb-6">
                    <a href="../product/index.php" class="stat-card stat-card-link fade-in stagger-1">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $total_products ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Total Products</p>
                            </div>
                        </div>
                    </a>
                    <a href="../categories/index.php" class="stat-card stat-card-link fade-in stagger-2">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $total_categories ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Categories</p>
                            </div>
                        </div>
                    </a>
                    <a href="../supplier/index.php" class="stat-card stat-card-link fade-in stagger-3">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-50 dark:bg-blue-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $total_suppliers ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Suppliers</p>
                            </div>
                        </div>
                    </a>
                    <a href="../users/index.php" class="stat-card stat-card-link fade-in stagger-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-purple-50 dark:bg-purple-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $total_users ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Users</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Stat Cards Row 2 -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5 mb-6">
                    <div class="stat-card fade-in stagger-5">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-50 dark:bg-green-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= number_format($today_revenue) ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Today's Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card fade-in stagger-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= number_format($monthly_revenue) ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Monthly Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card fade-in stagger-7">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-red-50 dark:bg-red-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $low_stock_count ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Low Stock Products</p>
                            </div>
                        </div>
                    </div>
                    <a href="../forecast/index.php" class="stat-card stat-card-link fade-in stagger-8">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-cyan-50 dark:bg-cyan-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $forecast_summary['total'] ?? 0 ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Forecast Summary</p>
                                <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">
                                    High: <?= $forecast_summary['high_d'] ?? 0 ?> · Med: <?= $forecast_summary['med_d'] ?? 0 ?> · Low: <?= $forecast_summary['low_d'] ?? 0 ?>
                                </p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Charts + Tables Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
                    <!-- Sales Overview Chart -->
                    <div class="lg:col-span-2 card fade-in stagger-5">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Sales Overview (Last 7 Days)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="salesOverviewChart" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Top Selling Products -->
                    <div class="card fade-in stagger-6">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Top Selling Products</h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($top_products) > 0): ?>
                                <div class="divide-y divide-gray-100 dark:divide-slate-700">
                                    <?php foreach ($top_products as $i => $tp): ?>
                                        <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <span class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400"><?= $i + 1 ?></span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800 dark:text-slate-200"><?= htmlspecialchars($tp['product_name']) ?></p>
                                                    <p class="text-xs text-gray-500 dark:text-slate-400"><?= $tp['total_sold'] ?> sold</p>
                                                </div>
                                            </div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-slate-100"><?= number_format($tp['revenue']) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm text-gray-500 dark:text-slate-400">No sales today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales + Low Stock -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <!-- Recent Sales -->
                    <div class="card fade-in stagger-7">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Recent Sales</h3>
                            <a href="../sale/history.php" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($recent_sales) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table w-full">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Amount</th>
                                                <th class="center">Method</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $rs): ?>
                                                <tr>
                                                    <td class="font-semibold"><?= htmlspecialchars($rs['invoice_no']) ?></td>
                                                    <td class="font-semibold text-gray-900 dark:text-slate-100"><?= number_format($rs['grand_total']) ?></td>
                                                    <td class="center"><span class="badge badge-info"><span class="badge-dot"></span><?= $rs['payment_method'] ?></span></td>
                                                    <td class="text-gray-500 dark:text-slate-400"><?= date('d M Y', strtotime($rs['sale_date'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm text-gray-500 dark:text-slate-400">No recent sales</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Low Stock Products -->
                    <div class="card fade-in stagger-8">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Low Stock Alert</h3>
                            <a href="../product/index.php" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($low_stock_products) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table w-full">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th class="num">Stock</th>
                                                <th class="num">Min</th>
                                                <th class="center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_products as $ls): ?>
                                                <tr>
                                                    <td class="font-medium"><?= htmlspecialchars($ls['product_name']) ?></td>
                                                    <td class="num font-semibold text-red-600 dark:text-red-400"><?= $ls['quantity'] ?></td>
                                                    <td class="num"><?= $ls['minimum_stock'] ?></td>
                                                    <td class="center">
                                                        <?php if ($ls['quantity'] == 0): ?>
                                                            <span class="badge badge-danger"><span class="badge-dot"></span>Out of Stock</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning"><span class="badge-dot"></span>Low Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm text-gray-500 dark:text-slate-400">All products are well-stocked</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <script>
                (function(){
                    const labels = <?= json_encode(array_map(function($r){ return date('d M', strtotime($r['d'])); }, $sales_chart_rows)) ?>;
                    const revenue = <?= json_encode(array_map(function($r){ return (float)$r['revenue']; }, $sales_chart_rows)) ?>;
                    const orders = <?= json_encode(array_map(function($r){ return (int)$r['orders']; }, $sales_chart_rows)) ?>;
                    const isDark = document.documentElement.classList.contains('dark');
                    const gridColor = isDark ? 'rgba(51,65,85,0.5)' : 'rgba(229,231,235,1)';
                    const textColor = isDark ? '#94a3b8' : '#6b7280';

                    new Chart(document.getElementById('salesOverviewChart'), {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Revenue',
                                data: revenue,
                                backgroundColor: 'rgba(99,102,241,0.7)',
                                borderRadius: 6,
                                barPercentage: 0.6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) { return 'Ks ' + ctx.parsed.y.toLocaleString(); }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: gridColor },
                                    ticks: { color: textColor, callback: function(v){ return v >= 1000 ? (v/1000)+'K' : v; } }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: textColor }
                                }
                            }
                        }
                    });
                })();
                </script>

                <?php elseif ($role === 'staff'): ?>
                <!-- ═══════════════ STAFF DASHBOARD ═══════════════ -->

                <!-- Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-5 mb-6">
                    <a href="../product/index.php" class="stat-card stat-card-link fade-in stagger-1">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $total_products ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Total Products</p>
                            </div>
                        </div>
                    </a>
                    <div class="stat-card fade-in stagger-2">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-50 dark:bg-blue-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $total_suppliers ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Suppliers</p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card fade-in stagger-3">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-red-50 dark:bg-red-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $low_stock_count ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Low Stock Products</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-5 mb-6">
                    <div class="stat-card fade-in stagger-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-50 dark:bg-green-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $today_stock_in['cnt'] ?? 0 ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Today's Stock In</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">Total: <?= number_format($today_stock_in['total'] ?? 0) ?></p>
                            </div>
                        </div>
                    </div>
                    <a href="../forecast/index.php" class="stat-card stat-card-link fade-in stagger-5">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-cyan-50 dark:bg-cyan-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $forecast_summary['total'] ?? 0 ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Forecast Summary</p>
                                <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">
                                    High: <?= $forecast_summary['high_d'] ?? 0 ?> · Med: <?= $forecast_summary['med_d'] ?? 0 ?> · Low: <?= $forecast_summary['low_d'] ?? 0 ?>
                                </p>
                            </div>
                        </div>
                    </a>
                    <?php if ($low_stock_count > 0): ?>
                    <div class="stat-card fade-in stagger-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-red-600 dark:text-red-400"><?= $low_stock_count ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Low Stock Alert</p>
                                <p class="text-xs text-red-500 dark:text-red-400 mt-0.5">Need restocking</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Charts + Recent Purchases -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                    <!-- Stock Movement Chart -->
                    <div class="card fade-in stagger-5">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Stock Movement (Last 7 Days)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="stockMovementChart" height="220"></canvas>
                        </div>
                    </div>

                    <!-- Recent Purchases -->
                    <div class="card fade-in stagger-6">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Recent Purchases</h3>
                            <a href="../purchase/history.php" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($recent_purchases) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table w-full">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Supplier</th>
                                                <th class="num">Amount</th>
                                                <th class="center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_purchases as $rp): ?>
                                                <tr>
                                                    <td class="font-semibold"><?= htmlspecialchars($rp['invoice_no'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($rp['supplier_name'] ?? '-') ?></td>
                                                    <td class="num font-semibold text-gray-900 dark:text-slate-100"><?= number_format($rp['total_amount']) ?></td>
                                                    <td class="center">
                                                        <span class="badge <?= $rp['payment_status'] === 'Paid' ? 'badge-success' : 'badge-warning' ?>">
                                                            <span class="badge-dot"></span><?= $rp['payment_status'] ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm text-gray-500 dark:text-slate-400">No recent purchases</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <script>
                (function(){
                    const isDark = document.documentElement.classList.contains('dark');
                    const gridColor = isDark ? 'rgba(51,65,85,0.5)' : 'rgba(229,231,235,1)';
                    const textColor = isDark ? '#94a3b8' : '#6b7280';

                    const allDates = <?= json_encode(array_unique(array_merge(
                        array_map(function($r){ return $r['d']; }, $stock_in_chart),
                        array_map(function($r){ return $r['d']; }, $stock_out_chart)
                    ))) ?>;
                    allDates.sort();

                    const inMap = <?= json_encode(array_combine(
                        array_map(function($r){ return $r['d']; }, $stock_in_chart),
                        array_map(function($r){ return (int)$r['cnt']; }, $stock_in_chart)
                    )) ?>;
                    const outMap = <?= json_encode(array_combine(
                        array_map(function($r){ return $r['d']; }, $stock_out_chart),
                        array_map(function($r){ return (int)$r['cnt']; }, $stock_out_chart)
                    )) ?>;

                    const labels = allDates.map(function(d){ return new Date(d).toLocaleDateString('en', {day:'2-digit', month:'short'}); });
                    const inData = allDates.map(function(d){ return inMap[d] || 0; });
                    const outData = allDates.map(function(d){ return outMap[d] || 0; });

                    new Chart(document.getElementById('stockMovementChart'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Stock In',
                                data: inData,
                                borderColor: 'rgb(16,185,129)',
                                backgroundColor: 'rgba(16,185,129,0.1)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: 'rgb(16,185,129)'
                            },{
                                label: 'Stock Out',
                                data: outData,
                                borderColor: 'rgb(239,68,68)',
                                backgroundColor: 'rgba(239,68,68,0.1)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: 'rgb(239,68,68)'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    labels: { color: textColor, usePointStyle: true, padding: 15 }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: gridColor },
                                    ticks: { color: textColor, stepSize: 1 }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: textColor }
                                }
                            }
                        }
                    });
                })();
                </script>

                <?php elseif ($role === 'cashier'): ?>
                <!-- ═══════════════ CASHIER DASHBOARD ═══════════════ -->

                <!-- Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-5 mb-6">
                    <div class="stat-card fade-in stagger-1">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= $today_stats['orders'] ?? 0 ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Today's Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card fade-in stagger-2">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-50 dark:bg-green-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100"><?= number_format($today_stats['revenue'] ?? 0) ?></p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Today's Revenue</p>
                            </div>
                        </div>
                    </div>
                    <a href="../sale/pos.php" class="stat-card stat-card-link fade-in stagger-3">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-slate-100">POS</p>
                                <p class="text-sm text-gray-500 dark:text-slate-400">Open Register</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Recent Sales + Best Selling -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <!-- Recent Sales -->
                    <div class="card fade-in stagger-4">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Recent Sales</h3>
                            <a href="../sale/history.php" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($recent_sales) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table w-full">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th class="num">Amount</th>
                                                <th class="center">Method</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $rs): ?>
                                                <tr>
                                                    <td class="font-semibold"><?= htmlspecialchars($rs['invoice_no']) ?></td>
                                                    <td class="num font-semibold text-gray-900 dark:text-slate-100"><?= number_format($rs['grand_total']) ?></td>
                                                    <td class="center"><span class="badge badge-info"><span class="badge-dot"></span><?= $rs['payment_method'] ?></span></td>
                                                    <td class="text-gray-500 dark:text-slate-400"><?= date('h:i A', strtotime($rs['sale_date'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm text-gray-500 dark:text-slate-400">No sales today yet</p>
                                    <a href="../sale/pos.php" class="btn btn-primary btn-sm mt-3 inline-flex">Start Selling</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Best Selling Products -->
                    <div class="card fade-in stagger-5">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Best Selling Products</h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($best_products) > 0): ?>
                                <div class="divide-y divide-gray-100 dark:divide-slate-700">
                                    <?php foreach ($best_products as $i => $bp): ?>
                                        <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <span class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-xs font-bold text-amber-600 dark:text-amber-400"><?= $i + 1 ?></span>
                                                <p class="text-sm font-medium text-gray-800 dark:text-slate-200"><?= htmlspecialchars($bp['product_name']) ?></p>
                                            </div>
                                            <span class="badge badge-success"><span class="badge-dot"></span><?= $bp['total_sold'] ?> sold</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm text-gray-500 dark:text-slate-400">No sales data for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

            </main>
        </div>
    </div>
    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>

</html>