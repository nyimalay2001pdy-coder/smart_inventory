<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? 'staff';
$name = $_SESSION['name'] ?? 'User';
$page_title = $page_title ?? 'Dashboard';

$breadcrumb_parts = ['Dashboard'];
if ($page_title !== 'Dashboard') {
    $breadcrumb_parts[] = $page_title;
}

$notif_count = 0;
$low_stock_products = [];
if (isset($conn)) {
    $notif_result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM products WHERE quantity <= minimum_stock AND status='Active'");
    if ($notif_result) {
        $notif_count = (int)mysqli_fetch_assoc($notif_result)['count'];
    }
    if ($notif_count > 0) {
        $ls_result = mysqli_query($conn, "SELECT id, product_name, quantity, minimum_stock FROM products WHERE quantity <= minimum_stock AND status='Active' ORDER BY quantity ASC LIMIT 10");
        while ($row = mysqli_fetch_assoc($ls_result)) {
            $low_stock_products[] = $row;
        }
    }
}
?>
<header class="sticky top-0 z-30 bg-white border-b border-gray-200">
    <div class="px-4 lg:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 -ml-2 text-gray-500 hover:text-indigo-600 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div>
                <h1 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($page_title) ?></h1>
                <nav class="flex items-center gap-1 text-xs text-gray-500 mt-0.5">
                    <?php foreach ($breadcrumb_parts as $i => $part): ?>
                        <?php if ($i > 0): ?>
                            <svg class="w-3 h-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        <?php endif; ?>
                        <?php if ($i === 0): ?>
                            <a href="../dashboard/index.php" class="hover:text-indigo-600 transition-colors"><?= htmlspecialchars($part) ?></a>
                        <?php else: ?>
                            <span class="<?= $i === count($breadcrumb_parts) - 1 ? 'text-gray-700 font-medium' : '' ?>"><?= htmlspecialchars($part) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <div class="relative">
                <button onclick="toggleDropdown('notifDropdown')" class="relative p-2 text-gray-500 hover:text-indigo-600 rounded-lg hover:bg-gray-100 transition" title="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <?php if ($notif_count > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold w-4.5 h-4.5 flex items-center justify-center rounded-full" style="min-width:18px;height:18px"><?= $notif_count ?></span>
                    <?php endif; ?>
                </button>
                <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800">Notifications</p>
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <?php if (count($low_stock_products) > 0): ?>
                            <?php foreach ($low_stock_products as $item): ?>
                                <a href="../product/index.php" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-50">
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($item['product_name']) ?></p>
                                    <p class="text-xs text-red-500 mt-0.5">Low stock: <?= $item['quantity'] ?> (min: <?= $item['minimum_stock'] ?>)</p>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="px-4 py-6 text-center text-sm text-gray-500">No notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="relative">
                <button onclick="toggleDropdown('userDropdown')" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 transition">
                    <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
                        <?= strtoupper(substr($name, 0, 1)) ?>
                    </div>
                    <div class="text-left hidden sm:block">
                        <p class="text-sm font-medium text-gray-800 leading-tight"><?= htmlspecialchars($name) ?></p>
                        <p class="text-[11px] text-gray-500 capitalize"><?= $role ?></p>
                    </div>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="userDropdown" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($name) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></p>
                    </div>
                    <a href="../profile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        My Profile
                    </a>
                    <a href="../change_password.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Change Password
                    </a>
                    <div class="border-t border-gray-100"></div>
                    <a href="../logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>