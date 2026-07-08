<?php
require_once 'config/database.php';

echo "<h2 style='color:#4f46e5;'>Theme Preference Migration</h2>";

// Check if column exists
$check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'theme'");
if (mysqli_num_rows($check) > 0) {
    // Check if it needs to be updated to include 'system'
    $col = mysqli_fetch_assoc($check);
    if (strpos($col['Type'], 'system') === false) {
        $result = mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN theme ENUM('light', 'dark', 'system') DEFAULT 'system'");
        if ($result) {
            echo "<p style='color:green;'>✅ Theme column updated to include 'system' option!</p>";
        } else {
            echo "<p style='color:red;'>❌ Error: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p style='color:green;'>✅ Theme column already exists with system option.</p>";
    }
} else {
    $result = mysqli_query($conn, "ALTER TABLE users ADD COLUMN theme ENUM('light', 'dark', 'system') DEFAULT 'system' AFTER status");
    if ($result) {
        echo "<p style='color:green;'>✅ Theme column added to users table successfully!</p>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . mysqli_error($conn) . "</p>";
    }
}

echo "<p><a href='login.php' style='color:#4f46e5;'>→ Go to Login</a></p>";
$conn->close();
