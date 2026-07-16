<?php
require_once 'config/database.php';

echo "=== Units Migration ===\n\n";

function columnExists(mysqli $conn, string $table, string $column): bool {
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && mysqli_num_rows($res) > 0;
}

function tableExists(mysqli $conn, string $table): bool {
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $res && mysqli_num_rows($res) > 0;
}

function runQuery(mysqli $conn, string $sql, string $label): bool {
    $res = @mysqli_query($conn, $sql);
    if (!$res) {
        echo "   FAIL [$label]: " . mysqli_error($conn) . "\n";
        return false;
    }
    echo "   OK - $label\n";
    return true;
}

// 1. Create units table (drop first if it exists but is malformed)
echo "1. Setting up 'units' table...\n";
if (tableExists($conn, 'units')) {
    $has_unit_id = columnExists($conn, 'units', 'unit_id');
    $has_unit_symbol = columnExists($conn, 'units', 'unit_symbol');
    if ($has_unit_id && $has_unit_symbol) {
        echo "   units table already exists with correct schema\n\n";
    } else {
        echo "   units table exists but is missing columns - recreating...\n";
        runQuery($conn, "DROP TABLE units", "Drop malformed units table");
        runQuery($conn, "CREATE TABLE units (
            unit_id INT AUTO_INCREMENT PRIMARY KEY,
            unit_name VARCHAR(50) NOT NULL,
            unit_symbol VARCHAR(20) NOT NULL,
            status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB", "Create units table");
    }
} else {
    runQuery($conn, "CREATE TABLE units (
        unit_id INT AUTO_INCREMENT PRIMARY KEY,
        unit_name VARCHAR(50) NOT NULL,
        unit_symbol VARCHAR(20) NOT NULL,
        status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB", "Create units table");
}

// 2. Insert default units
echo "\n2. Seeding default units...\n";
$defaults = [
    ['Piece', 'pcs'],
    ['Box', 'box'],
    ['Kilogram', 'kg'],
    ['Liter', 'liter'],
    ['Pack', 'pack'],
    ['Bottle', 'bottle'],
    ['Can', 'can'],
];
foreach ($defaults as $d) {
    @mysqli_query($conn, "INSERT IGNORE INTO units (unit_name, unit_symbol, status) VALUES ('{$d[0]}', '{$d[1]}', 'Active')");
}
$count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM units"))['cnt'];
echo "   OK - $count units in table\n\n";

// 3. Check products table state
$has_unit_col = columnExists($conn, 'products', 'unit');
$has_unit_id_col = columnExists($conn, 'products', 'unit_id');

if ($has_unit_id_col) {
    echo "3. products.unit_id already exists - skipping product migration\n\n";
} elseif (!$has_unit_col) {
    echo "3. products has neither 'unit' nor 'unit_id' - adding unit_id with default\n\n";
    runQuery($conn, "ALTER TABLE products ADD COLUMN unit_id INT NOT NULL DEFAULT 1 AFTER barcode", "Add unit_id column");
} else {
    echo "3. Adding 'unit_id' column to products...\n";
    runQuery($conn, "ALTER TABLE products ADD COLUMN unit_id INT DEFAULT NULL AFTER barcode", "Add unit_id column");

    // 4. Migrate existing unit values
    echo "\n4. Migrating existing unit data...\n";
    $migrated = 0;
    $res = mysqli_query($conn, "SELECT id, unit FROM products WHERE unit IS NOT NULL AND unit != ''");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $sym = mysqli_real_escape_string($conn, $row['unit']);
            $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT unit_id FROM units WHERE unit_symbol='$sym' LIMIT 1"));
            if ($u) {
                mysqli_query($conn, "UPDATE products SET unit_id={$u['unit_id']} WHERE id={$row['id']}");
                $migrated++;
            }
        }
    }
    echo "   OK - $migrated products migrated\n\n";

    // 5. Default remaining NULL unit_id to 'Piece' (unit_id=1)
    echo "5. Setting default unit_id for unmapped products...\n";
    mysqli_query($conn, "UPDATE products SET unit_id = 1 WHERE unit_id IS NULL");
    echo "   OK\n\n";

    // 6. Make NOT NULL
    echo "6. Setting unit_id NOT NULL with default 1...\n";
    runQuery($conn, "ALTER TABLE products MODIFY COLUMN unit_id INT NOT NULL DEFAULT 1", "Set NOT NULL");

    // 7. Drop old unit column
    if ($has_unit_col) {
        echo "\n7. Dropping old 'unit' column...\n";
        runQuery($conn, "ALTER TABLE products DROP COLUMN unit", "Drop unit column");
    }
}

// 8. Add foreign key (check first)
$has_fk = columnExists($conn, 'products', 'unit_id');
$fk_check = @mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_products_unit'");
if ($has_fk && (!$fk_check || mysqli_num_rows($fk_check) === 0)) {
    echo "8. Adding foreign key constraint...\n";
    runQuery($conn, "ALTER TABLE products ADD CONSTRAINT fk_products_unit FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON UPDATE CASCADE ON DELETE RESTRICT", "Add FK");
} else {
    echo "8. Foreign key already exists or unit_id missing - skipping\n\n";
}

echo "\n=== Migration Complete ===\n";
echo "\nVerify: Run units/index.php in your browser.\n";
