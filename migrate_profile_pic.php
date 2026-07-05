<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/database.php';

$msg = '';
$error = '';

// Check if column already exists
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($check && $check->num_rows > 0) {
    $msg = 'profile_picture column already exists. No changes needed.';
} else {
    $sql = "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER email";
    if ($conn->query($sql)) {
        $msg = 'Successfully added profile_picture column to users table!';
    } else {
        $error = 'Error adding column: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
        <?php if ($msg): ?>
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Migration Complete</h1>
            <p class="text-gray-600"><?= htmlspecialchars($msg) ?></p>
        <?php elseif ($error): ?>
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Migration Failed</h1>
            <p class="text-red-600"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <a href="dashboard/index.php" class="inline-block mt-6 px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Go to Dashboard</a>
    </div>
</body>
</html>
