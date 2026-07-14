<?php
include "config/database.php";

echo "<h2>Purchase Payments Migration</h2>";

// 1. Create purchase_payments table
$sql1 = "CREATE TABLE IF NOT EXISTS purchase_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    payment_method ENUM('Cash','KBZPay') NOT NULL DEFAULT 'Cash',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if (mysqli_query($conn, $sql1)) {
    echo "<p style='color:green'>✓ purchase_payments table created</p>";
} else {
    echo "<p style='color:red'>✗ Error: " . mysqli_error($conn) . "</p>";
}

// 2. Add payment_status to purchases if not exists
$check = mysqli_query($conn, "SHOW COLUMNS FROM purchases LIKE 'payment_status'");
if (mysqli_num_rows($check) == 0) {
    $sql2 = "ALTER TABLE purchases ADD COLUMN payment_status ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid'";
    if (mysqli_query($conn, $sql2)) {
        echo "<p style='color:green'>✓ payment_status column added to purchases</p>";
    } else {
        echo "<p style='color:red'>✗ Error: " . mysqli_error($conn) . "</p>";
    }
} else {
    // Update ENUM to include Partial
    $sql2 = "ALTER TABLE purchases MODIFY COLUMN payment_status ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid'";
    if (mysqli_query($conn, $sql2)) {
        echo "<p style='color:green'>✓ payment_status ENUM updated</p>";
    } else {
        echo "<p style='color:red'>✗ Error: " . mysqli_error($conn) . "</p>";
    }
}

echo "<br><h3>Migration Complete!</h3>";
echo "<p><a href='purchase/add.php'>Go to Purchase</a></p>";
?>
