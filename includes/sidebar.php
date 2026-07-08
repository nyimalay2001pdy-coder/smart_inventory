<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? 'staff';
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$file = basename($_SERVER['PHP_SELF']);

/**
 * Check if the current page matches a menu item.
 * - isActive('sale')                   → true when current_dir is 'sale'
 * - isActive('purchase', 'add.php')    → true ONLY when dir is 'purchase' AND file is 'add.php'
 */
function isActive($dirs, $check_file = null)
{
    global $current_dir, $file;
    $dirs = (array)$dirs;
    if ($check_file) {
        return in_array($current_dir, $dirs) && $file === $check_file;
    }
    return in_array($current_dir, $dirs);
}
?>
<aside id="sidebar" class="w-64 bg-white dark:bg-slate-800 border-r border-gray-200 dark:border-slate-700 flex flex-col h-screen fixed lg:sticky top-0 z-40 -translate-x-full lg:translate-x-0 sidebar-transition text-gray-400 font-sm">
    <div class="h-16 flex items-center gap-3 px-5 border-b border-gray-100 dark:border-slate-700">
        <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>
        <div>
            <h1 class="text-sm font-bold text-gray-900 dark:text-slate-100 leading-tight">Smart Inventory</h1>
            <p class="text-[10px] text-gray-500 dark:text-slate-400 leading-tight">Management System</p>
        </div>
    </div>

    <nav class="flex-1 py-3 px-3 space-y-0.5 overflow-y-auto">
        <p class="px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2">Menu</p>

        <?php if ($role === 'admin' || $role === 'staff' || $role === 'cashier'): ?>
            <a href="../dashboard/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('dashboard') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Dashboard
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'staff'): ?>
            <?php $inv_open = isActive(['product', 'category', 'categories', 'supplier', 'suppliers']); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup('inventoryGroup')" class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= $inv_open ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Inventory
                    </span>
                    <svg id="inventoryGroupIcon" class="w-4 h-4 transition-transform <?= $inv_open ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div id="inventoryGroup" class="ml-4 mt-0.5 space-y-0.5 <?= $inv_open ? '' : 'hidden' ?>">
                    <a href="../product/index.php"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('product') ? 'hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-700 dark:hover:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= isActive('product') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                        Products
                    </a>
                    <?php if ($role === 'admin'): ?>
                        <a href="../categories/index.php"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive(['category', 'categories']) ? 'hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-700 dark:hover:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= isActive(['category', 'categories']) ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                            Categories
                        </a>
                        <a href="../supplier/index.php"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('supplier') ? 'hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-700 dark:hover:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= isActive('supplier') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                            Suppliers
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <?php $purchase_open = isActive(['purchase', 'stock-in']); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup('purchaseGroup')" class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= $purchase_open ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Purchases
                    </span>
                    <svg id="purchaseGroupIcon" class="w-4 h-4 transition-transform <?= $purchase_open ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div id="purchaseGroup" class="ml-4 mt-0.5 space-y-0.5 <?= $purchase_open ? '' : 'hidden' ?>">
                    <a href="../purchase/index.php"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('purchase', 'index.php') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= isActive('purchase', 'index.php') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                        New Purchase
                    </a>
                    <a href="../purchase/history.php"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('purchase', 'history.php') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= isActive('purchase', 'history.php') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                        History
                    </a>
                    <a href="../purchase/reports.php"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('purchase', 'reports.php') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= isActive('purchase', 'reports.php') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                        Reports
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'staff'): ?>
            <?php $sale_open = isActive('sale'); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup('saleGroup')" class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= $sale_open ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        Sales
                    </span>
                    <svg id="saleGroupIcon" class="w-4 h-4 transition-transform <?= $sale_open ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div id="saleGroup" class="ml-4 mt-0.5 space-y-0.5 <?= $sale_open ? '' : 'hidden' ?>">
                    <a href="../sale/pos.php"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('sale', 'pos.php') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= isActive('sale', 'pos.php') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                        New Sale
                    </a>
                    <a href="../sale/history.php"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('sale', 'history.php') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= isActive('sale', 'history.php') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                        History
                    </a>
                    <a href="../sale/reports.php"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('sale', 'reports.php') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-medium' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= isActive('sale', 'reports.php') ? 'bg-indigo-500' : 'bg-gray-400 dark:bg-slate-500' ?>"></span>
                        Reports
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'cashier'): ?>
            <a href="../sale/pos.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('sale') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                </svg>
                POS Sale
            </a>
        <?php endif; ?>

        <p class="px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mt-4 mb-2">Analytics</p>

        <?php if ($role === 'admin'): ?>
            <a href="../reports/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('reports') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Reports
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="../forecast/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('forecast') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Forecast
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <p class="px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mt-4 mb-2">Administration</p>

            <a href="../users/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive(['user', 'users']) ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
                Users
            </a>

            <a href="../settings/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('settings') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-6 h-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Profile
            </a>
        <?php endif; ?>
    </nav>

    <div class="p-3 border-t border-gray-100 dark:border-slate-700">
        <a href="../logout.php"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Logout
        </a>
    </div>
</aside>

<script>
    function toggleGroup(id) {
        const group = document.getElementById(id);
        const icon = document.getElementById(id + 'Icon');
        if (group) {
            group.classList.toggle('hidden');
            if (icon) icon.classList.toggle('rotate-90');
        }
    }
</script>