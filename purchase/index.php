<?php
include "../includes/auth_check.php";
protectPurchases('view');
include "../config/database.php";
include "../config/helpers.php";

$is_admin = isAdmin();
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$show_edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : null;
$show_view_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : null;

// ============ ADD PAYMENT ============
if (isset($_POST['add_payment'])) {
    $purchase_id = (int)$_POST['purchase_id'];
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? '';

    if ($purchase_id > 0 && $payment_amount > 0) {
        $purchase = mysqli_fetch_assoc(mysqli_query($conn, "SELECT total_amount FROM purchases WHERE id='$purchase_id'"));
        $grand_total = (float)$purchase['total_amount'];

        // Calculate remaining balance and new status
        $amtCol = getPaymentAmountCol($conn, 'purchase_payments');
        $total_paid_result = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM($amtCol), 0) AS total_paid FROM purchase_payments WHERE purchase_id='$purchase_id'"));
        $existing_paid = (float)$total_paid_result['total_paid'];
        $new_total_paid = $existing_paid + $payment_amount;
        $remaining = max(0, $grand_total - $new_total_paid);
        
        if ($new_total_paid >= $grand_total) {
            $new_status = 'Paid';
        } elseif ($new_total_paid > 0) {
            $new_status = 'Partial';
        } else {
            $new_status = 'Unpaid';
        }

        $insAmtCol = getPaymentAmountCol($conn, 'purchase_payments');
        $hasExtra = columnExists($conn, 'purchase_payments', 'remaining_balance');
        if ($hasExtra) {
            $cash_amount = ($payment_method === 'Cash') ? $payment_amount : 0;
            $kbzpay_amount = ($payment_method === 'KBZPay') ? $payment_amount : 0;
            if (columnExists($conn, 'purchase_payments', 'cash_amount')) {
                mysqli_query($conn, "INSERT INTO purchase_payments(purchase_id, payment_method, cash_amount, kbzpay_amount, $insAmtCol, remaining_balance, payment_status, payment_date, notes)
                    VALUES('$purchase_id', '$payment_method', '$cash_amount', '$kbzpay_amount', '$payment_amount', '$remaining', '$new_status', '$payment_date', '$notes')");
            } else {
                mysqli_query($conn, "INSERT INTO purchase_payments(purchase_id, payment_method, $insAmtCol, remaining_balance, payment_status, payment_date, notes)
                    VALUES('$purchase_id', '$payment_method', '$payment_amount', '$remaining', '$new_status', '$payment_date', '$notes')");
            }
        } else {
            mysqli_query($conn, "INSERT INTO purchase_payments(purchase_id, payment_method, $insAmtCol, payment_date, notes)
                VALUES('$purchase_id', '$payment_method', '$payment_amount', '$payment_date', '$notes')");
        }

        if (columnExists($conn, 'purchases', 'status')) {
            mysqli_query($conn, "UPDATE purchases SET status='$new_status' WHERE id='$purchase_id'");
        }

        // Update supplier outstanding balance using helper
        $sup_id_for_bal = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT supplier_id FROM purchases WHERE id = $purchase_id"))['supplier_id'];
        recalcSupplierBalance($conn, $sup_id_for_bal);

        header("Location: index.php?view_id=$purchase_id&success=" . urlencode("Payment added successfully."));
        exit;
    }
}

// ============ DELETE ============
if (isset($_GET['confirm_delete'])) {
    $id = (int)$_GET['confirm_delete'];

    // Get supplier_id before deletion
    $del_sup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT supplier_id FROM purchases WHERE id='$id'"));
    $del_supplier_id = $del_sup ? (int)$del_sup['supplier_id'] : 0;

    $details = mysqli_query($conn, "SELECT * FROM purchase_details WHERE purchase_id='$id'");
    while ($row = mysqli_fetch_assoc($details)) {
        $product_id = $row['product_id'];
        $qty = $row['quantity'];
        mysqli_query($conn, "UPDATE products SET current_stock = current_stock - $qty WHERE id='$product_id'");
    }

    mysqli_query($conn, "DELETE FROM purchase_payments WHERE purchase_id='$id'");
    mysqli_query($conn, "DELETE FROM purchase_details WHERE purchase_id='$id'");
    mysqli_query($conn, "DELETE FROM purchases WHERE id='$id'");

    // Recalculate supplier outstanding balance after deletion
    if ($del_supplier_id > 0) {
        recalcSupplierBalance($conn, $del_supplier_id);
    }

    header("Location: index.php?success=" . urlencode("Purchase deleted and stock rolled back."));
    exit;
}

// ============ UPDATE (EDIT) ============
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $detail_ids = $_POST['detail_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['purchase_price'] ?? [];
    $product_ids = $_POST['product_id'] ?? [];

    $new_total = 0;
    foreach ($detail_ids as $i => $detail_id) {
        $detail_id = (int)$detail_id;
        $qty = (int)($quantities[$i] ?? 0);
        $price = (float)($prices[$i] ?? 0);
        $product_id = (int)($product_ids[$i] ?? 0);
        if ($detail_id <= 0 || $product_id <= 0) continue;
        $subtotal = $qty * $price;

        $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT quantity FROM purchase_details WHERE id='$detail_id'"));
        $old_qty = $old ? (int)$old['quantity'] : 0;
        $diff = $qty - $old_qty;

        mysqli_query($conn, "UPDATE products SET current_stock = current_stock + $diff, purchase_price='$price' WHERE id='$product_id'");
        mysqli_query($conn, "UPDATE purchase_details SET quantity='$qty', purchase_price='$price', subtotal='$subtotal' WHERE id='$detail_id'");
        $new_total += $subtotal;
    }

    mysqli_query($conn, "UPDATE purchases SET supplier_id='$supplier_id', total_amount='$new_total' WHERE id='$id'");

    // Recalculate payment status
    $editAmtCol = getPaymentAmountCol($conn, 'purchase_payments');
    $total_paid_result = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM($editAmtCol), 0) AS total_paid FROM purchase_payments WHERE purchase_id='$id'"));
    $total_paid = (float)$total_paid_result['total_paid'];
    if ($new_total > 0) {
        if ($total_paid >= $new_total) $new_status = 'Paid';
        elseif ($total_paid > 0) $new_status = 'Partial';
        else $new_status = 'Unpaid';
    } else {
        $new_status = 'Unpaid';
    }
    if (columnExists($conn, 'purchases', 'status')) {
        mysqli_query($conn, "UPDATE purchases SET status='$new_status' WHERE id='$id'");
    }

    // Recalculate supplier outstanding balance
    recalcSupplierBalance($conn, $supplier_id);

    header("Location: index.php?success=" . urlencode("Purchase #$id updated successfully."));
    exit;
}

// ============ SEARCH / FILTER ============
$sql = "SELECT p.*, s.supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE 1";
if ($search) {
    $safe = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (s.supplier_name LIKE '%$safe%' OR p.invoice_no LIKE '%$safe%')";
}
if ($status_filter && columnExists($conn, 'purchases', 'status')) {
    $sql .= " AND p.status = '$status_filter'";
}
$sql .= " ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);

$all_products = mysqli_query($conn, "SELECT * FROM products WHERE status='Active'");
$all_suppliers = mysqli_query($conn, "SELECT * FROM suppliers WHERE status='Active'");

// ============ EDIT MODAL DATA ============
$edit_purchase = null;
$edit_details = null;
if ($show_edit_id) {
    $edit_purchase = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM purchases WHERE id='$show_edit_id'"));
    if ($edit_purchase) {
        $edit_details = mysqli_query(
            $conn,
            "SELECT d.*, p.product_name FROM purchase_details d
             INNER JOIN products p ON d.product_id = p.id
             WHERE d.purchase_id='$show_edit_id'"
        );
    }
}

// ============ VIEW MODAL DATA ============
$view_purchase = null;
$view_details = null;
$view_payments = null;
if ($show_view_id) {
    $view_purchase = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT pu.*, s.supplier_name, s.phone, s.address
         FROM purchases pu
         LEFT JOIN suppliers s ON pu.supplier_id = s.id
         WHERE pu.id='$show_view_id'"
    ));
    if ($view_purchase) {
        $view_details = mysqli_query(
            $conn,
            "SELECT d.*, p.product_name FROM purchase_details d
             INNER JOIN products p ON d.product_id = p.id
             WHERE d.purchase_id='$show_view_id'"
        );
        $view_payments = mysqli_query(
            $conn,
            "SELECT * FROM purchase_payments WHERE purchase_id='$show_view_id' ORDER BY payment_date ASC, id ASC"
        );
    }
}

