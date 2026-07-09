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
<header class="sticky top-0 z-30 bg-white/80 dark:bg-slate-800/80 backdrop-blur-lg border-b border-gray-200/60 dark:border-slate-700/60 shadow-sm">
    <div class="px-4 lg:px-6 h-16 flex items-center justify-between">
        <!-- Left: Mobile Menu + Title & Breadcrumbs -->
        <div class="flex items-center gap-4">
            <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 -ml-2 text-gray-500 dark:text-slate-400 hover:text-indigo-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-slate-100 leading-tight"><?= htmlspecialchars($page_title) ?></h1>
                <nav class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                    <?php foreach ($breadcrumb_parts as $i => $part): ?>
                        <?php if ($i > 0): ?>
                            <svg class="w-3 h-3 text-gray-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        <?php endif; ?>
                        <?php if ($i === 0): ?>
                            <a href="../dashboard/index.php" class="hover:text-indigo-600 transition-colors duration-200"><?= htmlspecialchars($part) ?></a>
                        <?php else: ?>
                            <span class="<?= $i === count($breadcrumb_parts) - 1 ? 'text-gray-700 dark:text-slate-200 font-medium' : '' ?>"><?= htmlspecialchars($part) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-3">
            <!-- Theme Toggle -->
            <button onclick="toggleTheme()" id="themeToggleBtn" class="header-btn relative flex items-center justify-center w-10 h-10 text-gray-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-xl border border-gray-200 dark:border-slate-600 hover:border-indigo-300 dark:hover:border-indigo-500 bg-white dark:bg-slate-700/50 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all duration-200 shadow-sm hover:shadow" title="Toggle Theme">
                <!-- Sun icon (light mode) -->
                <svg id="iconLight" class="w-[18px] h-[18px] transition-all duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <!-- Moon icon (dark mode) -->
                <svg id="iconDark" class="w-[18px] h-[18px] transition-all duration-300 absolute inset-0 m-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
                <!-- Monitor icon (system mode) -->
                <svg id="iconSystem" class="w-[18px] h-[18px] transition-all duration-300 absolute inset-0 m-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </button>

            <!-- Notifications -->
            <div class="relative">
                <button onclick="toggleDropdown('notifDropdown')" class="header-btn relative flex items-center justify-center w-10 h-10 text-gray-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-xl border border-gray-200 dark:border-slate-600 hover:border-indigo-300 dark:hover:border-indigo-500 bg-white dark:bg-slate-700/50 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all duration-200 shadow-sm hover:shadow" title="Notifications">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <?php if ($notif_count > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full shadow-sm ring-2 ring-white dark:ring-slate-800" style="min-width:18px;height:18px;padding:0 4px"><?= $notif_count ?></span>
                    <?php endif; ?>
                </button>
                <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 z-50 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-700/30">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-800 dark:text-slate-200">Notifications</p>
                            <?php if ($notif_count > 0): ?>
                                <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-0.5 rounded-full"><?= $notif_count ?> new</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        <?php if (count($low_stock_products) > 0): ?>
                            <?php foreach ($low_stock_products as $item): ?>
                                <a href="../product/index.php" class="block px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-slate-700/50 border-b border-gray-50 dark:border-slate-700/50 transition-colors duration-150">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-500/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-800 dark:text-slate-200 truncate"><?= htmlspecialchars($item['product_name']) ?></p>
                                            <p class="text-xs text-red-500 dark:text-red-400 mt-0.5">Stock: <?= $item['quantity'] ?> (min: <?= $item['minimum_stock'] ?>)</p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="px-4 py-8 text-center">
                                <svg class="w-10 h-10 text-gray-300 dark:text-slate-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-slate-400">No notifications</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">All caught up!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div class="w-px h-8 bg-gray-200 dark:bg-slate-600/50"></div>

            <!-- User Profile -->
            <div class="relative">
                <button onclick="toggleDropdown('userDropdown')" class="header-btn flex items-center gap-2.5 pl-1.5 pr-3 py-1.5 rounded-xl border border-gray-200 dark:border-slate-600 hover:border-indigo-300 dark:hover:border-indigo-500 bg-white dark:bg-slate-700/50 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all duration-200 shadow-sm hover:shadow">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-xs shadow-sm">
                        <?= strtoupper(substr($name, 0, 1)) ?>
                    </div>
                    <div class="text-left hidden md:block">
                        <p class="text-sm font-semibold text-gray-800 dark:text-slate-200 leading-tight"><?= htmlspecialchars($name) ?></p>
                        <p class="text-[11px] text-gray-500 dark:text-slate-400 capitalize leading-tight"><?= $role ?></p>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 dark:text-slate-500 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="userDropdown" class="hidden absolute right-0 mt-2 w-60 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 py-1.5 z-50 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-700/30">
                        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100"><?= htmlspecialchars($name) ?></p>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></p>
                    </div>
                    <a href="../profile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                        <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        My Profile
                    </a>
                    <a href="../change_password.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                        <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Change Password
                    </a>
                    <div class="my-1.5 border-t border-gray-100 dark:border-slate-700"></div>
                    <a href="../logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors duration-150">
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