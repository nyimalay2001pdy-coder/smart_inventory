<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? 'staff';
$name = $_SESSION['name'] ?? 'User';
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$file = basename($_SERVER['PHP_SELF']);

function isActive($dirs, $check_file = null)
{
    global $current_dir, $file;
    $dirs = (array)$dirs;
    if ($check_file) {
        return in_array($current_dir, $dirs) && $file === $check_file;
    }
    return in_array($current_dir, $dirs);
}

function isGroupActive($dirs)
{
    return isActive($dirs);
}

function menuItem($href, $label, $dirs, $file_check = null, $icon = '')
{
    $active = isActive($dirs, $file_check);
    $activeClass = $active
        ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold'
        : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200';
    $dot = $active
        ? '<span class="w-1.5 h-1.5 rounded-full bg-indigo-500 flex-shrink-0"></span>'
        : '<span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-slate-600 flex-shrink-0"></span>';
    return <<<HTML
<a href="{$href}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] transition-all duration-150 {$activeClass}">
    {$dot}
    {$label}
</a>
HTML;
}

?>
<aside id="sidebar" class="w-64 bg-white dark:bg-slate-800 border-r border-gray-200 dark:border-slate-700 flex flex-col h-screen fixed lg:sticky top-0 z-40 -translate-x-full lg:translate-x-0 sidebar-transition">

    <!-- Logo -->
    <div class="h-16 flex items-center gap-3 px-5 border-b border-gray-100 dark:border-slate-700 flex-shrink-0">
        <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 shadow-sm">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>
        <div class="min-w-0">
            <h1 class="text-sm font-bold text-gray-900 dark:text-slate-100 leading-tight truncate">Smart Inventory</h1>
            <p class="text-[10px] text-gray-500 dark:text-slate-400 leading-tight capitalize"><?= $role ?></p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 py-3 px-3 space-y-1 overflow-y-auto">

        <!-- ─── Dashboard ─── -->
        <a href="../dashboard/index.php"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('dashboard') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 shadow-sm shadow-indigo-100 dark:shadow-indigo-500/5' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
        </a>

        <!-- ═══════════════ ADMIN SIDEBAR ═══════════════ -->
        <?php if ($role === 'admin'): ?>

            <!-- Inventory Group -->
            <?php $inv_active = isGroupActive(['product', 'category', 'categories', 'supplier', 'suppliers']); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup(this)" data-group="grp-inventory" class="sidebar-group-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $inv_active ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Inventory
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 <?= $inv_active ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div class="sidebar-group-items <?= $inv_active ? '' : 'collapsed' ?>">
                    <div class="ml-4 mt-1 space-y-0.5 border-l border-gray-200 dark:border-slate-600 pl-3">
                        <?= menuItem('../categories/index.php', 'Categories', ['category', 'categories']) ?>
                        <?= menuItem('../units/index.php', 'Units', 'units') ?>
                        <?= menuItem('../product/index.php', 'Products', 'product') ?>
                        <?= menuItem('../supplier/index.php', 'Suppliers', 'supplier') ?>
                    </div>
                </div>
            </div>

            <!-- Purchases Group -->
            <?php $purch_active = isGroupActive(['purchase', 'stock-in']); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup(this)" data-group="grp-purchase" class="sidebar-group-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $purch_active ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Purchase
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 <?= $purch_active ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div class="sidebar-group-items <?= $purch_active ? '' : 'collapsed' ?>">
                    <div class="ml-4 mt-1 space-y-0.5 border-l border-gray-200 dark:border-slate-600 pl-3">
                        <?= menuItem('../purchase/index.php', 'New Purchase', 'purchase', 'index.php') ?>
                        <?= menuItem('../purchase/history.php', 'History', 'purchase', 'history.php') ?>
                        <?= menuItem('../purchase/reports.php', 'Reports', 'purchase', 'reports.php') ?>
                    </div>
                </div>
            </div>

            <!-- Sales Group -->
            <?php $sale_active = isGroupActive('sale'); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup(this)" data-group="grp-sale" class="sidebar-group-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $sale_active ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        Sale
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 <?= $sale_active ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div class="sidebar-group-items <?= $sale_active ? '' : 'collapsed' ?>">
                    <div class="ml-4 mt-1 space-y-0.5 border-l border-gray-200 dark:border-slate-600 pl-3">
                        <?= menuItem('../sale/pos.php', 'New Sale', 'sale', 'pos.php') ?>
                        <?= menuItem('../sale/history.php', 'History', 'sale', 'history.php') ?>
                        <?= menuItem('../sale/reports.php', 'Reports', 'sale', 'reports.php') ?>
                    </div>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="pt-3 mt-1 border-t border-gray-100 dark:border-slate-700">
                <p class="px-3 mb-1.5 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Analytics</p>
            </div>

            <a href="../reports/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('reports') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Report
            </a>

            <a href="../forecast/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('forecast') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Forecast
            </a>

            <!-- Administration Section -->
            <div class="pt-3 mt-1 border-t border-gray-100 dark:border-slate-700">
                <p class="px-3 mb-1.5 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Administration</p>
            </div>

            <a href="../users/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive(['user', 'users']) ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                User
            </a>

            <a href="../settings/index.php"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= isActive('settings') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Setting
            </a>

            <!-- ═══════════════ STAFF SIDEBAR ═══════════════ -->
        <?php elseif ($role === 'staff'): ?>

            <!-- Inventory Group -->
            <?php $inv_active = isGroupActive(['product']); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup(this)" data-group="grp-inventory" class="sidebar-group-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $inv_active ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Inventory
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 <?= $inv_active ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div class="sidebar-group-items <?= $inv_active ? '' : 'collapsed' ?>">
                    <div class="ml-4 mt-1 space-y-0.5 border-l border-gray-200 dark:border-slate-600 pl-3">
                        <?= menuItem('../product/index.php', 'Products', 'product') ?>
                    </div>
                </div>
            </div>

            <!-- Sales Group -->
            <?php $sale_active = isGroupActive('sale'); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup(this)" data-group="grp-sale" class="sidebar-group-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $sale_active ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        Sales
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 <?= $sale_active ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div class="sidebar-group-items <?= $sale_active ? '' : 'collapsed' ?>">
                    <div class="ml-4 mt-1 space-y-0.5 border-l border-gray-200 dark:border-slate-600 pl-3">
                        <?= menuItem('../sale/pos.php', 'New Sale', 'sale', 'pos.php') ?>
                        <?= menuItem('../sale/history.php', 'History', 'sale', 'history.php') ?>
                        <?= menuItem('../sale/reports.php', 'Reports', 'sale', 'reports.php') ?>
                    </div>
                </div>
            </div>

            <!-- ═══════════════ CASHIER SIDEBAR ═══════════════ -->
        <?php elseif ($role === 'cashier'): ?>

            <!-- Sales Group -->
            <?php $sale_active = isGroupActive('sale'); ?>
            <div class="sidebar-group">
                <button onclick="toggleGroup(this)" data-group="grp-sale" class="sidebar-group-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $sale_active ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-slate-200' ?>">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        Sales
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 <?= $sale_active ? 'rotate-90' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div class="sidebar-group-items <?= $sale_active ? '' : 'collapsed' ?>">
                    <div class="ml-4 mt-1 space-y-0.5 border-l border-gray-200 dark:border-slate-600 pl-3">
                        <?= menuItem('../sale/pos.php', 'New Sale', 'sale', 'pos.php') ?>
                        <?= menuItem('../sale/history.php', 'History', 'sale', 'history.php') ?>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </nav>

    <!-- Bottom: Logout -->
    <div class="flex-shrink-0 border-t border-gray-100 dark:border-slate-700 p-3 space-y-0.5">

        <a href="../logout.php"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all duration-150">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Logout
        </a>
    </div>
