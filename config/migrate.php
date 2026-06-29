<?php
require_once 'db.php';

echo "<h2>Running Migration...</h2>";

$queries = [
    "ALTER TABLE products ADD COLUMN sku VARCHAR(50) AFTER product_name",
    "ALTER TABLE products ADD COLUMN purchase_price DECIMAL(10,2) AFTER sku",
    "ALTER TABLE products CHANGE price selling_price DECIMAL(10,2)",
    "ALTER TABLE products ADD COLUMN status ENUM('Active','Inactive','Hidden') DEFAULT 'Active' AFTER reorder_level",
];

foreach ($queries as $q) {
    echo "<p>Running: <code>$q</code> ... ";
    if ($conn->query($q)) {
        echo "<span style='color:green'>✅ OK</span></p>";
    } else {
        echo "<span style='color:orange'>⚠ " . $conn->error . "</span></p>";
    }
}

echo "<br><h3 style='color:green'>✅ Migration Complete!</h3>";
echo "<p><a href='../product/index.php'>← Back to Products</a></p>";
?>
