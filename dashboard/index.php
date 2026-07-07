<?php
include "../includes/auth_check.php";
include "../config/database.php";
$page_title = "Dashboard";

$role = $_SESSION['role'];

// ─── Common queries ───────────────────────────────────────────────
$total_products  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM products WHERE status='Active'"))['count'];
$total_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM categories WHERE status='Active'"))['count'];
$total_suppliers  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM suppliers WHERE status='Active'"))['count'];

$low_stock_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM products WHERE quantity <= minimum_stock AND status='Active'"))['count'];
$low_stock_result = mysqli_query($conn, "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.quantity <= p.minimum_stock AND p.status='Active' ORDER BY p.quantity ASC LIMIT 10");

// ─── Admin queries ────────────────────────────────────────────────
if ($role === 'admin') {
    $total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM users WHERE status='Active'"))['count'];
    $total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT customer_name) AS count FROM sales WHERE customer_name != '' AND customer_name IS NOT NULL"))['count'];

    $today_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count, COALESCE(SUM(grand_total), 0) AS revenue FROM sales WHERE DATE(sale_date) = CURDATE()"));
    $total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(grand_total), 0) AS total FROM sales"))['total'];
    $total_purchase_cost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(purchase_price * quantity), 0) AS cost FROM sale_details"))['cost'];
    $total_profit = $total_revenue - $total_purchase_cost;
    // Sales charts
    $daily_chart = mysqli_query($conn, "SELECT DATE(sale_date) AS day, COALESCE(SUM(grand_total), 0) AS total FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(sale_date) ORDER BY day ASC");
    $daily_labels = [];
    $daily_values = [];
    $daily_map = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $daily_map[$d] = 0;
    }
    while ($row = mysqli_fetch_assoc($daily_chart)) {
        $daily_map[$row['day']] = (float)$row['total'];
    }
    foreach ($daily_map as $d => $v) {
        $daily_labels[] = date('d M', strtotime($d));
        $daily_values[] = $v;
    }

    $monthly_chart = mysqli_query($conn, "SELECT DATE_FORMAT(sale_date, '%Y-%m') AS month, COALESCE(SUM(grand_total), 0) AS total FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(sale_date, '%Y-%m') ORDER BY month ASC");
    $monthly_labels = [];
    $monthly_values = [];
    while ($row = mysqli_fetch_assoc($monthly_chart)) {
        $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $monthly_values[] = (float)$row['total'];
    }

    $profit_chart = mysqli_query($conn, "SELECT DATE(s.sale_date) AS day, COALESCE(SUM(sd.subtotal - (sd.purchase_price * sd.quantity)), 0) AS profit FROM sales s JOIN sale_details sd ON s.id = sd.sale_id WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(s.sale_date) ORDER BY day ASC");
    $profit_map = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $profit_map[$d] = 0;
    }
    while ($row = mysqli_fetch_assoc($profit_chart)) {
        $profit_map[$row['day']] = (float)$row['profit'];
    }
    $profit_values = array_values($profit_map);

    $top_products = mysqli_query($conn, "SELECT p.product_name, SUM(sd.quantity) AS qty, SUM(sd.subtotal) AS revenue FROM sale_details sd JOIN products p ON sd.product_id = p.id JOIN sales s ON sd.sale_id = s.id WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY sd.product_id ORDER BY qty DESC LIMIT 10");
    $top_labels = [];
    $top_qty = [];
    while ($tp = mysqli_fetch_assoc($top_products)) {
        $top_labels[] = $tp['product_name'];
        $top_qty[] = (int)$tp['qty'];
    }

    $recent_sales = mysqli_query($conn, "SELECT s.*, u.name AS cashier FROM sales s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.id DESC LIMIT 5");

    // Purchase chart (monthly)
    $purchase_monthly = mysqli_query($conn, "SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS month, COALESCE(SUM(total_amount), 0) AS total FROM purchases WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(purchase_date, '%Y-%m') ORDER BY month ASC");
    $purchase_monthly_labels = [];
    $purchase_monthly_values = [];
    $purchase_map = [];
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $purchase_map[$m] = 0;
    }
    while ($row = mysqli_fetch_assoc($purchase_monthly)) {
        $purchase_map[$row['month']] = (float)$row['total'];
    }
    foreach ($purchase_map as $m => $v) {
        $purchase_monthly_labels[] = date('M Y', strtotime($m . '-01'));
        $purchase_monthly_values[] = $v;
    }

    $recent_purchases = mysqli_query($conn, "SELECT pu.*, s.supplier_name FROM purchases pu LEFT JOIN suppliers s ON pu.supplier_id = s.id ORDER BY pu.id DESC LIMIT 10");

    // Forecast summary
    $forecast_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT product_id) AS count FROM forecasts"))['count'];
    $forecast_high = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM forecasts WHERE demand_level='High'"))['count'];
    $forecast_low = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM forecasts WHERE demand_level='Low'"))['count'];
    $forecast_items = mysqli_query($conn, "SELECT f.*, p.product_name FROM forecasts f JOIN products p ON f.product_id = p.id ORDER BY f.id DESC LIMIT 10");
}

