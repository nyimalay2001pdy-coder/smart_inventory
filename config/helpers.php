<?php
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
 * Recalculate a supplier's current_balance (unpaid purchase amounts) from all their purchases.
 * This is the authoritative balance calculation — always use this instead of setting current_balance directly.
 */
function recalcSupplierBalance($conn, $supplier_id) {
    $amtCol = getPaymentAmountCol($conn, 'purchase_payments');

    // Calculate total outstanding across all purchases for this supplier
    $bal_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(
        CASE WHEN IFNULL(pp.total_paid, 0) >= p.total_amount THEN 0
             ELSE p.total_amount - IFNULL(pp.total_paid, 0)
        END
    ), 0) AS outstanding
    FROM purchases p
    LEFT JOIN (
        SELECT purchase_id, SUM($amtCol) AS total_paid
        FROM purchase_payments
        GROUP BY purchase_id
    ) pp ON p.id = pp.purchase_id
    WHERE p.supplier_id = $supplier_id"));
    $outstanding = max(0, (float)$bal_res['outstanding']);

    if ($outstanding > 0) {
        mysqli_query($conn, "UPDATE suppliers SET current_balance = $outstanding, balance_type = 'Payable' WHERE id = $supplier_id");
    } else {
        // Check if advance_balance > 0 to determine correct type
        $sup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT advance_balance FROM suppliers WHERE id = $supplier_id"));
        $adv = $sup ? (float)$sup['advance_balance'] : 0;
        if ($adv > 0) {
            mysqli_query($conn, "UPDATE suppliers SET current_balance = 0.00, balance_type = 'Advance' WHERE id = $supplier_id");
        } else {
            mysqli_query($conn, "UPDATE suppliers SET current_balance = 0.00, balance_type = 'Clear' WHERE id = $supplier_id");
        }
    }
}
