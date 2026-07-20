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
