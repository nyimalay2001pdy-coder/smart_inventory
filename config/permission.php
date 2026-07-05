<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkPermission($module, $action = 'view') {
    if (!isset($_SESSION['role'])) {
        return false;
    }

    $role = $_SESSION['role'];

    $perms = [
        'admin' => [
            'dashboard' => ['view'],
            'products' => ['view', 'add', 'edit', 'delete'],
            'categories' => ['view', 'add', 'edit', 'delete'],
            'suppliers' => ['view', 'add', 'edit', 'delete'],
            'purchases' => ['view', 'add', 'edit', 'delete'],
            'sales' => ['view', 'add', 'delete'],
            'reports' => ['view'],
            'forecast' => ['view'],
            'users' => ['view', 'add', 'edit', 'delete'],
            'pos' => ['view', 'add'],
            'invoices' => ['view'],
        ],
        'staff' => [
            'dashboard' => ['view'],
            'products' => ['view'],
            'sales' => ['view', 'add'],
            'invoices' => ['view'],
            'pos' => ['view', 'add'],
        ],
        'cashier' => [
            'dashboard' => ['view'],
            'pos' => ['view', 'add'],
            'invoices' => ['view'],
            'products' => ['view'],
        ],
    ];

    if (!isset($perms[$role][$module])) {
        return false;
    }

    return in_array($action, $perms[$role][$module]);
}
