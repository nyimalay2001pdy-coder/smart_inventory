<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function isCashier() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'cashier';
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: ../dashboard/index.php");
        exit;
    }
}

function requireStaff() {
    if (!isStaff() && !isAdmin()) {
        header("Location: ../dashboard/index.php");
        exit;
    }
}

function requireCashier() {
    if (!isCashier() && !isAdmin()) {
        header("Location: ../dashboard/index.php");
        exit;
    }
}

function hasPermission($module, $action = 'view') {
    $permissions = [
        'admin' => [
            'dashboard' => ['view'],
            'products' => ['view', 'add', 'edit', 'delete'],
            'categories' => ['view', 'add', 'edit', 'delete'],
            'suppliers' => ['view', 'add', 'edit', 'delete'],
            'purchases' => ['view', 'add', 'edit', 'delete'],
            'sales' => [],
            'reports' => ['view'],
            'forecast' => ['view'],
            'users' => ['view', 'add', 'edit', 'delete'],
        ],
        'staff' => [
            'dashboard' => ['view'],
            'products' => ['view'],
            'sales' => ['view', 'add'],
            'invoices' => ['view'],
        ],
        'cashier' => [
            'sales' => ['view', 'add'],
            'invoices' => ['view'],
            'products' => ['view'],
        ],
    ];

    $role = $_SESSION['role'] ?? 'staff';
    if (!isset($permissions[$role][$module])) {
        return false;
    }
    return in_array($action, $permissions[$role][$module]);
}
