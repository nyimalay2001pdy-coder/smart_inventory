<?php
include "../includes/auth_check.php";
requireAdmin();
include "../config/database.php";

$page_title = "Forecast";
$action = $_GET['action'] ?? 'dashboard';

// Generate forecast using moving average
function calculateForecast($conn, $product_id, $days = 30) {
    $result = mysqli_query($conn, "
        SELECT SUM(sd.quantity) AS total_qty
        FROM sale_details sd
        JOIN sales s ON sd.sale_id = s.id
        WHERE sd.product_id = '$product_id'
        AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    ");
    $row = mysqli_fetch_assoc($result);
    $total = (int)$row['total_qty'];
    $daily_avg = $days > 0 ? $total / $days : 0;
    return [
        'total_sold' => $total,
        'daily_avg' => round($daily_avg, 1),
        'forecast_7' => round($daily_avg * 7),
        'forecast_30' => round($daily_avg * 30),
    ];
}

// Run forecast for all products
if ($action === 'generate') {
    $products = mysqli_query($conn, "SELECT id, product_name, quantity, minimum_stock FROM products WHERE status='Active'");
    mysqli_query($conn, "DELETE FROM forecasts WHERE forecast_date < CURDATE()");

    while ($p = mysqli_fetch_assoc($products)) {
        $forecast = calculateForecast($conn, $p['id'], 30);
        $demand = 'Medium';
        $ratio = $forecast['daily_avg'];
        if ($ratio > 5) $demand = 'High';
        elseif ($ratio < 1) $demand = 'Low';

        $recommended = max($forecast['forecast_30'], $p['minimum_stock'] * 2);
        if ($p['quantity'] < $p['minimum_stock']) {
            $recommended = max($recommended, $p['minimum_stock'] * 3 - $p['quantity']);
        }

        $stmt = $conn->prepare("INSERT INTO forecasts (product_id, forecast_date, forecast_quantity, demand_level, recommended_stock, method) VALUES (?, CURDATE(), ?, ?, ?, 'moving_average') ON DUPLICATE KEY UPDATE forecast_quantity = VALUES(forecast_quantity), demand_level = VALUES(demand_level), recommended_stock = VALUES(recommended_stock)");
        $stmt->bind_param("iisi", $p['id'], $forecast['forecast_30'], $demand, $recommended);
        $stmt->execute();
    }
    header("Location: index.php?generated=1");
    exit;
}

$generated = isset($_GET['generated']);

// Dashboard stats
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM products WHERE status='Active'"))['count'];
$total_forecast = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(forecast_quantity), 0) AS total FROM forecasts WHERE forecast_date = CURDATE()"))['total'];
$need_restock = mysqli_num_rows(mysqli_query($conn, "
    SELECT f.id FROM forecasts f
    JOIN products p ON f.product_id = p.id
    WHERE f.forecast_date = CURDATE() AND p.quantity < f.recommended_stock
"));

$high_demand = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM forecasts WHERE forecast_date = CURDATE() AND demand_level='High'"))['count'];

// Forecast data for charts
$products_forecast = mysqli_query($conn, "
    SELECT p.product_name, p.quantity, f.forecast_quantity, f.demand_level, f.recommended_stock
    FROM forecasts f
    JOIN products p ON f.product_id = p.id
    WHERE f.forecast_date = CURDATE()
    ORDER BY f.forecast_quantity DESC LIMIT 15
");

$high_demand_products = mysqli_query($conn, "
    SELECT p.product_name, p.quantity, f.forecast_quantity, f.recommended_stock
    FROM forecasts f
    JOIN products p ON f.product_id = p.id
    WHERE f.forecast_date = CURDATE() AND f.demand_level = 'High'
    ORDER BY f.forecast_quantity DESC
");

$low_stock_forecast = mysqli_query($conn, "
    SELECT p.product_name, p.quantity, p.minimum_stock, f.forecast_quantity, f.recommended_stock
    FROM forecasts f
    JOIN products p ON f.product_id = p.id
    WHERE f.forecast_date = CURDATE() AND p.quantity < f.recommended_stock
    ORDER BY (f.recommended_stock - p.quantity) DESC LIMIT 10
");

// Chart labels and data
$chart_labels = [];
$chart_forecast = [];
$chart_current = [];
$chart_demand = [];
while ($p = mysqli_fetch_assoc($products_forecast)) {
    $chart_labels[] = $p['product_name'];
    $chart_forecast[] = (int)$p['forecast_quantity'];
    $chart_current[] = (int)$p['quantity'];
    $chart_demand[] = $p['demand_level'];
}

// Sales trend for last 30 days
$sales_trend = mysqli_query($conn, "
    SELECT DATE(sale_date) AS day, COALESCE(SUM(grand_total), 0) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(sale_date) ORDER BY day ASC
");
$trend_labels = [];
$trend_values = [];
while ($t = mysqli_fetch_assoc($sales_trend)) {
    $trend_labels[] = date('d M', strtotime($t['day']));
    $trend_values[] = (float)$t['total'];
}

// Accuracy calculation (simple correlation between forecast and actual)
$accuracy_data = mysqli_query($conn, "
    SELECT f.forecast_quantity,
           COALESCE((SELECT SUM(sd.quantity) FROM sale_details sd JOIN sales s ON sd.sale_id = s.id WHERE sd.product_id = f.product_id AND s.sale_date >= f.forecast_date AND s.sale_date < DATE_ADD(f.forecast_date, INTERVAL 30 DAY)), 0) AS actual_sold
    FROM forecasts f
    WHERE f.forecast_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND f.forecast_quantity > 0
    LIMIT 50
");
$total_error = 0;
$count = 0;
while ($a = mysqli_fetch_assoc($accuracy_data)) {
    $diff = abs($a['forecast_quantity'] - $a['actual_sold']);
    $total_error += $diff / max($a['forecast_quantity'], 1);
    $count++;
}
$accuracy = $count > 0 ? max(0, min(100, 100 - ($total_error / $count) * 100)) : 85;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Demand Forecast</h1>
                    <p class="text-gray-500 mt-1">Predict future demand and plan inventory</p>
                </div>
                <a href="?action=generate" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl hover:bg-indigo-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Generate Forecast
                </a>
            </div>

            <?php if ($generated): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-3 rounded-xl mb-6">Forecast generated successfully using Moving Average method.</div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500">Expected Demand (30d)</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1"><?= number_format($total_forecast) ?> units</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500">High Demand Products</p>
                    <p class="text-2xl font-bold text-red-600 mt-1"><?= $high_demand ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500">Products Need Restock</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1"><?= $need_restock ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <p class="text-sm text-gray-500">Forecast Accuracy</p>
                    <p class="text-2xl font-bold text-green-600 mt-1"><?= number_format($accuracy, 1) ?>%</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">Sales Trend (30 Days)</h2>
                    <canvas id="salesTrendChart" height="120"></canvas>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">Product Demand Forecast</h2>
                    <canvas id="forecastChart" height="120"></canvas>
                </div>
            </div>

            <?php if (mysqli_num_rows($low_stock_forecast) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-lg font-bold mb-4 text-amber-600">Products That May Run Out Soon</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr class="border-b text-gray-500 text-left"><th class="p-3">Product</th><th>Current Stock</th><th>Min Stock</th><th>Forecast Demand</th><th>Recommended Stock</th><th>Need to Order</th></tr></thead>
                        <tbody>
                            <?php while ($l = mysqli_fetch_assoc($low_stock_forecast)): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-semibold"><?= htmlspecialchars($l['product_name']) ?></td>
                                <td class="text-red-600 font-bold"><?= $l['quantity'] ?></td>
                                <td><?= $l['minimum_stock'] ?></td>
                                <td><?= number_format($l['forecast_quantity']) ?></td>
                                <td><?= number_format($l['recommended_stock']) ?></td>
                                <td class="text-orange-600 font-bold"><?= max(0, $l['recommended_stock'] - $l['quantity']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">High Demand Products</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead><tr class="border-b text-gray-500 text-left"><th class="p-3">Product</th><th>Stock</th><th>Forecast</th><th>Recommended</th></tr></thead>
                            <tbody>
                                <?php if (mysqli_num_rows($high_demand_products) > 0): while ($h = mysqli_fetch_assoc($high_demand_products)): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-semibold"><?= htmlspecialchars($h['product_name']) ?></td>
                                    <td><?= $h['quantity'] ?></td>
                                    <td class="text-red-600 font-bold"><?= number_format($h['forecast_quantity']) ?></td>
                                    <td class="text-green-600 font-bold"><?= number_format($h['recommended_stock']) ?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center py-8 text-gray-500">No high demand products. Generate forecast first.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold mb-4">All Product Forecasts</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead><tr class="border-b text-gray-500 text-left"><th class="p-3">Product</th><th>Current</th><th>Forecast (30d)</th><th>Demand</th><th>Rec. Stock</th></tr></thead>
                            <tbody>
                                <?php
                                $all_forecasts = mysqli_query($conn, "
                                    SELECT p.product_name, p.quantity, f.forecast_quantity, f.demand_level, f.recommended_stock
                                    FROM forecasts f JOIN products p ON f.product_id = p.id
                                    WHERE f.forecast_date = CURDATE() ORDER BY f.forecast_quantity DESC
                                ");
                                if (mysqli_num_rows($all_forecasts) > 0): while ($f = mysqli_fetch_assoc($all_forecasts)):
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-semibold"><?= htmlspecialchars($f['product_name']) ?></td>
                                    <td><?= $f['quantity'] ?></td>
                                    <td><?= number_format($f['forecast_quantity']) ?></td>
                                    <td>
                                        <span class="<?= $f['demand_level'] == 'High' ? 'bg-red-100 text-red-600' : ($f['demand_level'] == 'Medium' ? 'bg-amber-100 text-amber-600' : 'bg-green-100 text-green-600') ?> px-2 py-0.5 rounded-full text-xs">
                                            <?= $f['demand_level'] ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($f['recommended_stock']) ?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-8 text-gray-500">No forecasts yet. Click "Generate Forecast" to start.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
    const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trend_labels) ?>,
            datasets: [{
                label: 'Sales Revenue',
                data: <?= json_encode($trend_values) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: '#4f46e5',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.dataset.label + ': ' + Number(ctx.raw).toLocaleString() + ' Ks'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { callback: v => v.toLocaleString() + ' Ks' }
                },
                x: {
                    grid: { display: false },
                    ticks: { maxTicksLimit: 15 }
                }
            }
        }
    });

    const forecastCtx = document.getElementById('forecastChart').getContext('2d');
    new Chart(forecastCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Current Stock',
                    data: <?= json_encode($chart_current) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.3)',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    borderRadius: 4,
                },
                {
                    label: 'Forecast Demand',
                    data: <?= json_encode($chart_forecast) ?>,
                    backgroundColor: 'rgba(245, 158, 11, 0.3)',
                    borderColor: '#f59e0b',
                    borderWidth: 1,
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, padding: 10 } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.dataset.label + ': ' + Number(ctx.raw).toLocaleString() + ' units'
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
            }
        }
    });
    </script>
</body>
</html>