$success_msg = $_GET['success'] ?? '';
$page_title = "Purchase Management";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    <?php if ($success_msg): ?>
                        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-xl flex items-start gap-3 shadow-sm">
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium"><?= htmlspecialchars($success_msg) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <form method="GET" class="flex flex-wrap items-center gap-3 flex-1">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search supplier or invoice..." class="form-input flex-1 min-w-[200px]">
                            <select name="status" class="form-input w-auto">
                                <option value="">All Status</option>
                                <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="Partial" <?= $status_filter === 'Partial' ? 'selected' : '' ?>>Partial</option>
                                <option value="Unpaid" <?= $status_filter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                            <button class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn btn-outline">Reset</a>
                        </form>
                        <a href="add.php" class="btn btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Purchase
                        </a>
                    </div>

                    <!-- Table -->
                    <div class="card overflow-hidden">
                        <div class="table-wrap">
                            <table class="data-table w-full">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice</th>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th class="num">Amount</th>
                                        <th class="center">Payment</th>
                                        <th class="center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): $count = 1;
                                        while ($row = mysqli_fetch_assoc($result)):
                                            $amtCol = getPaymentAmountCol($conn, 'purchase_payments');
                                            $total_paid_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM($amtCol), 0) AS tp FROM purchase_payments WHERE purchase_id='{$row['id']}'"));
                                            $total_paid = (float)$total_paid_q['tp'];
                                            $remaining = (float)$row['total_amount'] - $total_paid;
                                        ?>
                                            <tr>
                                                <td><?= $count++ ?></td>
                                                <td class="font-semibold"><?= htmlspecialchars($row['invoice_no'] ?? '#' . $row['id']) ?></td>
                                                <td><?= $row['purchase_date'] ?></td>
                                                <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                                                <td class="num">
                                                    <div class="text-sm font-bold"><?= number_format($row['total_amount'], 2) ?></div>
                                                    <?php if ($total_paid > 0 && $remaining > 0): ?>
                                                        <div class="text-xs text-amber-600">Paid: <?= number_format($total_paid, 2) ?> | Bal: <?= number_format($remaining, 2) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="center">
                                                    <?php
                                                    $ta = (float)$row['total_amount'];
                                                    if ($ta > 0 && $total_paid >= $ta) $row_status = 'Paid';
                                                    elseif ($total_paid > 0) $row_status = 'Partial';
                                                    else $row_status = 'Unpaid';
                                                    $statusClass = match($row_status) {
                                                        'Paid' => 'badge-success',
                                                        'Partial' => 'badge-warning',
                                                        default => 'badge-danger'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <span class="badge-dot"></span>
                                                        <?= $row_status ?>
                                                    </span>
                                                </td>
                                                <td class="center">
                                                    <div class="actions">
                                                        <a href="?view_id=<?= $row['id'] ?>" class="btn btn-sm bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg">View</a>
                                                        <?php if (checkPermission('purchases', 'edit')): ?>
                                                            <a href="?edit_id=<?= $row['id'] ?>" class="btn btn-sm bg-green-50 text-green-600 hover:bg-green-100 rounded-lg">Edit</a>
                                                        <?php endif; ?>
                                                        <?php if (checkPermission('purchases', 'delete')): ?>
                                                            <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['invoice_no'])) ?>', 'index.php')" class="btn btn-sm bg-red-50 text-red-600 hover:bg-red-100 rounded-lg">Delete</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-12">
                                                <div class="empty-state">
                                                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                    </svg>
                                                    <h3>No purchases found</h3>
                                                    <p>Create your first purchase to get started.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- ============ VIEW MODAL ============ -->
    <?php if ($view_purchase): ?>
        <?php
        $vp_total_paid = 0;
        $vpAmtCol = getPaymentAmountCol($conn, 'purchase_payments');
        while ($vp = mysqli_fetch_assoc($view_payments)) { $vp_total_paid += (float)$vp[$vpAmtCol]; }
        mysqli_data_seek($view_payments, 0);
        $vp_balance = (float)$view_purchase['total_amount'] - $vp_total_paid;
        ?>
        <div id="viewModal" class="modal-overlay">
            <div class="bg-white rounded-2xl p-6 lg:p-8 w-full max-w-3xl relative mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <button onclick="window.location.href='index.php'" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 dark:text-gray-300 text-2xl leading-none">&times;</button>

                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Purchase Invoice</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">#<?= htmlspecialchars($view_purchase['invoice_no'] ?? 'ID: ' . $view_purchase['id']) ?></p>
                    </div>
                    <?php
                    $vp_ta = (float)$view_purchase['total_amount'];
                    if ($vp_ta > 0 && $vp_total_paid >= $vp_ta) $vp_status = 'Paid';
                    elseif ($vp_total_paid > 0) $vp_status = 'Partial';
                    else $vp_status = 'Unpaid';
                    $vStatusClass = match($vp_status) {
                        'Paid' => 'badge-success',
                        'Partial' => 'badge-warning',
                        default => 'badge-danger'
                    };
                    ?>
                    <span class="badge <?= $vStatusClass ?>">
                        <span class="badge-dot"></span><?= $vp_status ?>
                    </span>
                </div>

                <hr class="mb-6">

                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Supplier</h4>
                        <p class="font-medium"><?= htmlspecialchars($view_purchase['supplier_name']) ?></p>
                        <?php if ($view_purchase['phone']): ?><p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($view_purchase['phone']) ?></p><?php endif; ?>
                        <?php if ($view_purchase['address']): ?><p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($view_purchase['address']) ?></p><?php endif; ?>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Details</h4>
                        <p class="text-sm">Date: <span class="font-medium"><?= $view_purchase['purchase_date'] ?></span></p>
                        <p class="text-sm">Created: <span class="font-medium"><?= $view_purchase['created_at'] ?></span></p>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Payment Summary</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Grand Total</p>
                            <p class="text-lg font-bold text-indigo-600"><?= number_format($view_purchase['total_amount'], 2) ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Paid</p>
                            <p class="text-lg font-bold text-emerald-600"><?= number_format($vp_total_paid, 2) ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Remaining Balance</p>
                            <p class="text-lg font-bold <?= $vp_balance > 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= number_format($vp_balance, 2) ?></p>
                        </div>
                    </div>
                </div>

                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Items</h4>
                <table class="data-table w-full mb-6">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="num">Qty</th>
                            <th class="num">Price</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $vi = 1; while ($row = mysqli_fetch_assoc($view_details)): ?>
                            <tr>
                                <td><?= $vi++ ?></td>
                                <td class="font-medium"><?= htmlspecialchars($row['product_name']) ?></td>
                                <td class="num"><?= $row['quantity'] ?></td>
                                <td class="num"><?= number_format($row['purchase_price'], 2) ?></td>
                                <td class="num"><?= number_format($row['subtotal'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Payment History -->
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Payment History</h4>
                <?php if (mysqli_num_rows($view_payments) > 0): ?>
                    <table class="data-table w-full mb-6">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th class="num">Amount</th>
                                <?php if (columnExists($conn, 'purchase_payments', 'advance_applied')): ?>
                                    <th class="num">Advance</th>
                                <?php endif; ?>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $pi = 1; while ($pmt = mysqli_fetch_assoc($view_payments)): ?>
                                <tr>
                                    <td><?= $pi++ ?></td>
                                    <td><?= $pmt['payment_date'] ?></td>
                                    <td>
                                        <span class="badge <?= $pmt['payment_method'] === 'Cash' ? 'badge-success' : 'badge-info' ?>">
                                            <?= $pmt['payment_method'] ?>
                                        </span>
                                    </td>
                                    <td class="num font-semibold"><?= number_format($pmt[$vpAmtCol], 2) ?></td>
                                    <?php if (columnExists($conn, 'purchase_payments', 'advance_applied')): ?>
                                        <td class="num">
                                            <?php
                                            $adv_applied = (float)($pmt['advance_applied'] ?? 0);
                                            $adv_created = (float)($pmt['advance_created'] ?? 0);
                                            if ($adv_applied > 0): ?>
                                                <span class="text-blue-600 font-semibold">-<?= number_format($adv_applied, 2) ?></span>
                                            <?php elseif ($adv_created > 0): ?>
                                                <span class="text-emerald-600 font-semibold">+<?= number_format($adv_created, 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-gray-500 text-sm"><?= htmlspecialchars($pmt['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-sm text-gray-400 mb-6">No payments recorded.</p>
                <?php endif; ?>

                <!-- Add Payment Button (only if balance remaining) -->
                <?php if ($vp_balance > 0 && $is_admin): ?>
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Add Payment</h4>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="purchase_id" value="<?= $view_purchase['id'] ?>">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Method</label>
                                    <select name="payment_method" class="form-input text-sm" required>
                                        <option value="Cash">Cash</option>
                                        <option value="KBZPay">KBZPay</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Amount (Max: <?= number_format($vp_balance, 2) ?>)</label>
                                    <input type="number" name="payment_amount" min="0.01" max="<?= $vp_balance ?>" step="0.01" value="<?= $vp_balance ?>" class="form-input text-sm" required>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Date</label>
                                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="form-input text-sm" required>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Notes (Optional)</label>
                                <input type="text" name="notes" class="form-input text-sm" placeholder="Payment notes...">
                            </div>
                            <button type="submit" name="add_payment" class="btn btn-primary gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Payment
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="text-right border-t pt-4 mt-4">
                    <a href="index.php" class="btn btn-secondary">Close</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ EDIT MODAL ============ -->
    <?php if ($edit_purchase && $edit_details): ?>
        <div id="editModal" class="modal-overlay">
            <div class="bg-white rounded-2xl p-6 lg:p-8 w-full max-w-4xl relative mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <a href="index.php" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 dark:text-gray-300 text-2xl leading-none">&times;</a>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Edit Purchase #<?= htmlspecialchars($edit_purchase['invoice_no'] ?? $edit_purchase['id']) ?></h2>

                <form method="POST">
                    <input type="hidden" name="id" value="<?= $edit_purchase['id'] ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-input">
                                <?php mysqli_data_seek($all_suppliers, 0);
                                while ($s = mysqli_fetch_assoc($all_suppliers)): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $edit_purchase['supplier_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['supplier_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Products</h4>
                    <div class="table-wrap mb-6">
                        <table class="data-table w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th class="num">Quantity</th>
                                    <th class="num">Price</th>
                                    <th class="num">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $edit_total = 0; $ei = 1;
                                while ($row = mysqli_fetch_assoc($edit_details)): $edit_total += $row['subtotal']; ?>
                                    <tr>
                                        <td><?= $ei++ ?></td>
                                        <td>
                                            <select name="product_id[]" class="form-input text-sm">
                                                <?php mysqli_data_seek($all_products, 0);
                                                while ($p = mysqli_fetch_assoc($all_products)): ?>
                                                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $row['product_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p['product_name']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <input type="hidden" name="detail_id[]" value="<?= $row['id'] ?>">
                                        </td>
                                        <td><input type="number" name="quantity[]" value="<?= $row['quantity'] ?>" min="0" class="form-input text-sm text-center"></td>
                                        <td><input type="number" name="purchase_price[]" value="<?= $row['purchase_price'] ?>" step="0.01" min="0" class="form-input text-sm text-center"></td>
                                        <td class="num"><?= number_format($row['subtotal'], 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <span class="text-lg font-bold text-gray-800 dark:text-gray-200">Total: <span class="text-indigo-600"><?= number_format($edit_total, 2) ?></span></span>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button name="update" class="btn btn-primary">Update Purchase</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>

</html>
