<?php
include "../includes/auth_check.php";
include "../config/database.php";
include "../config/helpers.php";

$role = $_SESSION['role'];

if ($role !== 'cashier' && $role !== 'admin') {
    header("Location: ../dashboard/index.php");
    exit;
}

if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

function generateInvoiceNo($conn) {
    $result = mysqli_query($conn, "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM sales");
    $row = mysqli_fetch_assoc($result);
    return 'POS-' . date('Ymd') . '-' . str_pad($row['next_id'], 4, '0', STR_PAD_LEFT);
}

// Add to cart
if (isset($_POST['add_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    $found = false;
    foreach ($_SESSION['pos_cart'] as &$item) {
        if ($item['product_id'] == $product_id) {
            $item['quantity'] += $qty;
            $item['total'] = $item['quantity'] * $item['price'];
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, product_name, selling_price, purchase_price, quantity FROM products WHERE id='$product_id' AND status='Active'"));
        if ($p && $p['quantity'] >= $qty) {
            $_SESSION['pos_cart'][] = [
                'product_id' => $p['id'],
                'product_name' => $p['product_name'],
                'price' => $p['selling_price'],
                'purchase_price' => $p['purchase_price'],
                'quantity' => $qty,
                'total' => $qty * $p['selling_price']
            ];
        }
    }
    header("Location: index.php");
    exit;
}

// Barcode search
if (isset($_POST['barcode_search'])) {
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM products WHERE barcode='$barcode' AND status='Active'"));
    if ($product) {
        $_SESSION['pos_cart'][] = [
            'product_id' => $product['id'],
            'quantity' => 1,
            'price' => 0,
            'total' => 0
        ];
    }
    header("Location: index.php");
    exit;
}

// Update cart
if (isset($_POST['update_cart'])) {
    foreach (($_POST['quantity'] ?? []) as $key => $qty) {
        $qty = max(0, (int)$qty);
        if (isset($_SESSION['pos_cart'][$key])) {
            if ($qty <= 0) {
                unset($_SESSION['pos_cart'][$key]);
            } else {
                $_SESSION['pos_cart'][$key]['quantity'] = $qty;
                $_SESSION['pos_cart'][$key]['total'] = $qty * $_SESSION['pos_cart'][$key]['price'];
            }
        }
    }
    $_SESSION['pos_cart'] = array_values($_SESSION['pos_cart']);
    header("Location: index.php");
    exit;
}

// Remove item
if (isset($_GET['remove'])) {
    $key = (int)$_GET['remove'];
    if (isset($_SESSION['pos_cart'][$key])) {
        unset($_SESSION['pos_cart'][$key]);
        $_SESSION['pos_cart'] = array_values($_SESSION['pos_cart']);
    }
    header("Location: index.php");
    exit;
}

// Complete sale with multi-payment support
$error = '';
if (isset($_POST['complete_sale'])) {
    if (count($_SESSION['pos_cart']) > 0) {
        $insufficient = [];
        foreach ($_SESSION['pos_cart'] as $item) {
            $prod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_name, quantity FROM products WHERE id='{$item['product_id']}'"));
            if ($prod && $prod['quantity'] < $item['quantity']) {
                $insufficient[] = $prod['product_name'] . " (available: {$prod['quantity']})";
            }
        }

        if (count($insufficient) > 0) {
            $error = "Insufficient stock: " . implode(", ", $insufficient);
        } else {
            $grand_total = array_sum(array_column($_SESSION['pos_cart'], 'total'));
            $discount = (float)($_POST['discount'] ?? 0);
            $grand_total -= $discount;
            if ($grand_total < 0) $grand_total = 0;

            $payment_cash = (float)($_POST['payment_cash'] ?? 0);
            $payment_card = (float)($_POST['payment_card'] ?? 0);
            $payment_transfer = (float)($_POST['payment_transfer'] ?? 0);
            $total_paid = $payment_cash + $payment_card + $payment_transfer;

            if (abs($total_paid - $grand_total) > 0.01) {
                if ($total_paid < $grand_total) {
                    $error = "Payment amount is not enough.";
                } else {
                    $error = "Payment amount exceeds total.";
                }
            } else {
                $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name'] ?? 'Walk-in Customer');
                $invoice_no = generateInvoiceNo($conn);

                // Determine primary method (highest amount) for backward compat
                $methods_map = [
                    'Cash' => $payment_cash,
                    'Card' => $payment_card,
                    'Transfer' => $payment_transfer
                ];
                arsort($methods_map);
                $primary_method = key($methods_map);

                mysqli_begin_transaction($conn);
                try {
                    $stmt = $conn->prepare("INSERT INTO sales (invoice_no, user_id, customer_name, grand_total, payment_method, paid_amount, discount, sale_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sisdddd", $invoice_no, $_SESSION['user_id'], $customer_name, $grand_total, $primary_method, $total_paid, $discount);
                    $stmt->execute();
                    $sale_id = $conn->insert_id;

                    if (!$sale_id) {
                        throw new Exception("Failed to create sale record.");
                    }

                    foreach ($_SESSION['pos_cart'] as $item) {
                        $subtotal = $item['quantity'] * $item['price'];
                        $sd_stmt = $conn->prepare("INSERT INTO sale_details (sale_id, product_id, quantity, purchase_price, selling_price, subtotal)
                            VALUES (?, ?, ?, ?, ?, ?)");
                        $sd_stmt->bind_param("iiiddd", $sale_id, $item['product_id'], $item['quantity'], $item['purchase_price'], $item['price'], $subtotal);
                        $sd_stmt->execute();

                        $stock_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                        $stock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                        $stock_stmt->execute();
                    }

                    foreach ($methods_map as $method => $amount) {
                        if ($amount > 0) {
                            $pmt_stmt = $conn->prepare("INSERT INTO payments (sale_id, payment_method, amount) VALUES (?, ?, ?)");
                            $pmt_stmt->bind_param("isd", $sale_id, $method, $amount);
                            $pmt_stmt->execute();
                        }
                    }

                    mysqli_commit($conn);

                    $_SESSION['last_sale'] = $sale_id;
                    $_SESSION['pos_cart'] = [];
                    header("Location: ../sale/invoice.php?id=$sale_id");
                    exit;
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = "Sale failed. Please try again.";
                }
            }
        }
    } else {
        $error = "Cart is empty.";
    }
}

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$product_query = "SELECT * FROM products WHERE status='Active'";
if ($search) {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $product_query .= " AND (product_name LIKE '%$safe_search%' OR sku LIKE '%$safe_search%' OR barcode LIKE '%$safe_search%')";
}
if ($category_filter) {
    $product_query .= " AND category_id = '$category_filter'";
}
$product_query .= " ORDER BY product_name ASC";
$products = mysqli_query($conn, $product_query);

$categories = mysqli_query($conn, "SELECT * FROM categories WHERE status='Active' ORDER BY name");

$cart_subtotal = array_sum(array_column($_SESSION['pos_cart'] ?? [], 'total'));
$invoice_no = generateInvoiceNo($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
        .product-card { transition: all 0.2s; }
        .product-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .product-card.out-of-stock { opacity: 0.5; pointer-events: none; }
        .cart-item:hover { background: #f9fafb; }
        .payment-input { transition: border-color 0.15s, box-shadow 0.15s; }
        .payment-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); outline: none; }
        .payment-input.error { border-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <main class="flex-1 flex flex-col">
            <div class="bg-white shadow-sm px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-bold text-gray-800">Point of Sale</h1>
                    <span class="bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full text-sm font-semibold"><?= $invoice_no ?></span>
                </div>
                <div class="text-sm text-gray-500"><?= date('d-m-Y h:i A') ?></div>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-50 border-b border-red-200 text-red-700 px-6 py-3 text-sm font-medium"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="flex-1 flex overflow-hidden">
                <div class="flex-1 p-4 overflow-y-auto">
                    <div class="flex gap-3 mb-4">
                        <form method="GET" class="flex-1 flex gap-2">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search product by name, SKU or barcode..." class="flex-1 border rounded-lg px-4 py-2.5">
                            <select name="category" class="border rounded-lg px-3 py-2.5" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= $c['id'] ?>" <?= $category_filter == $c['id'] ? 'selected' : '' ?>><?= $c['name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700">Search</button>
                            <a href="index.php" class="border px-4 py-2.5 rounded-lg hover:bg-gray-50">Reset</a>
                        </form>
                    </div>

                    <div class="product-grid">
                        <?php if (mysqli_num_rows($products) > 0): while ($p = mysqli_fetch_assoc($products)):
                            $oos = $p['quantity'] <= 0;
                        ?>
                        <div class="bg-white rounded-xl shadow-sm p-3 product-card <?= $oos ? 'out-of-stock' : '' ?>">
                            <div class="h-20 bg-gray-100 rounded-lg mb-2 flex items-center justify-center text-3xl">📦</div>
                            <h3 class="font-semibold text-sm truncate"><?= htmlspecialchars($p['product_name']) ?></h3>
                            <p class="text-xs text-gray-500">SKU: <?= htmlspecialchars($p['sku'] ?? 'N/A') ?></p>
                            <p class="text-xs <?= $p['quantity'] <= $p['minimum_stock'] ? 'text-red-500' : 'text-gray-500' ?>">Stock: <?= $p['quantity'] ?></p>
                            <p class="text-indigo-600 font-bold text-sm mt-1"><?= number_format($p['selling_price']) ?> Ks</p>
                            <form method="POST" class="mt-2 flex gap-1">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <input type="number" name="quantity" value="1" min="1" max="<?= $p['quantity'] ?>" class="border rounded-lg w-14 text-center p-1.5 text-sm">
                                <button name="add_cart" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-green-700 flex-1 <?= $oos ? 'opacity-50' : '' ?>">+ Add</button>
                            </form>
                        </div>
                        <?php endwhile; else: ?>
                        <div class="col-span-full text-center py-12 text-gray-400">
                            <p class="text-5xl mb-4">🔍</p>
                            <p class="font-semibold">No products found</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="w-96 bg-white shadow-lg flex flex-col">
                    <div class="p-4 border-b">
                        <h2 class="font-bold text-lg">Current Order</h2>
                        <p class="text-xs text-gray-500"><?= count($_SESSION['pos_cart'] ?? []) ?> item(s)</p>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4">
                        <?php if (count($_SESSION['pos_cart'] ?? []) > 0): ?>
                        <form method="POST" id="cartForm">
                            <?php foreach ($_SESSION['pos_cart'] as $key => $item): ?>
                            <div class="cart-item rounded-lg p-3 mb-2 border">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm"><?= htmlspecialchars($item['product_name']) ?></p>
                                        <p class="text-indigo-600 font-bold"><?= number_format($item['price']) ?> Ks</p>
                                    </div>
                                    <a href="?remove=<?= $key ?>" class="text-red-500 hover:text-red-700 text-lg">&times;</a>
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <input type="number" name="quantity[<?= $key ?>]" value="<?= $item['quantity'] ?>" min="0" class="border rounded-lg w-16 p-1.5 text-center text-sm">
                                    <span class="text-sm text-gray-500">x <?= number_format($item['price']) ?> Ks</span>
                                    <span class="ml-auto font-bold text-sm">= <?= number_format($item['total']) ?> Ks</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <button type="submit" name="update_cart" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800 mt-2">Update Quantities</button>
                        </form>
                        <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-full text-gray-400">
                            <p class="text-5xl mb-4">🛒</p>
                            <p class="font-semibold">Cart is empty</p>
                            <p class="text-sm">Add products to start</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($_SESSION['pos_cart'] ?? []) > 0): ?>
                    <div class="border-t p-4 space-y-2">
                        <div class="flex justify-between text-sm"><span>Subtotal</span><span class="font-semibold"><?= number_format($cart_subtotal) ?> Ks</span></div>
                        <div class="flex justify-between text-sm items-center">
                            <span>Discount</span>
                            <input type="number" form="paymentForm" name="discount" id="discountInput" value="0" min="0" class="border rounded w-24 text-right p-1 text-sm" oninput="updateTotals()">
                        </div>
                        <div class="border-t pt-2 flex justify-between text-lg">
                            <span class="font-bold">Total</span>
                            <span class="font-bold text-indigo-600" id="grandTotalDisplay"><?= number_format($cart_subtotal) ?> Ks</span>
                        </div>
                        <button onclick="showPaymentModal()" class="w-full bg-indigo-600 text-white py-3 rounded-xl hover:bg-indigo-700 font-bold text-lg flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                            Checkout
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="paymentModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
            <h2 class="text-xl font-bold">Complete Payment</h2>
            <form method="POST" id="paymentForm">
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-700">Invoice No</label>
                        <div class="bg-gray-100 px-4 py-2.5 rounded-lg text-sm font-semibold"><?= $invoice_no ?></div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-700">Customer Name</label>
                        <input type="text" name="customer_name" placeholder="Walk-in Customer" class="w-full border rounded-lg p-2.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-700">Grand Total</label>
                        <div class="text-green-600 text-2xl font-bold" id="modalTotal"><?= number_format($cart_subtotal) ?> Ks</div>
                    </div>

                    <div class="border-t pt-3 space-y-3">
                        <label class="text-xs font-semibold text-gray-700 block">Payment Methods</label>

                        <div class="flex items-center gap-3">
                            <div class="w-20 text-sm font-medium text-gray-700">Cash</div>
                            <input type="number" name="payment_cash" id="paymentCash" value="0" min="0" step="0.01"
                                class="payment-input flex-1 border rounded-lg p-2.5 text-sm"
                                oninput="calculatePayments()">
                            <span class="text-xs text-gray-500 w-16 text-right">Ks</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-20 text-sm font-medium text-gray-700">Card</div>
                            <input type="number" name="payment_card" id="paymentCard" value="0" min="0" step="0.01"
                                class="payment-input flex-1 border rounded-lg p-2.5 text-sm"
                                oninput="calculatePayments()">
                            <span class="text-xs text-gray-500 w-16 text-right">Ks</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-20 text-sm font-medium text-gray-700">Transfer</div>
                            <input type="number" name="payment_transfer" id="paymentTransfer" value="0" min="0" step="0.01"
                                class="payment-input flex-1 border rounded-lg p-2.5 text-sm"
                                oninput="calculatePayments()">
                            <span class="text-xs text-gray-500 w-16 text-right">Ks</span>
                        </div>
                    </div>

                    <div class="border-t pt-3 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold">Total Paid</span>
                            <span class="font-bold text-indigo-600" id="totalPaidDisplay">0 Ks</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold">Balance</span>
                            <span class="font-bold" id="balanceDisplay">0 Ks</span>
                        </div>
                        <div id="paymentError" class="text-red-600 text-xs font-medium hidden"></div>
                    </div>
                </div>
                <div class="flex gap-3 mt-4">
                    <button type="button" onclick="hidePaymentModal()" class="flex-1 py-2.5 border rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                    <button type="submit" name="complete_sale" id="completeSaleBtn" class="flex-1 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">Complete Sale</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const subtotal = <?= $cart_subtotal ?>;

    function updateTotals() {
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const total = Math.max(0, subtotal - discount);
        document.getElementById('grandTotalDisplay').textContent = total.toLocaleString() + ' Ks';
        document.getElementById('modalTotal').textContent = total.toLocaleString() + ' Ks';
        calculatePayments();
    }

    function showPaymentModal() {
        document.getElementById('paymentModal').classList.remove('hidden');
        document.getElementById('paymentCash').value = '0';
        document.getElementById('paymentCard').value = '0';
        document.getElementById('paymentTransfer').value = '0';
        calculatePayments();
    }

    function hidePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
    }

    function calculatePayments() {
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const total = Math.max(0, subtotal - discount);

        const cash = parseFloat(document.getElementById('paymentCash').value) || 0;
        const card = parseFloat(document.getElementById('paymentCard').value) || 0;
        const transfer = parseFloat(document.getElementById('paymentTransfer').value) || 0;
        const totalPaid = cash + card + transfer;

        document.getElementById('totalPaidDisplay').textContent = totalPaid.toLocaleString() + ' Ks';

        const balance = totalPaid - total;
        const balanceEl = document.getElementById('balanceDisplay');
        const errorEl = document.getElementById('paymentError');
        const btn = document.getElementById('completeSaleBtn');

        balanceEl.textContent = Math.abs(balance).toFixed(2) + ' Ks';

        if (Math.abs(balance) < 0.01) {
            balanceEl.className = 'font-bold text-green-600';
            errorEl.classList.add('hidden');
            btn.disabled = false;
        } else if (balance < 0) {
            balanceEl.className = 'font-bold text-red-600';
            errorEl.textContent = 'Payment amount is not enough.';
            errorEl.classList.remove('hidden');
            btn.disabled = true;
        } else {
            balanceEl.className = 'font-bold text-orange-600';
            errorEl.textContent = 'Payment amount exceeds total.';
            errorEl.classList.remove('hidden');
            btn.disabled = true;
        }
    }

    <?php if ($error): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showPaymentModal();
    });
    <?php endif; ?>
    </script>
</body>
</html>