// ─── Staff queries ────────────────────────────────────────────────
if ($role === 'staff') {
    $recent_purchases = mysqli_query($conn, "SELECT pu.*, s.supplier_name FROM purchases pu LEFT JOIN suppliers s ON pu.supplier_id = s.id ORDER BY pu.id DESC LIMIT 10");

    $purchase_summary_this_month = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS total FROM purchases WHERE MONTH(purchase_date) = MONTH(CURDATE()) AND YEAR(purchase_date) = YEAR(CURDATE())"));

    $stock_summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) AS total_qty, COALESCE(SUM(quantity * purchase_price), 0) AS total_value, COUNT(*) AS product_count FROM products WHERE status='Active'"));

    $purchase_chart_staff = mysqli_query($conn, "SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS month, COALESCE(SUM(total_amount), 0) AS total FROM purchases WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(purchase_date, '%Y-%m') ORDER BY month ASC");
    $staff_pch_labels = [];
    $staff_pch_values = [];
    while ($row = mysqli_fetch_assoc($purchase_chart_staff)) {
        $staff_pch_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $staff_pch_values[] = (float)$row['total'];
    }
}

// ─── Cashier queries ──────────────────────────────────────────────
if ($role === 'cashier') {
    $today_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count, COALESCE(SUM(grand_total), 0) AS revenue FROM sales WHERE DATE(sale_date) = CURDATE()"));
    $recent_sales = mysqli_query($conn, "SELECT s.*, u.name AS cashier FROM sales s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.id DESC LIMIT 10");
}

