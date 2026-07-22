<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include __DIR__ . '/theme-init.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="flex-1 flex flex-col min-w-0">
            <?php include __DIR__ . '/header.php'; ?>
            <main class="flex-1 flex items-center justify-center p-4 lg:p-6">
                <div class="text-center max-w-md">
                    <div class="w-20 h-20 bg-red-100 dark:bg-red-500/10 rounded-2xl flex items-center justify-center mx-auto mb-6 ring-1 ring-red-200 dark:ring-red-500/20">
                        <svg class="w-10 h-10 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 mb-2">Access Denied</h1>
                    <p class="text-gray-600 dark:text-slate-400 mb-6">You don't have permission to access this page. Please contact your administrator if you believe this is an error.</p>
                    <a href="../dashboard/index.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors duration-200 shadow-sm hover:shadow">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
</body>

</html>
<?php exit; ?>
