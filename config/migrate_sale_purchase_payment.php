<?php
/**
 * Migration: Align sales/purchases tables and sale_payments/purchase_payments tables
 * 
 * This migration ensures:
 * 1. sale_payments table has the correct columns (paid_amount, cash_amount, kbzpay_amount, change_amount, payment_status)
 * 2. purchase_payments table has the correct columns (paid_amount, cash_amount, kbzpay_amount, remaining_balance, payment_status)
 * 3. sales table does NOT have payment columns (payment_method, paid_amount, cash_amount, kbzpay_amount, change_amount)
 * 4. purchases table does NOT have payment columns (payment_method, paid_amount, cash_amount, kbzpay_amount, remaining_balance)
 */

include "database.php";

echo "<h2>Sale & Purchase Payment Migration</h2>";
echo "<pre>";

function runMigration($conn, $sql, $description) {
    if (mysqli_query($conn, $sql)) {
        echo "✓ $description\n";
        return true;
    } else {
        $error = mysqli_error($conn);
        if (strpos($error, 'Duplicate column') !== false) {
            echo "✓ $description (already exists)\n";
            return true;
        }
        echo "✗ $description - Error: $error\n";
        return false;
    }
}

function columnExists($conn, $table, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($result) > 0;
}

// ============================================================
// 1. Fix sale_payments table
// ============================================================
echo "\n=== Sale Payments Table ===\n";

// If 'amount' column exists, rename it to 'paid_amount'
if (columnExists($conn, 'sale_payments', 'amount')) {
    runMigration($conn, "ALTER TABLE sale_payments CHANGE COLUMN amount paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00", "Renamed amount to paid_amount in sale_payments");
}

// Add missing columns
if (!columnExists($conn, 'sale_payments', 'paid_amount')) {
    runMigration($conn, "ALTER TABLE sale_payments ADD COLUMN paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER kbzpay_amount", "Added paid_amount to sale_payments");
}
if (!columnExists($conn, 'sale_payments', 'cash_amount')) {
    runMigration($conn, "ALTER TABLE sale_payments ADD COLUMN cash_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_method", "Added cash_amount to sale_payments");
}
if (!columnExists($conn, 'sale_payments', 'kbzpay_amount')) {
    runMigration($conn, "ALTER TABLE sale_payments ADD COLUMN kbzpay_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cash_amount", "Added kbzpay_amount to sale_payments");
}
if (!columnExists($conn, 'sale_payments', 'change_amount')) {
    runMigration($conn, "ALTER TABLE sale_payments ADD COLUMN change_amount DECIMAL(10,2) DEFAULT 0.00 AFTER paid_amount", "Added change_amount to sale_payments");
}
if (!columnExists($conn, 'sale_payments', 'payment_status')) {
    runMigration($conn, "ALTER TABLE sale_payments ADD COLUMN payment_status ENUM('Paid','Partial','Unpaid') DEFAULT 'Paid' AFTER change_amount", "Added payment_status to sale_payments");
}
if (!columnExists($conn, 'sale_payments', 'payment_date')) {
    runMigration($conn, "ALTER TABLE sale_payments ADD COLUMN payment_date DATETIME DEFAULT CURRENT_TIMESTAMP AFTER payment_status", "Added payment_date to sale_payments");
}

// Update payment_method ENUM to include Mixed if needed
runMigration($conn, "ALTER TABLE sale_payments MODIFY COLUMN payment_method ENUM('Cash','KBZPay','Mixed') NOT NULL", "Updated payment_method ENUM in sale_payments");

// ============================================================
// 2. Fix purchase_payments table
// ============================================================
echo "\n=== Purchase Payments Table ===\n";

// If 'amount' column exists, rename it to 'paid_amount'
if (columnExists($conn, 'purchase_payments', 'amount')) {
    runMigration($conn, "ALTER TABLE purchase_payments CHANGE COLUMN amount paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00", "Renamed amount to paid_amount in purchase_payments");
}

// Add missing columns
if (!columnExists($conn, 'purchase_payments', 'paid_amount')) {
    runMigration($conn, "ALTER TABLE purchase_payments ADD COLUMN paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER kbzpay_amount", "Added paid_amount to purchase_payments");
}
if (!columnExists($conn, 'purchase_payments', 'cash_amount')) {
    runMigration($conn, "ALTER TABLE purchase_payments ADD COLUMN cash_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_method", "Added cash_amount to purchase_payments");
}
if (!columnExists($conn, 'purchase_payments', 'kbzpay_amount')) {
    runMigration($conn, "ALTER TABLE purchase_payments ADD COLUMN kbzpay_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cash_amount", "Added kbzpay_amount to purchase_payments");
}
if (!columnExists($conn, 'purchase_payments', 'remaining_balance')) {
    runMigration($conn, "ALTER TABLE purchase_payments ADD COLUMN remaining_balance DECIMAL(10,2) DEFAULT 0.00 AFTER paid_amount", "Added remaining_balance to purchase_payments");
}
if (!columnExists($conn, 'purchase_payments', 'payment_status')) {
    runMigration($conn, "ALTER TABLE purchase_payments ADD COLUMN payment_status ENUM('Paid','Partial','Unpaid') DEFAULT 'Paid' AFTER remaining_balance", "Added payment_status to purchase_payments");
}
if (!columnExists($conn, 'purchase_payments', 'payment_date')) {
    runMigration($conn, "ALTER TABLE purchase_payments ADD COLUMN payment_date DATETIME DEFAULT CURRENT_TIMESTAMP AFTER payment_status", "Added payment_date to purchase_payments");
}