// ─── Helper ───────────────────────────────────────────────────────
function statCard($icon, $bg, $iconColor, $value, $label, $stagger = '')
{
    return '<div class="stat-card fade-in' . ($stagger ? ' ' . $stagger : '') . '">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 ' . $bg . ' rounded-lg flex items-center justify-center flex-shrink-0">
                    ' . $icon . '
                </div>
                <span class="text-l font-bold text-gray-900">' . $value . '</span>
            </div>
            <p class="text-xs text-gray-500">' . $label . '</p>
        </div>';
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col min-w-0">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">

                <?php if ($role === 'admin'): ?>
                    <!-- ════════════════════════ ADMIN DASHBOARD ════════════════════════ -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-6">
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
                            '',
                            'text-indigo-600',
                            $total_products,
                            'Total Products'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
                            'text-blue-600',
                            $total_categories,
                            'Categories',
                            'stagger-1'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                            'text-emerald-600',
                            $total_suppliers,
                            'Suppliers',
                            'stagger-2'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                            'text-amber-600',
                            $total_customers,
                            'Customers',
                            'stagger-3'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>',
                            'text-purple-600',
                            $total_users,
                            'Users',
                            'stagger-4'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                            'text-green-600',
                            number_format($today_sales['revenue']),
                            "Today's Revenue",
                            'stagger-5'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                            'text-rose-600',
                            number_format($total_revenue),
                            'Monthly Revenue',
                            'stagger-6'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                            'text-cyan-600',
                            number_format(max(0, $total_profit)),
                            'Total Profit',
                            'stagger-7'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>',
                            'text-red-600',
                            $low_stock_count,
                            'Low Stock Items',
                            'stagger-8'
                        ) ?>
                    </div>

                    <!-- Charts Row 1: Sales -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Daily Sales (30 Days)</h2>
                            </div>
                            <div class="card-body"><canvas id="dailySalesChart" height="120"></canvas></div>
                        </div>
                        <div class="card fade-in stagger-1">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Monthly Sales</h2>
                            </div>
                            <div class="card-body"><canvas id="monthlySalesChart" height="120"></canvas></div>
                        </div>
                    </div>

                    <!-- Charts Row 2: Profit & Products -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Profit Trend (30 Days)</h2>
                            </div>
                            <div class="card-body"><canvas id="profitChart" height="120"></canvas></div>
                        </div>
                        <div class="card fade-in stagger-1">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Top Selling Products</h2>
                            </div>
                            <div class="card-body"><canvas id="topProductsChart" height="120"></canvas></div>
                        </div>
                    </div>

                    <!-- Low Stock + Recent Sales -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Low Stock Alert</h2>
                                <?php if ($low_stock_count > 0): ?><span class="badge badge-danger"><?= $low_stock_count ?> items</span><?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($low_stock_result && mysqli_num_rows($low_stock_result) > 0): while ($p = mysqli_fetch_assoc($low_stock_result)): ?>
                                        <div class="flex items-center justify-between p-3 rounded-lg mb-2 <?= $p['quantity'] == 0 ? 'bg-red-50' : 'bg-amber-50' ?>">
                                            <div>
                                                <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($p['product_name']) ?></p>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></p>
                                            </div>
                                            <span class="font-bold text-sm <?= $p['quantity'] == 0 ? 'text-red-600' : 'text-amber-600' ?>"><?= $p['quantity'] ?></span>
                                        </div>
                                    <?php endwhile;
                                else: ?>
                                    <div class="empty-state">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <h3>All stocked up!</h3>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="lg:col-span-2 card fade-in stagger-1">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Recent Sales</h2>
                                <a href="../sale/history.php" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800">View All →</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (mysqli_num_rows($recent_sales) > 0): ?>
                                    <div class="overflow-x-auto">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Invoice</th>
                                                    <th>Date</th>
                                                    <th>Customer</th>
                                                    <th>Cashier</th>
                                                    <th class="text-right">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($s = mysqli_fetch_assoc($recent_sales)): ?>
                                                    <tr>
                                                        <td class="font-semibold"><?= htmlspecialchars($s['invoice_no']) ?></td>
                                                        <td class="text-gray-500"><?= date('d-m-Y h:i A', strtotime($s['sale_date'])) ?></td>
                                                        <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                                                        <td><?= htmlspecialchars($s['cashier'] ?? 'Admin') ?></td>
                                                        <td class="text-right font-bold text-green-600"><?= number_format($s['grand_total']) ?> Ks</td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                        </svg>
                                        <h3>No sales yet</h3>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Purchases + Purchase Chart -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="lg:col-span-2 card fade-in">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Recent Purchases</h2>
                                <a href="../purchase/index.php" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800">View All →</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (mysqli_num_rows($recent_purchases) > 0): ?>
                                    <div class="overflow-x-auto">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Invoice</th>
                                                    <th>Supplier</th>
                                                    <th>Date</th>
                                                    <th class="text-right">Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($p = mysqli_fetch_assoc($recent_purchases)): ?>
                                                    <tr>
                                                        <td class="font-semibold"><?= htmlspecialchars($p['invoice_no'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($p['supplier_name'] ?? 'N/A') ?></td>
                                                        <td class="text-gray-500"><?= date('d-m-Y', strtotime($p['purchase_date'])) ?></td>
                                                        <td class="text-right font-bold"><?= number_format($p['total_amount']) ?> Ks</td>
                                                        <td><span class="badge <?= $p['payment_status'] === 'Paid' ? 'badge-success' : 'badge-warning' ?>"><?= $p['payment_status'] ?></span></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                        </svg>
                                        <h3>No purchases yet</h3>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card fade-in stagger-1">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Monthly Purchases</h2>
                            </div>
                            <div class="card-body"><canvas id="purchaseChart" height="140"></canvas></div>
                        </div>
                    </div>

                    <!-- Forecast Summary -->
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Forecast Summary</h2>
                            </div>
                            <div class="card-body">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Products with Forecast</span>
                                        <span class="font-bold text-gray-900"><?= $forecast_total ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">High Demand</span>
                                        <span class="font-bold text-green-600"><?= $forecast_high ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Low Demand</span>
                                        <span class="font-bold text-amber-600"><?= $forecast_low ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-3 card fade-in stagger-1">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Forecast Details</h2>
                                <a href="../forecast/index.php" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800">Manage →</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($forecast_items && mysqli_num_rows($forecast_items) > 0): ?>
                                    <div class="overflow-x-auto">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Forecast Date</th>
                                                    <th>Forecast Qty</th>
                                                    <th>Demand Level</th>
                                                    <th>Recommended Stock</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($f = mysqli_fetch_assoc($forecast_items)): ?>
                                                    <tr>
                                                        <td class="font-semibold"><?= htmlspecialchars($f['product_name']) ?></td>
                                                        <td class="text-gray-500"><?= date('d-m-Y', strtotime($f['forecast_date'])) ?></td>
                                                        <td><?= $f['forecast_quantity'] ?></td>
                                                        <td>
                                                            <span class="badge <?= $f['demand_level'] === 'High' ? 'badge-success' : ($f['demand_level'] === 'Low' ? 'badge-warning' : 'badge-info') ?>">
                                                                <?= $f['demand_level'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $f['recommended_stock'] ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                        <h3>No forecasts generated yet</h3>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($role === 'staff'): ?>
                    <!-- ═══════════════════════ STAFF DASHBOARD ═════════════════════════ -->
                  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
                            'bg-indigo-100',
                            'text-indigo-600',
                            $total_products,
                            'Total Products'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
                            'bg-blue-100',
                            'text-blue-600',
                            $total_categories,
                            'Categories',
                            'stagger-1'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                            'bg-emerald-100',
                            'text-emerald-600',
                            $total_suppliers,
                            'Suppliers',
                            'stagger-2'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                            'bg-amber-100',
                            'text-amber-600',
                            $stock_summary['total_qty'],
                            'Total Stock Qty',
                            'stagger-3'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>',
                            'bg-red-100',
                            'text-red-600',
                            $low_stock_count,
                            'Low Stock Items',
                            'stagger-4'
                        ) ?>
                    </div>

                    <!-- Low Stock Alert -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Low Stock Alert</h2>
                                <?php if ($low_stock_count > 0): ?><span class="badge badge-danger"><?= $low_stock_count ?> items</span><?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php mysqli_data_seek($low_stock_result, 0);
                                if ($low_stock_result && mysqli_num_rows($low_stock_result) > 0): while ($p = mysqli_fetch_assoc($low_stock_result)): ?>
                                        <div class="flex items-center justify-between p-3 rounded-lg mb-2 <?= $p['quantity'] == 0 ? 'bg-red-50' : 'bg-amber-50' ?>">
                                            <div>
                                                <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($p['product_name']) ?></p>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></p>
                                            </div>
                                            <span class="font-bold text-sm <?= $p['quantity'] == 0 ? 'text-red-600' : 'text-amber-600' ?>"><?= $p['quantity'] ?></span>
                                        </div>
                                    <?php endwhile;
                                else: ?>
                                    <div class="empty-state">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <h3>All stocked up!</h3>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stock Summary -->
                        <div class="card fade-in stagger-1">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Stock Summary</h2>
                            </div>
                            <div class="card-body">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Total Products</span>
                                        <span class="font-bold text-gray-900"><?= $stock_summary['product_count'] ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Total Units in Stock</span>
                                        <span class="font-bold text-gray-900"><?= number_format($stock_summary['total_qty']) ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Stock Value</span>
                                        <span class="font-bold text-green-600"><?= number_format($stock_summary['total_value']) ?> Ks</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Summary -->
                        <div class="card fade-in stagger-2">
                            <div class="card-header">
                                <h2 class="text-base font-bold text-gray-900">Purchase Summary</h2>
                            </div>
                            <div class="card-body">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">This Month</span>
                                        <span class="font-bold text-gray-900"><?= $purchase_summary_this_month['count'] ?> purchases</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Monthly Total</span>
                                        <span class="font-bold text-indigo-600"><?= number_format($purchase_summary_this_month['total']) ?> Ks</span>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <canvas id="staffPurchaseChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Purchases -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <h2 class="text-base font-bold text-gray-900">Recent Purchases</h2>
                            <a href="../purchase/index.php" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800">View All →</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (mysqli_num_rows($recent_purchases) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Supplier</th>
                                                <th>Date</th>
                                                <th class="text-right">Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($p = mysqli_fetch_assoc($recent_purchases)): ?>
                                                <tr>
                                                    <td class="font-semibold"><?= htmlspecialchars($p['invoice_no'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($p['supplier_name'] ?? 'N/A') ?></td>
                                                    <td class="text-gray-500"><?= date('d-m-Y', strtotime($p['purchase_date'])) ?></td>
                                                    <td class="text-right font-bold"><?= number_format($p['total_amount']) ?> Ks</td>
                                                    <td><span class="badge <?= $p['payment_status'] === 'Paid' ? 'badge-success' : 'badge-warning' ?>"><?= $p['payment_status'] ?></span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                    </svg>
                                    <h3>No purchases yet</h3>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($role === 'cashier'): ?>
                    <!-- ═══════════════════════ CASHIER DASHBOARD ═══════════════════════ -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
                            'bg-blue-100',
                            'text-blue-600',
                            $today_data['count'],
                            "Today's Transactions"
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                            'bg-green-100',
                            'text-green-600',
                            number_format($today_data['revenue']),
                            "Today's Revenue",
                            'stagger-1'
                        ) ?>
                        <?= statCard(
                            '<svg class="w-4.5 h-4.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>',
                            'bg-indigo-100',
                            'text-indigo-600',
                            $total_products,
                            'Products Available',
                            'stagger-2'
                        ) ?>
                    </div>

                    <!-- POS Quick Access -->
                    <div class="card fade-in mb-6">
                        <div class="card-body flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Point of Sale</h2>
                                <p class="text-sm text-gray-500">Start a new sale or view transaction history</p>
                            </div>
                            <a href="../sale/pos.php" class="btn btn-primary btn-lg px-8">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                                </svg>
                                Open POS
                            </a>
                        </div>
                    </div>

                    <!-- Recent Sales -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <h2 class="text-base font-bold text-gray-900">Recent Sales</h2>
                            <a href="../sale/history.php" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800">View All →</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (mysqli_num_rows($recent_sales) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Date</th>
                                                <th>Customer</th>
                                                <th class="text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($s = mysqli_fetch_assoc($recent_sales)): ?>
                                                <tr>
                                                    <td class="font-semibold"><?= htmlspecialchars($s['invoice_no']) ?></td>
                                                    <td class="text-gray-500"><?= date('d-m-Y h:i A', strtotime($s['sale_date'])) ?></td>
                                                    <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                                                    <td class="text-right font-bold text-green-600"><?= number_format($s['grand_total']) ?> Ks</td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                    </svg>
                                    <h3>No sales yet today</h3>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    </div>

    <script>
        <?php if ($role === 'admin'): ?>
            new Chart(document.getElementById('dailySalesChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($daily_labels) ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?= json_encode($daily_values) ?>,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2,
                        pointBackgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Revenue: ' + Number(ctx.raw).toLocaleString() + ' Ks'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.04)'
                            },
                            ticks: {
                                callback: v => v.toLocaleString()
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxTicksLimit: 15
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('monthlySalesChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($monthly_labels) ?>,
                    datasets: [{
                        label: 'Monthly Sales',
                        data: <?= json_encode($monthly_values) ?>,
                        backgroundColor: 'rgba(99, 102, 241, 0.2)',
                        borderColor: '#6366f1',
                        borderWidth: 2,
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Sales: ' + Number(ctx.raw).toLocaleString() + ' Ks'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.04)'
                            },
                            ticks: {
                                callback: v => v.toLocaleString()
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('profitChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($daily_labels) ?>,
                    datasets: [{
                        label: 'Profit',
                        data: <?= json_encode($profit_values) ?>,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2,
                        pointBackgroundColor: '#059669'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Profit: ' + Number(ctx.raw).toLocaleString() + ' Ks'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.04)'
                            },
                            ticks: {
                                callback: v => v.toLocaleString()
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxTicksLimit: 15
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('topProductsChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($top_labels) ?>,
                    datasets: [{
                        label: 'Quantity Sold',
                        data: <?= json_encode($top_qty) ?>,
                        backgroundColor: ['rgba(79, 70, 229, 0.6)', 'rgba(5, 150, 105, 0.6)', 'rgba(245, 158, 11, 0.6)', 'rgba(239, 68, 68, 0.6)', 'rgba(168, 85, 247, 0.6)', 'rgba(14, 165, 233, 0.6)', 'rgba(236, 72, 153, 0.6)', 'rgba(34, 197, 94, 0.6)', 'rgba(249, 115, 22, 0.6)', 'rgba(99, 102, 241, 0.6)'],
                        borderColor: '#4f46e5',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Sold: ' + Number(ctx.raw).toLocaleString() + ' units'
                            }
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                display: false
                            }
                        },
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.04)'
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('purchaseChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($purchase_monthly_labels) ?>,
                    datasets: [{
                        label: 'Purchases',
                        data: <?= json_encode($purchase_monthly_values) ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Purchases: ' + Number(ctx.raw).toLocaleString() + ' Ks'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.04)'
                            },
                            ticks: {
                                callback: v => v.toLocaleString()
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        <?php elseif ($role === 'staff'): ?>
            <?php if (!empty($staff_pch_labels)): ?>
                new Chart(document.getElementById('staffPurchaseChart'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($staff_pch_labels) ?>,
                        datasets: [{
                            label: 'Purchases',
                            data: <?= json_encode($staff_pch_values) ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 2,
                            pointBackgroundColor: '#10b981'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => Number(ctx.raw).toLocaleString() + ' Ks'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.04)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        <?php endif; ?>
    </script>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>

</html>