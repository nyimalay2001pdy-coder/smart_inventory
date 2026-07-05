<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? 'staff';
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$file = basename($_SERVER['PHP_SELF']);

function isActive($dirs, $file = null)
{
    global $current_dir, $file;
    $dirs = (array)$dirs;
    if (in_array($current_dir, $dirs)) return true;
    if ($file && in_array($file, (array)$file)) return true;
    return false;
}
?>
<aside id="sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col h-screen fixed lg:sticky top-0 z-40 -translate-x-full lg:translate-x-0 sidebar-transition">
    <div class="h-16 flex items-center gap-3 px-5 border-b border-gray-100">
        <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>
        <div>
            <h1 class="text-sm font-bold text-gray-900 leading-tight">Smart Inventory</h1>
            <p class="text-[10px] text-gray-500 leading-tight">Management System</p>
        </div>
    </div>

    <nav class="flex-1 py-3 px-3 space-y-0.5 overflow-y-auto">
        <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Menu</p>

        <?php if ($role === 'admin' || $role === 'staff' || $role === 'cashier'): ?>
            <a href="../dashboard/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Dashboard
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'staff'): ?>
            <a href="../product/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('product') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                Products
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="../categories/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive(['category', 'categories']) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                Categories
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="../supplier/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('supplier') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Suppliers
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="../purchase/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive(['purchase', 'stock-in']) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                Purchases
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'staff'): ?>
            <a href="../sale/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('sale') && $file !== 'invoice.php' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                </svg>
                Sales
            </a>
        <?php endif; ?>

        <?php if ($role === 'cashier'): ?>
            <a href="../pos/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('pos') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                </svg>
                POS Sale
            </a>
        <?php endif; ?>

        <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mt-4 mb-2">Analytics</p>

        <?php if ($role === 'admin'): ?>
            <a href="../reports/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('reports') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Reports
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="../forecast/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('forecast') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Forecast
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mt-4 mb-2">Administration</p>

            <a href="../users/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive(['user', 'users']) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
                Users
            </a>

            <a href="../settings/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all <?= isActive('settings') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Settings
            </a>
        <?php endif; ?>
    </nav>

    <div class="p-3 border-t border-gray-100">
        <a href="../logout.php"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-all">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Logout
        </a>
    </div>
</aside>