// Update payment_method ENUM to include Mixed if needed
runMigration($conn, "ALTER TABLE purchase_payments MODIFY COLUMN payment_method ENUM('Cash','KBZPay','Mixed') NOT NULL", "Updated payment_method ENUM in purchase_payments");

// ============================================================
// 3. Remove payment columns from sales table
// ============================================================
echo "\n=== Sales Table - Remove Payment Columns ===\n";

$salesDropColumns = ['payment_method', 'paid_amount', 'cash_amount', 'kbzpay_amount', 'change_amount'];
foreach ($salesDropColumns as $col) {
    if (columnExists($conn, 'sales', $col)) {
        runMigration($conn, "ALTER TABLE sales DROP COLUMN `$col`", "Dropped $col from sales");
    } else {
        echo "✓ $col not in sales table (already clean)\n";
    }
}

// ============================================================
// 4. Remove payment columns from purchases table
// ============================================================
echo "\n=== Purchases Table - Remove Payment Columns ===\n";

$purchasesDropColumns = ['payment_method', 'paid_amount', 'cash_amount', 'kbzpay_amount', 'remaining_balance', 'payment_status'];
foreach ($purchasesDropColumns as $col) {
    if (columnExists($conn, 'purchases', $col)) {
        runMigration($conn, "ALTER TABLE purchases DROP COLUMN `$col`", "Dropped $col from purchases");
    } else {
        echo "✓ $col not in purchases table (already clean)\n";
    }
}

// Ensure status column exists in purchases
if (!columnExists($conn, 'purchases', 'status')) {
    runMigration($conn, "ALTER TABLE purchases ADD COLUMN status ENUM('completed','pending','cancelled') DEFAULT 'completed' AFTER total_amount", "Added status column to purchases");
}

// ============================================================
// 5. Migrate existing data: copy payment info from sales to sale_payments
// ============================================================
echo "\n=== Migrate Existing Sale Data ===\n";

// Check if sales table still has payment_method (data migration)
if (columnExists($conn, 'sales', 'payment_method')) {
    $orphanSales = mysqli_query($conn, "
        SELECT s.id, s.total_amount, s.payment_method, s.paid_amount, s.cash_amount, s.kbzpay_amount, s.change_amount
        FROM sales s
        LEFT JOIN sale_payments sp ON s.id = sp.sale_id
        WHERE sp.id IS NULL AND s.payment_method IS NOT NULL
    ");
    $migrated = 0;
    while ($row = mysqli_fetch_assoc($orphanSales)) {
        $payment_method = $row['payment_method'] ?: 'Cash';
        $paid_amount = $row['paid_amount'] ?? $row['total_amount'] ?? 0;
        $cash_amount = $row['cash_amount'] ?? 0;
        $kbzpay_amount = $row['kbzpay_amount'] ?? 0;
        $change_amount = $row['change_amount'] ?? 0;
        $grand_total = (float)$row['total_amount'];
        $payment_status = ($paid_amount >= $grand_total) ? 'Paid' : (($paid_amount > 0) ? 'Partial' : 'Unpaid');
        
        mysqli_query($conn, "INSERT INTO sale_payments (sale_id, payment_method, cash_amount, kbzpay_amount, paid_amount, change_amount, payment_status, payment_date)
            VALUES ({$row['id']}, '$payment_method', $cash_amount, $kbzpay_amount, $paid_amount, $change_amount, '$payment_status', NOW())");
        $migrated++;
    }
    echo "✓ Migrated $migrated sale payment records to sale_payments table\n";
} else {
    echo "✓ No legacy payment data in sales table to migrate\n";
}

// ============================================================
// 6. Migrate existing data: copy payment info from purchases to purchase_payments
// ============================================================
echo "\n=== Migrate Existing Purchase Data ===\n";

if (columnExists($conn, 'purchases', 'payment_method')) {
    $orphanPurchases = mysqli_query($conn, "
        SELECT p.id, p.total_amount, p.payment_method, p.paid_amount, p.cash_amount, p.kbzpay_amount, p.remaining_balance
        FROM purchases p
        LEFT JOIN purchase_payments pp ON p.id = pp.purchase_id
        WHERE pp.id IS NULL AND p.payment_method IS NOT NULL
    ");
    $migrated = 0;
    while ($row = mysqli_fetch_assoc($orphanPurchases)) {
        $payment_method = $row['payment_method'] ?: 'Cash';
        $paid_amount = $row['paid_amount'] ?? 0;
        $cash_amount = $row['cash_amount'] ?? 0;
        $kbzpay_amount = $row['kbzpay_amount'] ?? 0;
        $remaining_balance = $row['remaining_balance'] ?? 0;
        $grand_total = (float)$row['total_amount'];
        $payment_status = ($paid_amount >= $grand_total) ? 'Paid' : (($paid_amount > 0) ? 'Partial' : 'Unpaid');
        
        mysqli_query($conn, "INSERT INTO purchase_payments (purchase_id, payment_method, cash_amount, kbzpay_amount, paid_amount, remaining_balance, payment_status, payment_date)
            VALUES ({$row['id']}, '$payment_method', $cash_amount, $kbzpay_amount, $paid_amount, $remaining_balance, '$payment_status', NOW())");
        $migrated++;
    }
    echo "✓ Migrated $migrated purchase payment records to purchase_payments table\n";
} else {
    echo "✓ No legacy payment data in purchases table to migrate\n";
}

echo "\n=== Migration Complete! ===\n";
echo "<p><a href='../sale/pos.php'>Go to POS</a> | <a href='../purchase/index.php'>Go to Purchases</a></p>";
echo "</pre>";
?>
