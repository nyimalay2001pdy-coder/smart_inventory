<?php
include "../includes/auth_check.php";
include "../config/database.php";
include "../config/helpers.php";

if (!isAdmin()) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============ ADD TO CART ============
if (isset($_POST['add_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $qty = (int)$_POST['quantity'];
    $price = (float)$_POST['purchase_price'];

    if ($product_id > 0 && $qty > 0 && $price > 0) {
        $product = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT product_name FROM products WHERE id='$product_id'")
        );
        if ($product) {
            $_SESSION['cart'][] = [
                "product_id" => $product_id,
                "product_name" => $product['product_name'],
                "quantity" => $qty,
                "price" => $price,
                "subtotal" => $qty * $price
            ];
        }
    }
    header("Location: add.php");
    exit;
}

// ============ REMOVE FROM CART ============
if (isset($_GET['remove'])) {
    $key = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    header("Location: add.php");
    exit;
}

// ============ SAVE PURCHASE ============
$error_msg = '';
if (isset($_POST['save_purchase'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $payment_status = $_POST['payment_status'] ?? 'Unpaid';
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
    if ($supplier_id <= 0) {
        $error_msg = 'Please select a supplier.';
    } elseif (count($_SESSION['cart']) === 0) {
        $error_msg = 'Please add at least one product.';
    } else {
        $date_prefix = date('Ymd');
        $inv_result = mysqli_query($conn, "SELECT invoice_no FROM purchases WHERE invoice_no LIKE 'INV-$date_prefix-%' ORDER BY id DESC LIMIT 1");
        if ($inv_result && $row = mysqli_fetch_assoc($inv_result)) {
            $last_num = (int)substr($row['invoice_no'], -4);
            $next_num = $last_num + 1;
        } else {
            $next_num = 1;
        }
        $invoice_no = 'INV-' . $date_prefix . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['subtotal'];
        }

        mysqli_query($conn, "INSERT INTO purchases(supplier_id, invoice_no, purchase_date, total_amount, payment_status)
            VALUES('$supplier_id', '$invoice_no', '$purchase_date', '$total', '$payment_status')");
        $purchase_id = mysqli_insert_id($conn);

        if ($purchase_id) {
            foreach ($_SESSION['cart'] as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $price = (float)$item['price'];
                $subtotal = (float)$item['subtotal'];

                mysqli_query($conn, "INSERT INTO purchase_details(purchase_id, product_id, quantity, purchase_price, subtotal)
                    VALUES('$purchase_id', '$pid', '$qty', '$price', '$subtotal')");

                mysqli_query($conn, "UPDATE products SET quantity = quantity + $qty, purchase_price = '$price' WHERE id='$pid'");
            }

            unset($_SESSION['cart']);
            header("Location: index.php?success=" . urlencode("Purchase #$invoice_no created successfully."));
            exit;
        } else {
            $error_msg = 'Failed to create purchase. Please try again.';
        }
    }
}

// ============ FETCH DATA ============
$suppliers = mysqli_query($conn, "SELECT * FROM suppliers WHERE status='Active'");
$products = mysqli_query($conn, "SELECT * FROM products WHERE status='Active'");
$logged_user = $_SESSION['name'] ?? 'User';

$cart_subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_subtotal += $item['subtotal'];
}

$page_title = "New Purchase";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Purchase - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .summary-sticky { position: sticky; top: 6rem; }
        .product-table td, .product-table th { padding: 0.75rem 0.5rem; white-space: nowrap; }
        .product-table tbody tr { transition: background 0.12s; }
        .product-table tbody tr:hover { background: #f9fafb; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .animate-spin { animation: spin 0.8s linear infinite; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>

            <main class="p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- ===== TOP HEADER WITH BREADCRUMB ===== -->
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <div>
                            <nav class="flex items-center gap-1.5 text-sm text-gray-400 mb-1">
                                <a href="../dashboard/index.php" class="hover:text-indigo-600 transition-colors">Dashboard</a>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <a href="index.php" class="hover:text-indigo-600 transition-colors">Purchase</a>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="text-gray-700 font-medium">New Purchase</span>
                            </nav>
                            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 tracking-tight">New Purchase</h1>
                            <p class="text-sm text-gray-500 mt-0.5">Create a new purchase order and update stock</p>
                        </div>
                        <a href="index.php" class="btn btn-outline btn-sm lg:btn-sm gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Back to Purchases
                        </a>
                    </div>

                    <?php if ($error_msg): ?>
                        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl flex items-start gap-3 shadow-sm">
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-sm font-medium"><?= $error_msg ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="purchaseForm" onsubmit="return handleFormSubmit(event)">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- ===== LEFT COLUMN (2/3) ===== -->
                            <div class="lg:col-span-2 space-y-6">

                                <!-- ===== CARD: PURCHASE INFORMATION ===== -->
                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Purchase Information
                                        </h2>
                                        <span class="text-xs text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full font-medium">New</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                            <div>
                                                <label class="form-label">Invoice Number</label>
                                                <input type="text" value="<?= 'INV-' . date('Ymd') . '-....' ?>" readonly
                                                    class="form-input bg-gray-50 text-gray-500 cursor-not-allowed text-sm">
                                            </div>
                                            <div>
                                                <label class="form-label">Purchase Date</label>
                                                <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required
                                                    class="form-input text-sm">
                                            </div>
                                            <div>
                                                <label class="form-label">Supplier <span class="text-red-500">*</span></label>
                                                <select name="supplier_id" id="supplier_id" class="form-input text-sm" required>
                                                    <option value="">-- Select Supplier --</option>
                                                    <?php mysqli_data_seek($suppliers, 0); while ($s = mysqli_fetch_assoc($suppliers)) { ?>
                                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['supplier_name'])?></option>
                                                    <?php } ?>
                                                </select>
                                                <p class="form-error hidden" id="supplierError">Please select a supplier.</p>
                                            </div>
                                            <div>
                                                <label class="form-label">Staff</label>
                                                <input type="text" value="<?= htmlspecialchars($logged_user) ?>" readonly
                                                    class="form-input bg-gray-50 text-gray-500 cursor-not-allowed text-sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ===== CARD: PAYMENT INFORMATION ===== -->
                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            Payment Information
                                        </h2>
                                    </div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                                            <div>
                                                <label class="form-label">Payment Method</label>
                                                <select name="payment_method" class="form-input text-sm">
                                                    <option value="Cash">Cash</option>
                                                    <option value="Card">Card</option>
                                                    <option value="Transfer">Transfer</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label">Payment Status <span class="text-red-500">*</span></label>
                                                <select name="payment_status" class="form-input text-sm" required>
                                                    <option value="Unpaid">Unpaid</option>
                                                    <option value="Paid">Paid</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label">Paid Amount</label>
                                                <div class="relative">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">$</span>
                                                    <input type="number" name="paid_amount" value="0" min="0" step="0.01" class="form-input pl-7 text-sm">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ===== CARD: PRODUCT SECTION ===== -->
                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            Product Items
                                        </h2>
                                        <span class="text-xs text-gray-400"><?= count($_SESSION['cart']) ?> item(s)</span>
                                    </div>
                                    <div class="card-body">
                                        <!-- Add Product Row -->
                                        <div class="grid grid-cols-12 gap-3 mb-5">
                                            <div class="col-span-12 sm:col-span-4">
                                                <label class="text-xs font-semibold text-gray-600 mb-1 block">Product <span class="text-red-500">*</span></label>
                                                <select name="product_id" id="add_product" class="form-input text-sm" required onchange="autoFillPrice(this)">
                                                    <option value="">-- Select Product --</option>
                                                    <?php mysqli_data_seek($products, 0); while ($p = mysqli_fetch_assoc($products)) { ?>
                                                        <option value="<?= $p['id'] ?>" data-price="<?= $p['purchase_price'] ?>"><?= htmlspecialchars($p['product_name'])?> (Stock: <?= $p['quantity'] ?>)</option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-span-6 sm:col-span-3">
                                                <label class="text-xs font-semibold text-gray-600 mb-1 block">Quantity <span class="text-red-500">*</span></label>
                                                <input type="number" name="quantity" id="add_qty" value="1" min="1" class="form-input text-sm">
                                            </div>
                                            <div class="col-span-6 sm:col-span-3">
                                                <label class="text-xs font-semibold text-gray-600 mb-1 block">Unit Cost <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">$</span>
                                                    <input type="number" name="purchase_price" id="add_price" value="0" min="0" step="0.01" class="form-input pl-7 text-sm">
                                                </div>
                                            </div>
                                            <div class="col-span-12 sm:col-span-2 flex items-end">
                                                <button type="submit" name="add_cart" class="btn btn-primary w-full justify-center text-sm h-[42px]">
                                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    <span class="hidden sm:inline">Add</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Products Table -->
                                        <div class="overflow-x-auto -mx-1">
                                            <table class="w-full product-table">
                                                <thead>
                                                    <tr class="border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                                        <th class="text-left">#</th>
                                                        <th class="text-left">Product</th>
                                                        <th class="text-center">Qty</th>
                                                        <th class="text-center">Unit Cost</th>
                                                        <th class="text-center">Total</th>
                                                        <th class="text-center w-16">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($_SESSION['cart']) > 0): $i = 1; ?>
                                                        <?php foreach ($_SESSION['cart'] as $key => $item): ?>
                                                            <tr class="border-b border-gray-100 text-sm">
                                                                <td class="text-gray-400 font-mono"><?= sprintf('%02d', $i++) ?></td>
                                                                <td class="font-medium text-gray-800"><?= htmlspecialchars($item['product_name']) ?></td>
                                                                <td class="text-center"><?= $item['quantity'] ?></td>
                                                                <td class="text-center">$<?= number_format($item['price'], 2) ?></td>
                                                                <td class="text-center font-semibold text-indigo-600">$<?= number_format($item['subtotal'], 2) ?></td>
                                                                <td class="text-center">
                                                                    <button type="button" onclick="removeCartItem(<?= $key ?>)" class="text-red-400 hover:text-red-600 transition p-1" title="Remove">
                                                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-10 text-gray-400">
                                                                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                                                <p class="text-sm">No products added yet.</p>
                                                                <p class="text-xs mt-0.5">Search and add products above.</p>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- ===== RIGHT COLUMN (1/3) — STICKY SUMMARY ===== -->
                            <div class="lg:col-span-1">
                                <div class="summary-sticky">
                                    <div class="card">
                                        <div class="card-header">
                                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                Purchase Summary
                                            </h2>
                                        </div>
                                        <div class="card-body space-y-4">
                                            <div>
                                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Subtotal</label>
                                                <div class="mt-1 flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2.5">
                                                    <span class="text-sm text-gray-500">Items total</span>
                                                    <span class="text-lg font-bold text-gray-800" id="summarySubtotal">$<?= number_format($cart_subtotal, 2) ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Discount</label>
                                                <div class="relative mt-1">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium">$</span>
                                                    <input type="number" name="discount" id="discount" value="0" min="0" step="0.01"
                                                        class="form-input pl-7 text-sm" oninput="recalcTotals()">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tax</label>
                                                <div class="relative mt-1">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium">$</span>
                                                    <input type="number" name="tax" id="tax" value="0" min="0" step="0.01"
                                                        class="form-input pl-7 text-sm" oninput="recalcTotals()">
                                                </div>
                                            </div>
                                            <div class="border-t border-gray-200"></div>
                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Grand Total</label>
                                                </div>
                                                <div class="flex items-center justify-between bg-indigo-50 rounded-xl px-4 py-3 border border-indigo-100">
                                                    <span class="text-sm font-medium text-indigo-600">Total amount</span>
                                                    <span class="text-2xl font-extrabold text-indigo-700" id="grandTotal">$<?= number_format($cart_subtotal, 2) ?></span>
                                                </div>
                                            </div>

                                            <input type="hidden" name="save_purchase" id="savePurchaseField" value="0">
                                            <button type="button" onclick="showConfirmModal()" id="saveBtn" class="btn btn-primary w-full justify-center btn-lg gap-2 mt-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                                Save Purchase
                                            </button>
                                            <p class="text-xs text-center text-gray-400">Review details before saving</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- ===== CONFIRMATION MODAL ===== -->
    <div id="confirmModal" class="modal-overlay hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-2xl" style="animation: modalIn 0.2s ease-out;">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Confirm Purchase</h3>
                <p class="text-sm text-gray-500 mt-1">Please review the summary before saving.</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 space-y-2.5 text-sm mb-5">
                <div class="flex justify-between"><span class="text-gray-500">Items</span><span class="font-semibold" id="confirmItems">0</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="font-semibold" id="confirmSubtotal">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Discount</span><span class="font-semibold text-red-500" id="confirmDiscount">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Tax</span><span class="font-semibold text-green-600" id="confirmTax">$0.00</span></div>
                <div class="border-t border-gray-200 pt-2.5 flex justify-between"><span class="font-bold text-gray-800">Grand Total</span><span class="font-bold text-indigo-600" id="confirmTotal">$0.00</span></div>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeConfirmModal()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
                <button type="button" onclick="submitForm()" class="btn btn-primary flex-1 justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Confirm & Save
                </button>
            </div>
        </div>
    </div>

    <!-- ===== REMOVE CONFIRM MODAL ===== -->
    <div id="removeModal" class="modal-overlay hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl" style="animation: modalIn 0.2s ease-out;">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Remove Item</h3>
                <p class="text-sm text-gray-500 mt-1">This item will be removed from the cart.</p>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeRemoveModal()" class="btn btn-secondary flex-1 justify-center">Keep</button>
                <a href="#" id="removeConfirmLink" class="btn btn-danger flex-1 justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Remove
                </a>
            </div>
        </div>
    </div>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/footer.php"; ?>

    <script>
        function autoFillPrice(el) {
            const price = el.options[el.selectedIndex]?.dataset.price;
            if (price && parseFloat(price) > 0) {
                document.getElementById('add_price').value = parseFloat(price).toFixed(2);
            } else {
                document.getElementById('add_price').value = '0';
            }
        }

        function handleFormSubmit(e) {
            if (e.submitter && e.submitter.name === 'add_cart') return true;
            e.preventDefault();
            showConfirmModal();
            return false;
        }

        function recalcTotals() {
            const subtotalTxt = document.getElementById('summarySubtotal').textContent.replace(/[$,]/g, '');
            const subtotal = parseFloat(subtotalTxt) || 0;
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const tax = parseFloat(document.getElementById('tax').value) || 0;
            const grand = Math.max(0, subtotal - discount + tax);
            document.getElementById('grandTotal').textContent = '$' + grand.toFixed(2);
        }

        let removeKey = null;
        function removeCartItem(key) {
            removeKey = key;
            document.getElementById('removeConfirmLink').href = '?remove=' + key;
            document.getElementById('removeModal').classList.remove('hidden');
        }
        function closeRemoveModal() {
            document.getElementById('removeModal').classList.add('hidden');
            removeKey = null;
        }

        function showConfirmModal() {
            const supplierId = document.getElementById('supplier_id').value;
            if (!supplierId) {
                document.getElementById('supplierError').classList.remove('hidden');
                document.getElementById('supplier_id').focus();
                return;
            }
            document.getElementById('supplierError').classList.add('hidden');

            const rows = document.querySelectorAll('.product-table tbody tr');
            const hasItems = rows.length > 0 && !(rows.length === 1 && rows[0].querySelector('td[colspan]'));
            if (!hasItems) {
                showToast('error', 'Please add at least one product to the cart.');
                return;
            }

            const items = document.querySelectorAll('.product-table tbody tr:not(:has(td[colspan]))');
            document.getElementById('confirmItems').textContent = items.length;

            const subtotalTxt = document.getElementById('summarySubtotal').textContent.replace(/[$,]/g, '');
            const discount = document.getElementById('discount').value || '0';
            const tax = document.getElementById('tax').value || '0';
            const grandTxt = document.getElementById('grandTotal').textContent.replace('$', '');

            document.getElementById('confirmSubtotal').textContent = '$' + Math.max(0, parseFloat(subtotalTxt) || 0).toFixed(2);
            document.getElementById('confirmDiscount').textContent = '$' + Math.max(0, parseFloat(discount)).toFixed(2);
            document.getElementById('confirmTax').textContent = '$' + Math.max(0, parseFloat(tax)).toFixed(2);
            document.getElementById('confirmTotal').textContent = '$' + grandTxt;

            document.getElementById('confirmModal').classList.remove('hidden');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
        }

        function submitForm() {
            document.getElementById('savePurchaseField').value = '1';
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Saving...';
            document.getElementById('purchaseForm').submit();
        }


    </script>
</body>
</html>
