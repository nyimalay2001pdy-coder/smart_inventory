<?php
/**
 * Migration: Add advance payment support to suppliers and purchase_payments
 */

include "database.php";

echo "Running advance payments migration...\n";

// 1. Add advance_balance column to suppliers
$check = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'advance_balance'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE suppliers ADD COLUMN advance_balance DECIMAL(10,2) DEFAULT 0.00 AFTER current_balance");
    echo "✓ Added advance_balance column to suppliers\n";
} else {
    echo "- advance_balance column already exists\n";
}

// 2. Add advance_applied column to purchase_payments
$check2 = mysqli_query($conn, "SHOW COLUMNS FROM purchase_payments LIKE 'advance_applied'");
if (mysqli_num_rows($check2) == 0) {
    mysqli_query($conn, "ALTER TABLE purchase_payments ADD COLUMN advance_applied DECIMAL(10,2) DEFAULT 0.00 AFTER paid_amount");
    echo "✓ Added advance_applied column to purchase_payments\n";
} else {
    echo "- advance_applied column already exists\n";
}

// 3. Add advance_created column to purchase_payments
$check3 = mysqli_query($conn, "SHOW COLUMNS FROM purchase_payments LIKE 'advance_created'");
if (mysqli_num_rows($check3) == 0) {
    mysqli_query($conn, "ALTER TABLE purchase_payments ADD COLUMN advance_created DECIMAL(10,2) DEFAULT 0.00 AFTER advance_applied");
    echo "✓ Added advance_created column to purchase_payments\n";
} else {
    echo "- advance_created column already exists\n";
}

echo "\nMigration complete.\n";
echo "You can now delete this file if desired.\n";
