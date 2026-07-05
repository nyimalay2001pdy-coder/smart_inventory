<?php
include "../includes/auth_check.php";
include "../config/database.php";

$sale_id = (int)($_GET['id'] ?? 0);
if ($sale_id <= 0) {
    header("Location: index.php");
    exit;
}

$sale = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT s.*, u.name AS cashier
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id='$sale_id'
"));
if (!$sale) {
    echo "Sale not found.";
    exit;
}

$items = mysqli_query($conn, "
    SELECT sd.*, p.product_name
    FROM sale_details sd
    LEFT JOIN products p ON sd.product_id = p.id
    WHERE sd.sale_id='$sale_id'
");

$subtotal = 0;
$item_rows = [];
while ($row = mysqli_fetch_assoc($items)) {
    $subtotal += $row['subtotal'];
    $item_rows[] = $row;
}

$payments = mysqli_query($conn, "
    SELECT * FROM payments
    WHERE sale_id='$sale_id'
    ORDER BY FIELD(payment_method, 'Cash', 'Card', 'Transfer')
");

$payment_details = [];
$total_paid = 0;
if (mysqli_num_rows($payments) > 0) {
    while ($p = mysqli_fetch_assoc($payments)) {
        $payment_details[] = $p;
        $total_paid += $p['amount'];
    }
} else {
    $total_paid = floatval($sale['paid_amount'] ?? $sale['grand_total']);
    if ($sale['payment_method']) {
        $payment_details[] = [
            'payment_method' => $sale['payment_method'],
            'amount' => $total_paid
        ];
    }
}

$tax = 0;
$grand_total = $subtotal;
$change = max(0, $total_paid - $grand_total);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($sale['invoice_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            #invoice-area, #invoice-area * { visibility: visible; }
            #invoice-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-[#f4f6fc] min-h-screen flex items-center justify-center p-6 font-sans">

    <div class="w-full max-w-[440px] flex flex-col items-center">

        <div id="invoice-area" class="bg-white w-full rounded-md shadow-md border border-gray-200/60 p-6 md:p-8 mb-6">

            <div class="text-center mb-5">
                <h1 class="text-lg font-bold text-gray-900 uppercase tracking-wide">Smart Inventory</h1>
                <p class="text-xs text-gray-700 font-medium mt-0.5">Thank you for shopping with us!</p>
                <h2 class="text-base font-bold text-gray-900 uppercase tracking-widest mt-3">Invoice</h2>
            </div>

            <div class="text-sm font-medium text-gray-800 space-y-1 mb-4">
                <div class="grid grid-cols-[90px_10px_1fr]">
                    <span>Invoice No</span><span>:</span><span class="font-semibold"><?= htmlspecialchars($sale['invoice_no']) ?></span>
                </div>
                <div class="grid grid-cols-[90px_10px_1fr]">
                    <span>Date</span><span>:</span><span><?= date('d-m-Y h:i A', strtotime($sale['sale_date'])) ?></span>
                </div>
                <div class="grid grid-cols-[90px_10px_1fr]">
                    <span>Cashier</span><span>:</span><span><?= htmlspecialchars($sale['cashier'] ?? 'Admin') ?></span>
                </div>
                <?php if (!empty($sale['customer_name'])) { ?>
                <div class="grid grid-cols-[90px_10px_1fr]">
                    <span>Customer</span><span>:</span><span><?= htmlspecialchars($sale['customer_name']) ?></span>
                </div>
                <?php } ?>
            </div>

            <div class="border-t border-dashed border-gray-400 my-3"></div>

            <div class="text-sm text-gray-800 font-medium space-y-2">
                <div class="grid grid-cols-[1.5fr_0.5fr_1fr_1fr] text-gray-900 font-bold">
                    <span>Item</span>
                    <span class="text-center">Qty</span>
                    <span class="text-right">Price</span>
                    <span class="text-right">Total</span>
                </div>
                <?php foreach ($item_rows as $item) { ?>
                <div class="grid grid-cols-[1.5fr_0.5fr_1fr_1fr]">
                    <span><?= htmlspecialchars($item['product_name']) ?></span>
                    <span class="text-center"><?= (int)$item['quantity'] ?></span>
                    <span class="text-right"><?= number_format($item['selling_price'], 2) ?></span>
                    <span class="text-right font-semibold"><?= number_format($item['subtotal'], 2) ?></span>
                </div>
                <?php } ?>
            </div>

            <div class="border-t border-dashed border-gray-400 my-4"></div>

            <div class="text-sm font-medium text-gray-800 space-y-1.5 ml-auto max-w-[260px]">
                <div class="grid grid-cols-[100px_10px_1fr] text-right">
                    <span class="text-left">Sub Total</span><span>:</span><span class="font-semibold"><?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="grid grid-cols-[100px_10px_1fr] text-right">
                    <span class="text-left">Tax (0%)</span><span>:</span><span><?= number_format($tax, 2) ?></span>
                </div>
                <div class="border-t border-gray-300 my-1.5 col-span-3"></div>
                <div class="grid grid-cols-[100px_10px_1fr] text-right text-gray-900 font-bold">
                    <span class="text-left">Grand Total</span><span>:</span><span><?= number_format($grand_total, 2) ?></span>
                </div>
            </div>

            <?php if (count($payment_details) > 0): ?>
            <div class="border-t border-dashed border-gray-400 my-4"></div>
            <div class="text-sm font-medium text-gray-800 space-y-1.5 ml-auto max-w-[260px]">
                <p class="font-bold text-gray-900 mb-1">Payment Details</p>
                <?php foreach ($payment_details as $pmt): ?>
                <div class="grid grid-cols-[100px_10px_1fr] text-right">
                    <span class="text-left"><?= htmlspecialchars($pmt['payment_method']) ?></span><span>:</span><span class="font-semibold"><?= number_format($pmt['amount'], 2) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="border-t border-gray-300 my-1.5 col-span-3"></div>
                <div class="grid grid-cols-[100px_10px_1fr] text-right font-bold text-gray-900">
                    <span class="text-left">Paid</span><span>:</span><span><?= number_format($total_paid, 2) ?></span>
                </div>
                <div class="grid grid-cols-[100px_10px_1fr] text-right font-bold">
                    <span class="text-left">Balance</span><span>:</span><span class="<?= $change > 0 ? 'text-green-600' : 'text-gray-900' ?>"><?= number_format($change, 2) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="border-t border-dashed border-gray-400 my-4"></div>

            <div class="text-center text-sm font-semibold text-gray-900 flex items-center justify-center gap-1">
                <span>Thank You!</span>
                <span class="text-amber-500 font-normal">😊</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 w-[280px] no-print">
            <button type="button" onclick="window.print()"
                class="bg-[#1d4ed8] text-white text-sm font-semibold py-2.5 px-4 rounded-md shadow-sm flex items-center justify-center gap-2 hover:opacity-95 active:scale-[0.98] transition-all">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print
            </button>
            <a href="../pos/index.php"
                class="bg-[#e5e7eb] border border-gray-300/70 text-gray-900 text-sm font-semibold py-2.5 px-4 rounded-md shadow-sm text-center hover:bg-gray-200 active:scale-[0.98] transition-all block">
                New Sale
            </a>
        </div>
    </div>
</body>
</html>