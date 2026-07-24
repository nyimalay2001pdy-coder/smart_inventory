<?php
/**
 * Supplier Ledger Functions
 * Handles all ledger transactions for suppliers
 */

/**
 * Create the supplier_ledger table if it doesn't exist
 */
function createSupplierLedgerTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS supplier_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        transaction_type ENUM('Purchase', 'Payment', 'Advance Applied', 'Advance Created', 'Adjustment') NOT NULL,
        reference_type VARCHAR(50) NULL,
        reference_id INT NULL,
        reference_no VARCHAR(100) NULL,
        debit DECIMAL(15,2) DEFAULT 0,
        credit DECIMAL(15,2) DEFAULT 0,
        balance DECIMAL(15,2) DEFAULT 0,
        description TEXT NULL,
        transaction_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_supplier_id (supplier_id),
        INDEX idx_transaction_date (transaction_date),
        INDEX idx_reference (reference_type, reference_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    return mysqli_query($conn, $sql);
}

/**
 * Add a ledger entry for a supplier
 * 
 * @param mysqli $conn Database connection
 * @param int $supplier_id Supplier ID
 * @param string $type Transaction type: Purchase, Payment, Advance Applied, Advance Created, Adjustment
 * @param string $reference_type Reference type: purchase, purchase_payment, supplier_payment
 * @param int $reference_id Reference ID
 * @param string $reference_no Reference number (invoice no, etc.)
 * @param float $debit Debit amount (increases what we owe)
 * @param float $credit Credit amount (decreases what we owe)
 * @param string $description Description of the transaction
 * @param string $transaction_date Transaction date (Y-m-d format)
 * @return int|false The inserted ledger entry ID or false on failure
 */
function addSupplierLedgerEntry($conn, $supplier_id, $type, $reference_type, $reference_id, $reference_no, $debit, $credit, $description, $transaction_date) {
    // Get the current running balance for this supplier
    $last_balance = getSupplierLastBalance($conn, $supplier_id);

    // Calculate new balance: debit increases balance (we owe more), credit decreases (we pay)
    $new_balance = $last_balance + $debit - $credit;

    $sql = "INSERT INTO supplier_ledger
        (supplier_id, transaction_type, reference_type, reference_id, reference_no,
         debit, credit, balance, description, transaction_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("LEDGER PREPARE ERROR: " . $conn->error . " SQL: $sql");
        return false;
    }

    $stmt->bind_param("issisddsss",
        $supplier_id, $type, $reference_type, $reference_id, $reference_no,
        $debit, $credit, $new_balance, $description, $transaction_date
    );

    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    error_log("LEDGER EXECUTE ERROR: " . $stmt->error);
    return false;
}

/**
 * Get the last balance for a supplier from the ledger
 */
function getSupplierLastBalance($conn, $supplier_id) {
    $result = mysqli_query($conn, "SELECT balance FROM supplier_ledger WHERE supplier_id = $supplier_id ORDER BY id DESC LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (float)$row['balance'];
    }
    return 0;
}

/**
 * Add a purchase ledger entry
 * Called when a purchase is created
 */
function addPurchaseLedgerEntry($conn, $supplier_id, $purchase_id, $invoice_no, $total_amount, $advance_applied, $purchase_date) {
    // Add the purchase as a debit (we owe this amount)
    $description = "Purchase #$invoice_no";
    if ($advance_applied > 0) {
        $description .= " (Advance applied: " . number_format($advance_applied, 2) . ")";
    }
    
    addSupplierLedgerEntry(
        $conn, 
        $supplier_id, 
        'Purchase', 
        'purchase', 
        $purchase_id, 
        $invoice_no, 
        $total_amount, 
        0, 
        $description, 
        $purchase_date
    );
    
    // If advance was applied, add an advance applied entry
    if ($advance_applied > 0) {
        addSupplierLedgerEntry(
            $conn, 
            $supplier_id, 
            'Advance Applied', 
            'purchase', 
            $purchase_id, 
            $invoice_no, 
            0, 
            $advance_applied, 
            "Advance of " . number_format($advance_applied, 2) . " applied to Purchase #$invoice_no", 
            $purchase_date
        );
    }
    
    return true;
}

/**
 * Add a purchase payment ledger entry
 * Called when a payment is made against a purchase
 */
function addPurchasePaymentLedgerEntry($conn, $supplier_id, $purchase_id, $payment_id, $invoice_no, $payment_amount, $payment_method, $payment_date) {
    $description = "Payment for Purchase #$invoice_no via $payment_method";
    
    addSupplierLedgerEntry(
        $conn, 
        $supplier_id, 
        'Payment', 
        'purchase_payment', 
        $payment_id, 
        $invoice_no, 
        0, 
        $payment_amount, 
        $description, 
        $payment_date
    );
    
    return true;
}

/**
 * Add a direct supplier payment ledger entry
 * Called when a direct payment is made to supplier (not linked to specific purchase)
 */
function addDirectPaymentLedgerEntry($conn, $supplier_id, $payment_id, $reference_no, $payment_amount, $payment_method, $payment_date) {
    $description = "Direct payment via $payment_method";
    
    addSupplierLedgerEntry(
        $conn, 
        $supplier_id, 
        'Payment', 
        'supplier_payment', 
        $payment_id, 
        $reference_no, 
        0, 
        $payment_amount, 
        $description, 
        $payment_date
    );
    
    return true;
}

