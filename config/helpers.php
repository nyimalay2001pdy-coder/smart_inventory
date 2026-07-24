<?php
// Include supplier ledger functions
require_once __DIR__ . '/supplier_ledger.php';

function sanitize($conn, $value) {
    return mysqli_real_escape_string($conn, trim($value));
}

function executeQuery($conn, $sql, $params = [], $types = '') {
    if (empty($params)) {
        return mysqli_query($conn, $sql);
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();

    $trimmed = strtoupper(ltrim($sql));
    if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\s/', $trimmed)) {
        return $stmt->errno === 0;
    }

    return $stmt->get_result();
}

function fetchOne($conn, $sql, $params = [], $types = '') {
    $result = executeQuery($conn, $sql, $params, $types);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

function fetchAll($conn, $sql, $params = [], $types = '') {
    $result = executeQuery($conn, $sql, $params, $types);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get the payment amount column name for a given table.
 * Handles both old schema (amount) and new schema (paid_amount).
 */
function getPaymentAmountCol($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'paid_amount'");
    $cache[$table] = (mysqli_num_rows($check) > 0) ? 'paid_amount' : 'amount';
    return $cache[$table];
}

/**
 * Check if a column exists in a table.
 */
function columnExists($conn, $table, $column) {
    static $cache = [];
    $key = "$table.$column";
    if (isset($cache[$key])) return $cache[$key];

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    $cache[$key] = (mysqli_num_rows($result) > 0);
    return $cache[$key];
}

/**
 * Recalculate a supplier's outstanding_balance and advance_credit from all their purchases and payments.
 * This is the SINGLE SOURCE OF TRUTH — always use this instead of setting balance fields directly.
 *
 * Balance logic:
 * - outstanding_balance: total_purchases - total_payments (only if positive, else 0)
 * - advance_credit: total_payments - total_purchases (only if positive, else 0)
 * - current_balance = outstanding_balance - advance_credit (for backward compat)
 */
function recalcSupplierBalance($conn, $supplier_id) {
    if ($supplier_id <= 0) return;

    $amtCol = getPaymentAmountCol($conn, 'purchase_payments');

    // Total purchases amount
    $purch_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM purchases WHERE supplier_id = $supplier_id"));
    $total_purchases = max(0, (float)$purch_res['total']);

    // Total payments from purchase_payments (paid_amount + advance_applied)
    $pay_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(pp.$amtCol + pp.advance_applied), 0) AS total FROM purchase_payments pp INNER JOIN purchases p ON pp.purchase_id = p.id WHERE p.supplier_id = $supplier_id"));
    $total_purchase_payments = max(0, (float)$pay_res['total']);

    // Total direct payments from supplier_payments table (if exists)
    $total_direct_payments = 0;
    if (columnExists($conn, 'supplier_payments', 'supplier_id') && columnExists($conn, 'supplier_payments', 'paid_amount')) {
        $dp_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(paid_amount), 0) AS total FROM supplier_payments WHERE supplier_id = $supplier_id"));
        $total_direct_payments = max(0, (float)$dp_res['total']);
    }

    // Total payments = purchase payments + direct payments
    $total_payments = $total_purchase_payments + $total_direct_payments;

    // Outstanding Balance vs Advance Credit (never mixed)
    if ($total_purchases > $total_payments) {
        $outstanding_balance = round($total_purchases - $total_payments, 2);
        $advance_credit = 0;
    } else {
        $outstanding_balance = 0;
        $advance_credit = round($total_payments - $total_purchases, 2);
    }

    // Backward compat fields
    $current_balance = $outstanding_balance - $advance_credit;
    if ($outstanding_balance > 0.01) {
        $new_type = 'Payable';
    } elseif ($advance_credit > 0.01) {
        $new_type = 'Advance';
    } else {
        $new_type = 'Clear';
        $outstanding_balance = 0;
        $advance_credit = 0;
        $current_balance = 0;
    }

    mysqli_query($conn, "UPDATE suppliers SET
        current_balance = $current_balance,
        advance_balance = $advance_credit,
        outstanding_balance = $outstanding_balance,
        advance_credit = $advance_credit,
        balance_type = '$new_type'
        WHERE id = $supplier_id");
}

/**
 * Get supplier balance from the single source of truth (suppliers table).
 * Use this function everywhere to read supplier balance.
 */
function getSupplierBalance($conn, $supplier_id) {
    $supplier = fetchOne($conn, "SELECT outstanding_balance, advance_credit, current_balance, balance_type FROM suppliers WHERE id = ?", [$supplier_id], "i");
    if (!$supplier) {
        return [
            'outstanding_balance' => 0,
            'advance_credit' => 0,
            'current_balance' => 0,
            'balance_type' => 'Clear'
        ];
    }
    return [
        'outstanding_balance' => (float)$supplier['outstanding_balance'],
        'advance_credit' => (float)$supplier['advance_credit'],
        'current_balance' => (float)$supplier['current_balance'],
        'balance_type' => $supplier['balance_type'] ?? 'Clear'
    ];
}

/**
 * Recalculate balances for ALL suppliers.
 * Call this on pages that list suppliers to ensure all balances are up-to-date.
 */
function recalcAllSupplierBalances($conn) {
    $suppliers = mysqli_query($conn, "SELECT id FROM suppliers WHERE status = 'Active'");
    if ($suppliers) {
        while ($row = mysqli_fetch_assoc($suppliers)) {
            recalcSupplierBalance($conn, $row['id']);
        }
    }
}