</aside>

<script>
    (function() {
        /* ── Smooth collapse / expand ── */
        window.toggleGroup = function(btn) {
            var id = btn.getAttribute('data-group');
            var container = btn.nextElementSibling;
            var arrow = btn.querySelector('svg:last-child');
            if (!container) return;

            var isOpen = !container.classList.contains('collapsed');

            if (isOpen) {
                /* Close: measure, then animate to 0 */
                container.style.maxHeight = container.scrollHeight + 'px';
                requestAnimationFrame(function() {
                    container.style.maxHeight = '0px';
                    container.style.opacity = '0';
                });
                container.classList.add('collapsed');
                if (arrow) arrow.classList.remove('rotate-90');
            } else {
                /* Open: measure natural height, animate in */
                container.classList.remove('collapsed');
                container.style.maxHeight = '0px';
                container.style.opacity = '0';
                var h = container.scrollHeight;
                container.style.maxHeight = h + 'px';
                container.style.opacity = '1';
                if (arrow) arrow.classList.add('rotate-90');
                /* Clean up after transition */
                setTimeout(function() {
                    if (!container.classList.contains('collapsed')) {
                        container.style.maxHeight = 'none';
                    }
                }, 250);
            }
        };

        /* ── On load: open any group that has an active child ── */
        document.querySelectorAll('.sidebar-group-items:not(.collapsed)').forEach(function(el) {
            el.style.maxHeight = 'none';
            el.style.opacity = '1';
        });
    })();
</script>