/**
 * Add an advance created ledger entry
 * Called when an overpayment creates advance credit
 */
function addAdvanceCreatedLedgerEntry($conn, $supplier_id, $reference_type, $reference_id, $reference_no, $advance_amount, $transaction_date) {
    $description = "Advance credit of " . number_format($advance_amount, 2) . " created";
    
    addSupplierLedgerEntry(
        $conn, 
        $supplier_id, 
        'Advance Created', 
        $reference_type, 
        $reference_id, 
        $reference_no, 
        0, 
        $advance_amount, 
        $description, 
        $transaction_date
    );
    
    return true;
}

/**
 * Get all ledger entries for a supplier
 */
function getSupplierLedgerEntries($conn, $supplier_id, $date_from = '', $date_to = '') {
    $sql = "SELECT * FROM supplier_ledger WHERE supplier_id = $supplier_id";
    
    if ($date_from !== '') {
        $sql .= " AND transaction_date >= '" . mysqli_real_escape_string($conn, $date_from) . "'";
    }
    if ($date_to !== '') {
        $sql .= " AND transaction_date <= '" . mysqli_real_escape_string($conn, $date_to) . "'";
    }
    
    $sql .= " ORDER BY id ASC";
    
    return mysqli_query($conn, $sql);
}

/**
 * Recalculate ledger balance from scratch
 * This rebuilds the running balance for all entries
 */
function recalculateLedgerBalance($conn, $supplier_id) {
    // Get all entries ordered by ID
    $result = mysqli_query($conn, "SELECT id, debit, credit FROM supplier_ledger WHERE supplier_id = $supplier_id ORDER BY id ASC");
    
    if (!$result) return false;
    
    $running_balance = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $running_balance += (float)$row['debit'] - (float)$row['credit'];
        mysqli_query($conn, "UPDATE supplier_ledger SET balance = $running_balance WHERE id = {$row['id']}");
    }
    
    return true;
}

/**
 * Initialize ledger from existing data
 * This creates initial ledger entries from existing purchases and payments
 */
function initializeLedgerFromExistingData($conn, $supplier_id) {
    // Check if ledger already has entries for this supplier
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM supplier_ledger WHERE supplier_id = $supplier_id"));
    if ($existing && $existing['count'] > 0) {
        return true; // Already initialized
    }
    
    // Get all purchases for this supplier
    $purchases = mysqli_query($conn, "SELECT * FROM purchases WHERE supplier_id = $supplier_id ORDER BY purchase_date ASC, id ASC");
    
    if (!$purchases) return false;
    
    $conn->begin_transaction();
    
    try {
        while ($purchase = mysqli_fetch_assoc($purchases)) {
            $purchase_id = $purchase['id'];
            $invoice_no = $purchase['invoice_no'];
            $total_amount = (float)$purchase['total_amount'];
            $purchase_date = $purchase['purchase_date'];
            
            // Add purchase entry
            addPurchaseLedgerEntry($conn, $supplier_id, $purchase_id, $invoice_no, $total_amount, 0, $purchase_date);
            
            // Get payments for this purchase
            $amtCol = getPaymentAmountCol($conn, 'purchase_payments');
            $payments = mysqli_query($conn, "SELECT * FROM purchase_payments WHERE purchase_id = $purchase_id ORDER BY id ASC");
            
            if ($payments) {
                while ($payment = mysqli_fetch_assoc($payments)) {
                    $payment_amount = (float)$payment[$amtCol];
                    $advance_applied = (float)($payment['advance_applied'] ?? 0);
                    $advance_created = (float)($payment['advance_created'] ?? 0);
                    $payment_date = $payment['payment_date'];
                    $payment_method = $payment['payment_method'] ?? 'Cash';
                    
                    // Add advance applied entry if any
                    if ($advance_applied > 0) {
                        addSupplierLedgerEntry(
                            $conn, $supplier_id, 'Advance Applied', 'purchase', $purchase_id, $invoice_no,
                            0, $advance_applied, "Advance applied to Purchase #$invoice_no", $payment_date
                        );
                    }
                    
                    // Add payment entry if any cash/kbzpay was paid
                    if ($payment_amount > 0) {
                        addPurchasePaymentLedgerEntry($conn, $supplier_id, $purchase_id, $payment['id'], $invoice_no, $payment_amount, $payment_method, $payment_date);
                    }
                    
                    // Add advance created entry if any
                    if ($advance_created > 0) {
                        addAdvanceCreatedLedgerEntry($conn, $supplier_id, 'purchase_payment', $payment['id'], $invoice_no, $advance_created, $payment_date);
                    }
                }
            }
        }
        
        // Also get direct supplier payments
        if (columnExists($conn, 'supplier_payments', 'supplier_id')) {
            $direct_payments = mysqli_query($conn, "SELECT * FROM supplier_payments WHERE supplier_id = $supplier_id ORDER BY payment_date ASC, id ASC");
            
            if ($direct_payments) {
                while ($dp = mysqli_fetch_assoc($direct_payments)) {
                    addDirectPaymentLedgerEntry(
                        $conn, $supplier_id, $dp['id'], $dp['ref_no'] ?? 'DP-' . $dp['id'],
                        (float)$dp['paid_amount'], $dp['payment_method'] ?? 'Cash', $dp['payment_date']
                    );
                }
            }
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}
