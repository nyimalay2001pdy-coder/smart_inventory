<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include permission system
require_once __DIR__ . '/../config/permission.php';

// ─── Authentication Check ───
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// ─── Role Check Helpers ───
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function isCashier() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'cashier';
}

// ─── Access Control Functions ───

/**
 * Deny access and redirect to dashboard
 */
function denyAccess() {
    header("Location: ../dashboard/index.php");
    exit;
}

/**
 * Require specific role(s)
 */
function requireRole(...$roles) {
    $currentRole = $_SESSION['role'] ?? '';
    if (!in_array($currentRole, $roles)) {
        denyAccess();
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireRole('admin');
}

/**
 * Require staff or admin
 */
function requireStaff() {
    requireRole('staff', 'admin');
}

/**
 * Require cashier or admin
 */
function requireCashier() {
    requireRole('cashier', 'admin');
}

/**
 * Require permission for a module and action
 */
function requirePermission($module, $action = 'view') {
    if (!checkPermission($module, $action)) {
        denyAccess();
    }
}

/**
 * Require access to a specific page
 */
function requirePageAccess($page) {
    if (!canAccessPage($page)) {
        denyAccess();
    }
}

// ─── Page-Level RBAC (for individual pages) ───

/**
 * Protect categories pages
 */
function protectCategories($action = 'view') {
    if (!checkPermission('categories', $action)) {
        denyAccess();
    }
}

/**
 * Protect units pages
 */
function protectUnits($action = 'view') {
    if (!checkPermission('units', $action)) {
        denyAccess();
    }
}

/**
 * Protect products pages
 */
function protectProducts($action = 'view') {
    if (!checkPermission('products', $action)) {
        denyAccess();
    }
}

/**
 * Protect suppliers pages
 */
function protectSuppliers($action = 'view') {
    if (!checkPermission('suppliers', $action)) {
        denyAccess();
    }
}

/**
 * Protect purchases pages
 */
function protectPurchases($action = 'view') {
    if (!checkPermission('purchases', $action)) {
        denyAccess();
    }
}

/**
 * Protect sales pages
 */
function protectSales($action = 'view') {
    if (!checkPermission('sales', $action)) {
        denyAccess();
    }
}

/**
 * Protect reports pages
 */
function protectReports() {
    if (!checkPermission('reports', 'view')) {
        denyAccess();
    }
}

/**
 * Protect forecast pages
 */
function protectForecast() {
    if (!checkPermission('forecast', 'view')) {
        denyAccess();
    }
}

/**
 * Protect users pages
 */
function protectUsers($action = 'view') {
    if (!checkPermission('users', $action)) {
        denyAccess();
    }
}

/**
 * Protect settings pages
 */
function protectSettings() {
    if (!checkPermission('settings', 'view')) {
        denyAccess();
    }
}
