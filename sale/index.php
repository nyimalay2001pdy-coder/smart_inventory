<?php
include "../includes/auth_check.php";
include "../config/database.php";

// Initialize sale cart
if (!isset($_SESSION['sale_cart'])) {
    $_SESSION['sale_cart'] = [];
}

// Generate invoice number
function generateInvoiceNo($conn)
{
    $result = mysqli_query($conn, "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM sales");
    $row = mysqli_fetch_assoc($result);
    return 'INV-' . str_pad($row['next_id'], 5, '0', STR_PAD_LEFT);
}

// ============ ADD TO CART ============
if (isset($_POST['add_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    $found = false;
    foreach ($_SESSION['sale_cart'] as &$item) {
        if ($item['product_id'] == $product_id) {
            $item['quantity'] += $qty;
            $item['total'] = $item['quantity'] * $item['price'];
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        $p = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT id, product_name, selling_price FROM products WHERE id='$product_id'"
        ));
        if ($p) {
            $_SESSION['sale_cart'][] = [
                'product_id' => $p['id'],
                'product_name' => $p['product_name'],
                'price' => $p['selling_price'],
                'quantity' => $qty,
                'total' => $qty * $p['selling_price']
            ];
        }
    }
    header("Location: index.php");
    exit;
}

// ============ REMOVE FROM CART ============
if (isset($_GET['remove'])) {
    $key = (int)$_GET['remove'];
    if (isset($_SESSION['sale_cart'][$key])) {
        unset($_SESSION['sale_cart'][$key]);
        $_SESSION['sale_cart'] = array_values($_SESSION['sale_cart']);
    }
    header("Location: index.php");
    exit;
}

// ============ UPDATE CART QUANTITIES ============
if (isset($_POST['update_cart'])) {
    foreach (($_POST['quantity'] ?? []) as $key => $qty) {
        $qty = max(1, (int)$qty);
        if (isset($_SESSION['sale_cart'][$key])) {
            $_SESSION['sale_cart'][$key]['quantity'] = $qty;
            $_SESSION['sale_cart'][$key]['total'] = $qty * $_SESSION['sale_cart'][$key]['price'];
        }
    }
    header("Location: index.php");
    exit;
}

// ============ COMPLETE SALE (with stock auto-reduce) ============
$success = null;
$error = null;
if (isset($_POST['complete_sale'])) {
    if (count($_SESSION['sale_cart']) > 0) {
        // Check stock availability first
        $insufficient = [];
        foreach ($_SESSION['sale_cart'] as $item) {
            $prod = mysqli_fetch_assoc(mysqli_query(
                $conn,
                "SELECT product_name, quantity FROM products WHERE id='{$item['product_id']}'"
            ));
            if ($prod && $prod['quantity'] < $item['quantity']) {
                $insufficient[] = $prod['product_name'] . " (available: {$prod['quantity']}, requested: {$item['quantity']})";
            }
        }

        if (count($insufficient) > 0) {
            $error = "Insufficient stock for: " . implode(", ", $insufficient);
        } else {
            $user_id = $_SESSION['user_id'] ?? null;
            if ($user_id) {
                $check_user = mysqli_query($conn, "SELECT id FROM users WHERE id='$user_id'");
                if (mysqli_num_rows($check_user) == 0) $user_id = null;
            }
            $grand_total = 0;
            foreach ($_SESSION['sale_cart'] as $item) {
                $grand_total += $item['total'];
            }
            $invoice_no = generateInvoiceNo($conn);

            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $paid_amount = (float)($_POST['paid_amount'] ?? $grand_total);
            $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name'] ?? '');

            $user_id_sql = $user_id ? "'$user_id'" : "NULL";
            mysqli_query($conn, "INSERT INTO sales(invoice_no, user_id, sale_date, grand_total, payment_method, paid_amount, customer_name)
                VALUES('$invoice_no', $user_id_sql, NOW(), '$grand_total', '$payment_method', '$paid_amount', '$customer_name')");
            $sale_id = mysqli_insert_id($conn);

            if ($sale_id) {
                foreach ($_SESSION['sale_cart'] as $item) {
                    $pid = $item['product_id'];
                    $qty = $item['quantity'];
                    $price = $item['price'];
                    $total = $item['total'];

                    mysqli_query($conn, "INSERT INTO sale_details(sale_id, product_id, quantity, selling_price, subtotal)
                        VALUES('$sale_id', '$pid', '$qty', '$price', '$total')");

                    // AUTO REDUCE STOCK
                    mysqli_query($conn, "UPDATE products SET quantity = quantity - $qty WHERE id='$pid'");
                }

                $_SESSION['last_sale_id'] = $sale_id;
                $_SESSION['sale_cart'] = [];
                header("Location: invoice.php?id=$sale_id");
                exit;
            } else {
                $error = "Failed to create sale record.";
            }
        }
    } else {
        $error = "Cart is empty.";
    }
}

// ============ CLEAR CART ============
if (isset($_GET['clear_cart'])) {
    unset($_SESSION['sale_cart']);
    header("Location: index.php");
    exit;
}

// ============ DELETE SALE (with stock restore) ============
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $details = mysqli_query($conn, "SELECT * FROM sale_details WHERE sale_id='$id'");
    while ($row = mysqli_fetch_assoc($details)) {
        $pid = $row['product_id'];
        $qty = $row['quantity'];
        mysqli_query($conn, "UPDATE products SET quantity = quantity + $qty WHERE id='$pid'");
    }
    mysqli_query($conn, "DELETE FROM sale_details WHERE sale_id='$id'");
    mysqli_query($conn, "DELETE FROM sales WHERE id='$id'");
    header("Location: index.php?tab=history");
    exit;
}

// Tab: pos (default) or history
$tab = $_GET['tab'] ?? 'pos';

// Calculate totals
$cart_subtotal = 0;
foreach (($_SESSION['sale_cart'] ?? []) as $item) {
    $cart_subtotal += $item['total'];
}
$grand_total_val = $cart_subtotal;
$invoice_no = generateInvoiceNo($conn);

// History query
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$history_sql = "
    SELECT s.*, u.name AS cashier
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE 1
";
if ($search !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $history_sql .= " AND s.invoice_no LIKE '%$safe_search%'";
}
if ($date_from !== '') {
    $history_sql .= " AND DATE(s.sale_date) >= '$date_from'";
}
if ($date_to !== '') {
    $history_sql .= " AND DATE(s.sale_date) <= '$date_to'";
}
$history_sql .= " ORDER BY s.id DESC";
$history_result = mysqli_query($conn, $history_sql);

// Report query
$report_from = $_GET['report_from'] ?? date('Y-m-01');
$report_to = $_GET['report_to'] ?? date('Y-m-t');
$report_sql = "
    SELECT s.*, u.name AS cashier
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE DATE(s.sale_date) BETWEEN '$report_from' AND '$report_to'
    ORDER BY s.sale_date DESC
";
$report_result = mysqli_query($conn, $report_sql);

$report_total_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(grand_total), 0) AS total_revenue
    FROM sales
    WHERE DATE(sale_date) BETWEEN '$report_from' AND '$report_to'
");
$report_totals = mysqli_fetch_assoc($report_total_query);

// Daily summary
$daily_sql = "
    SELECT DATE(sale_date) AS day, COUNT(*) AS sales_count, SUM(grand_total) AS daily_total
    FROM sales
    WHERE DATE(sale_date) BETWEEN '$report_from' AND '$report_to'
    GROUP BY DATE(sale_date)
    ORDER BY day DESC
";
$page_title = "Sales";
$daily_result = mysqli_query($conn, $daily_sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sales <?= $tab == 'pos' ? 'POS' : ($tab == 'history' ? 'History' : 'Report') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-6">

            <?php if ($success) { ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-xl mb-6 flex justify-between items-center">
                    <span><?= $success ?></span>
                    <button onclick="this.parentElement.remove()" class="text-green-700 font-bold">&times;</button>
                </div>
            <?php } ?>
            <?php if ($error) { ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6 flex justify-between items-center">
                    <span><?= $error ?></span>
                    <button onclick="this.parentElement.remove()" class="text-red-700 font-bold">&times;</button>
                </div>
            <?php } ?>

            <!-- Tabs -->
            <div class="flex gap-1 mb-8 bg-white p-1 rounded-xl shadow-sm w-fit">
                <a href="?tab=pos" class="px-6 py-3 rounded-lg font-semibold text-sm <?= $tab == 'pos' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100' ?>">🛒 POS</a>
                <a href="?tab=history" class="px-6 py-3 rounded-lg font-semibold text-sm <?= $tab == 'history' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100' ?>">📋 Sale History</a>
                <a href="?tab=report" class="px-6 py-3 rounded-lg font-semibold text-sm <?= $tab == 'report' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100' ?>">📊 Sales Report</a>
            </div>

            <?php if ($tab == 'pos') { ?>

                <div class="flex justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold">New Sale (POS)</h1>
                        <p class="text-gray-500">Dashboard > Sales > New Sale</p>
                    </div>
                    <div class="flex gap-5">
                        <div>
                            Invoice No
                            <div class="bg-white px-6 py-3 rounded-lg font-semibold"><?= $invoice_no ?></div>
                        </div>
                        <div>
                            Date
                            <div class="bg-white px-6 py-3 rounded-lg"><?= date('d/m/Y') ?></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">

                    <!-- Product Area -->
                    <div class="bg-white p-6 rounded-2xl shadow">
                        <h2 class="text-xl font-bold mb-4">Add Products</h2>

                        <form method="GET" class="mb-5">
                            <input name="search_product" value="<?= htmlspecialchars($_GET['search_product'] ?? '') ?>"
                                placeholder="Search product by name..." class="border w-full p-3 rounded-xl">
                        </form>

                        <div class="grid grid-cols-2 gap-5">
                            <?php
                            $search_term = $_GET['search_product'] ?? '';
                            $product_query = "SELECT * FROM products WHERE status='Active'";
                            if ($search_term !== '') {
                                $safe_term = mysqli_real_escape_string($conn, $search_term);
                                $product_query .= " AND (product_name LIKE '%$safe_term%' OR sku LIKE '%$safe_term%')";
                            }
                            $product_query .= " ORDER BY product_name";
                            $products = mysqli_query($conn, $product_query);

                            if (mysqli_num_rows($products) > 0) {
                                while ($p = mysqli_fetch_assoc($products)) { ?>
                                    <div class="border rounded-xl p-4 flex flex-col">
                                        <div class="h-24 bg-gray-100 rounded-lg mb-3 flex items-center justify-center text-4xl">
                                            📦
                                        </div>
                                        <h3 class="font-bold"><?= htmlspecialchars($p['product_name']) ?></h3>
                                        <p>Stock: <span class="font-semibold"><?= $p['quantity'] ?></span></p>
                                        <p class="font-bold text-indigo-600 text-lg"><?= number_format($p['selling_price'], 2) ?></p>
                                        <form method="POST" class="mt-3 flex gap-2">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="number" name="quantity" value="1" min="1"
                                                class="border p-2 rounded-lg w-16 text-center">
                                            <button name="add_cart"
                                                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex-1">
                                                + Add
                                            </button>
                                        </form>
                                    </div>
                                <?php }
                            } else { ?>
                                <p class="col-span-2 text-gray-500 text-center py-10">No products found</p>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Cart -->
                    <div class="bg-white p-6 rounded-2xl shadow flex flex-col">
                        <h2 class="text-xl font-bold mb-5">Current Cart</h2>

                        <?php if (count($_SESSION['sale_cart']) > 0) { ?>
                            <form method="POST">
                                <table class="w-full">
                                    <tr class="border-b text-gray-500">
                                        <th class="p-3 text-left">Product</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                    <?php foreach ($_SESSION['sale_cart'] as $key => $item) { ?>
                                        <tr class="border-b">
                                            <td class="p-3 font-semibold"><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td class="text-center"><?= number_format($item['price'], 2) ?></td>
                                            <td class="text-center">
                                                <input type="number" name="quantity[<?= $key ?>]"
                                                    value="<?= $item['quantity'] ?>" min="1"
                                                    class="border w-16 p-2 rounded text-center">
                                            </td>
                                            <td class="text-center font-semibold"><?= number_format($item['total'], 2) ?></td>
                                            <td class="text-center">
                                                <a href="?remove=<?= $key ?>" class="text-red-600 hover:text-red-800"
                                                    onclick="return confirm('Remove this item?')">🗑</a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </table>
                                <div class="mt-3">
                                    <button name="update_cart"
                                        class="text-indigo-600 hover:text-indigo-800 text-sm font-semibold">
                                        ↻ Update Quantities
                                    </button>
                                </div>
                            </form>

                            <div class="mt-6 space-y-3 flex-1">
                                <div class="flex justify-between text-lg">
                                    <span>Sub Total</span>
                                    <b><?= number_format($cart_subtotal, 2) ?></b>
                                </div>
                                <div class="flex justify-between text-lg">
                                    <span>Tax (0%)</span>
                                    <b>$0.00</b>
                                </div>
                                <hr>
                                <div class="flex justify-between text-xl">
                                    <span>Grand Total</span>
                                    <b class="text-indigo-600"><?= number_format($grand_total_val, 2) ?></b>
                                </div>
                            </div>

                            <div class="flex gap-5 mt-6">
                                <a href="?clear_cart=1"
                                    class="border border-red-500 text-red-500 px-8 py-3 rounded-xl text-center hover:bg-red-50"
                                    onclick="return confirm('Clear entire cart?')">
                                    🗑 Clear Cart
                                </a>
                                <button type="button" onclick="showPaymentModal()"
                                    class="bg-indigo-600 text-white flex-1 py-3 rounded-xl hover:bg-indigo-700 font-bold text-lg">
                                    🛒 Complete Sale
                                </button>
                            </div>
                        <?php } else { ?>
                            <div class="flex-1 flex items-center justify-center">
                                <div class="text-center text-gray-400">
                                    <div class="text-6xl mb-4">🛒</div>
                                    <p class="text-xl">Cart is empty</p>
                                    <p>Click <strong>+ Add</strong> on a product to start</p>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                </div>

            <?php } elseif ($tab == 'history') { ?>

                <!-- ============ HISTORY TAB ============ -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold">Sale History</h1>
                        <p class="text-gray-500">View all completed sales</p>
                    </div>
                </div>

                <!-- Search & Filter -->
                <form method="GET" class="bg-white p-5 rounded-2xl shadow flex gap-4 items-end">
                    <input type="hidden" name="tab" value="history">
                    <div class="flex-1">
                        <label class="text-xs font-semibold text-gray-500">Search Invoice</label>
                        <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by invoice no..."
                            class="w-full border p-3 rounded-xl mt-1">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500">From Date</label>
                        <input type="date" name="date_from" value="<?= $date_from ?>"
                            class="border p-3 rounded-xl mt-1">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500">To Date</label>
                        <input type="date" name="date_to" value="<?= $date_to ?>"
                            class="border p-3 rounded-xl mt-1">
                    </div>
                    <button class="bg-indigo-600 text-white px-6 py-3 rounded-xl">Search</button>
                    <a href="?tab=history" class="border px-6 py-3 rounded-xl text-center">Reset</a>
                </form>

                <!-- Table -->
                <div class="bg-white rounded-2xl shadow mt-8 p-6 overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b text-gray-500">
                                <th class="p-4">#</th>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Cashier</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($history_result) > 0) {
                                $count = 1;
                                while ($row = mysqli_fetch_assoc($history_result)) { ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-4"><?= $count++ ?></td>
                                        <td class="font-semibold"><?= htmlspecialchars($row['invoice_no']) ?></td>
                                        <td><?= date('d-m-Y h:i A', strtotime($row['sale_date'])) ?></td>
                                        <td class="text-green-600 font-bold"><?= number_format($row['grand_total'], 2) ?> Ks</td>
                                        <td>
                                            <?php
                                            $method = $row['payment_method'] ?? 'Cash';
                                            $badge = match ($method) {
                                                'Card' => 'bg-blue-100 text-blue-600',
                                                'Transfer' => 'bg-purple-100 text-purple-600',
                                                default => 'bg-green-100 text-green-600',
                                            };
                                            ?>
                                            <span class="<?= $badge ?> px-3 py-1 rounded-full text-xs font-semibold"><?= $method ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['cashier'] ?? 'Admin') ?></td>
                                        <td class="flex gap-2">
                                            <a href="invoice.php?id=<?= $row['id'] ?>"
                                                class="bg-blue-100 text-blue-600 px-3 py-2 rounded text-sm hover:bg-blue-200">View</a>
                                            <a href="?tab=history&delete_id=<?= $row['id'] ?>"
                                                onclick="return confirm('Delete this sale? Stock will be restored.')"
                                                class="bg-red-100 text-red-600 px-3 py-2 rounded text-sm hover:bg-red-200">Delete</a>
                                        </td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-gray-500">No sales found</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            <?php } elseif ($tab == 'report') { ?>

                <!-- ============ REPORT TAB ============ -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold">Sales Report</h1>
                        <p class="text-gray-500">View sales summary by date range</p>
                    </div>
                </div>

                <!-- Date Filter -->
                <form method="GET" class="bg-white p-5 rounded-2xl shadow flex gap-4 items-end">
                    <input type="hidden" name="tab" value="report">
                    <div>
                        <label class="text-xs font-semibold text-gray-500">From</label>
                        <input type="date" name="report_from" value="<?= $report_from ?>"
                            class="border p-3 rounded-xl mt-1">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500">To</label>
                        <input type="date" name="report_to" value="<?= $report_to ?>"
                            class="border p-3 rounded-xl mt-1">
                    </div>
                    <button class="bg-indigo-600 text-white px-6 py-3 rounded-xl">Generate</button>
                </form>

                <!-- Summary Cards -->
                <div class="grid grid-cols-3 gap-6 mt-8">
                    <div class="bg-white rounded-2xl shadow p-6">
                        <p class="text-gray-500 text-sm font-semibold">Total Sales</p>
                        <p class="text-3xl font-bold text-indigo-600 mt-2"><?= $report_totals['total_sales'] ?></p>
                    </div>
                    <div class="bg-white rounded-2xl shadow p-6">
                        <p class="text-gray-500 text-sm font-semibold">Total Revenue</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?= number_format($report_totals['total_revenue'], 2) ?> Ks</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow p-6">
                        <p class="text-gray-500 text-sm font-semibold">Average per Sale</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2">
                            <?= $report_totals['total_sales'] > 0 ? number_format($report_totals['total_revenue'] / $report_totals['total_sales'], 2) : '0.00' ?> Ks
                        </p>
                    </div>
                </div>

                <!-- Daily Summary -->
                <div class="bg-white rounded-2xl shadow mt-8 p-6">
                    <h2 class="text-xl font-bold mb-4">Daily Summary</h2>
                    <table class="w-full">
                        <thead>
                            <tr class="border-b text-gray-500">
                                <th class="p-3 text-left">Date</th>
                                <th class="text-left">Sales Count</th>
                                <th class="text-left">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($daily_result) > 0) {
                                while ($d = mysqli_fetch_assoc($daily_result)) { ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 font-semibold"><?= date('d-m-Y', strtotime($d['day'])) ?></td>
                                        <td><?= $d['sales_count'] ?> sale(s)</td>
                                        <td class="text-green-600 font-bold"><?= number_format($d['daily_total'], 2) ?> Ks</td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="3" class="text-center py-8 text-gray-500">No sales in this period</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sales List -->
                <div class="bg-white rounded-2xl shadow mt-8 p-6 overflow-x-auto">
                    <h2 class="text-xl font-bold mb-4">Sale Details</h2>
                    <table class="w-full">
                        <thead>
                            <tr class="border-b text-gray-500">
                                <th class="p-3 text-left">Invoice</th>
                                <th class="text-left">Date</th>
                                <th class="text-left">Payment</th>
                                <th class="text-left">Cashier</th>
                                <th class="text-left">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($report_result) > 0) {
                                while ($r = mysqli_fetch_assoc($report_result)) { ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 font-semibold"><?= htmlspecialchars($r['invoice_no']) ?></td>
                                        <td><?= date('d-m-Y h:i A', strtotime($r['sale_date'])) ?></td>
                                        <td><?= htmlspecialchars($r['payment_method'] ?? 'Cash') ?></td>
                                        <td><?= htmlspecialchars($r['cashier'] ?? 'Admin') ?></td>
                                        <td class="text-green-600 font-bold"><?= number_format($r['grand_total'], 2) ?> Ks</td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-500">No sales found</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="text-right mt-6 text-xl font-bold">
                        Total: <span class="text-green-600"><?= number_format($report_totals['total_revenue'], 2) ?> Ks</span>
                    </div>
                </div>

            <?php } ?>
            </main>
        </div>
    </div>
    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>

    <!-- Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl shadow-lg w-full max-w-sm p-7 flex flex-col gap-6">
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Complete Sale</h2>

            <form method="POST" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-800">Invoice No</label>
                    <div class="bg-[#f0f2f5] px-4 py-3 rounded-xl font-medium text-gray-700 border text-sm">
                        <?= $invoice_no ?>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-800">Customer Name</label>
                    <input type="text" name="customer_name" placeholder="Walk-in Customer"
                        class="w-full bg-white px-4 py-3 rounded-xl border border-gray-200 font-medium text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-800">Grand Total</label>
                    <div class="text-[#10b981] text-2xl font-bold tracking-tight">
                        <?= number_format($grand_total_val, 2) ?> Ks
                    </div>
                    <input type="hidden" name="grand_total" value="<?= $grand_total_val ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-800">Payment Method</label>
                    <select name="payment_method" required
                        class="w-full bg-white px-4 py-3 rounded-xl border border-gray-200 font-medium text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="Transfer">Transfer</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-800">Customer Payment</label>
                    <input type="number" id="payment_amount" name="paid_amount" step="0.01" min="0"
                        oninput="calculateChange()" required
                        class="w-full bg-white px-4 py-3 rounded-xl border border-gray-200 font-medium text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-800">Change</label>
                    <div id="changeDisplay" class="text-[#10b981] text-2xl font-bold tracking-tight">
                        0.00 Ks
                    </div>
                </div>

                <div class="flex gap-3 mt-2">
                    <button type="button" onclick="hidePaymentModal()"
                        class="flex-1 py-3 border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl text-sm font-semibold transition">
                        Cancel
                    </button>
                    <button type="submit" name="complete_sale"
                        class="flex-1 py-3 bg-[#1d4ed8] hover:bg-blue-700 text-white rounded-xl text-sm font-semibold tracking-wide shadow-sm transition">
                        Confirm Sale
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showPaymentModal() {
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('payment_amount').value = '';
            document.getElementById('changeDisplay').textContent = '0.00 Ks';
        }

        function hidePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }

        function calculateChange() {
            const grandTotal = <?= $grand_total_val ?>;
            const payment = parseFloat(document.getElementById('payment_amount').value) || 0;
            const change = Math.max(0, payment - grandTotal);
            document.getElementById('changeDisplay').textContent = change.toFixed(2) + ' Ks';
        }
    </script>
</body>

</html>