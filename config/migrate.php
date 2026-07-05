<?php
require_once 'database.php';

echo "<h2 style='color:#4f46e5;'>Smart Inventory - Database Migration</h2>";

$sql = file_get_contents(__DIR__ . '/migration.sql');
$queries = explode(';', $sql);
$count = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) continue;
    if (mysqli_query($conn, $query)) {
        $count++;
    } else {
        echo "<p style='color:orange;'>⚠ " . mysqli_error($conn) . "</p>";
    }
}

echo "<p style='color:green;'>✅ Migration complete! $count queries executed.</p>";
echo "<p><a href='../login.php' style='color:#4f46e5;'>→ Go to Login</a></p>";

// Create default admin user if not exists
$check = mysqli_query($conn, "SELECT id FROM users WHERE username='admin'");
if (mysqli_num_rows($check) == 0) {
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (name, username, email, password, role, status) VALUES ('System Admin', 'admin', 'admin@smartinventory.com', '$password', 'admin', 'Active')");
    mysqli_query($conn, "INSERT INTO users (name, username, email, password, role, status) VALUES ('Staff User', 'staff', 'staff@smartinventory.com', '$password', 'staff', 'Active')");
    mysqli_query($conn, "INSERT INTO users (name, username, email, password, role, status) VALUES ('Cashier User', 'cashier', 'cashier@smartinventory.com', '$password', 'cashier', 'Active')");
    echo "<p style='color:green;'>✅ Default users created (admin/admin123, staff/admin123, cashier/admin123)</p>";
}
?>
