<?php
include "../includes/auth_check.php";
include "../config/database.php";
$page_title = "Products";

$action = $_GET['action'] ?? 'list';
$is_admin = isAdmin();

if (!$is_admin && in_array($action, ['add', 'edit', 'delete'])) {
    header("Location: index.php");
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM products WHERE id=$id"));
    if ($p && $p['image'] && file_exists("../img/" . $p['image'])) {
        unlink("../img/" . $p['image']);
    }
    mysqli_query($conn, "DELETE FROM products WHERE id=$id");
    header("Location:index.php");
    exit;
}

if ($action === 'edit' && isset($_POST['update'])) {
    $id = (int)$_GET['id'];
    $category_id = (int)$_POST['category_id'];
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 'NULL';
    $minimum_stock = (int)$_POST['minimum_stock'];
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM products WHERE id=$id"));
    $image = $old['image'] ?? '';

    if ($_FILES['image']['name'] != "") {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image = 'prod_' . time() . '_' . $id . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], "../img/" . $image);
    }

    mysqli_query($conn, "UPDATE products SET category_id=$category_id, product_name='$product_name', sku='$sku', barcode='$barcode', brand='$brand', unit='$unit', supplier_id=$supplier_id, minimum_stock=$minimum_stock, purchase_price=$purchase_price, selling_price=$selling_price, description='$description', image='$image', status='$status' WHERE id=$id");
    header("Location:index.php");
    exit;
}

if ($action === 'add' && isset($_POST['save'])) {
    $category_id = (int)$_POST['category_id'];
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 'NULL';
    $minimum_stock = (int)$_POST['minimum_stock'];
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = 'Active';
    $image = "";

    if ($_FILES['image']['name'] != "") {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image = 'prod_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], "../img/" . $image);
    }

    mysqli_query($conn, "INSERT INTO products (category_id, product_name, sku, barcode, brand, unit, supplier_id, minimum_stock, purchase_price, selling_price, description, image, status) VALUES ($category_id, '$product_name', '$sku', '$barcode', '$brand', '$unit', $supplier_id, $minimum_stock, $purchase_price, $selling_price, '$description', '$image', '$status')");
    header("Location:index.php");
    exit;
}

// ========== EDIT FORM DATA ==========
$product = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM products WHERE id='$id'";
    $result = mysqli_query($conn, $sql);
    $product = mysqli_fetch_assoc($result);
    if (!$product) {
        die("Product not found");
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-6">

                <?php if ($action === 'view'): ?>
                    <?php
                    $view_id = (int)($_GET['id'] ?? 0);
                    $view_product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = $view_id"));
                    if (!$view_product) {
                        echo '<div class="text-center py-20"><h2 class="text-2xl font-bold text-gray-400">Product not found</h2><a href="index.php" class="text-indigo-600 mt-4 inline-block">← Back to Products</a></div>';
                    } else {
                        $vqty = (int)$view_product['quantity'];
                        $vmin = (int)$view_product['minimum_stock'];
                        if ($vqty == 0) {
                            $vstockLabel = 'Out of Stock';
                            $vstockClass = 'bg-red-100 text-red-700';
                        } elseif ($vqty <= $vmin) {
                            $vstockLabel = 'Low Stock';
                            $vstockClass = 'bg-amber-100 text-amber-700';
                        } else {
                            $vstockLabel = 'In Stock';
                            $vstockClass = 'bg-green-100 text-green-700';
                        }
                    ?>
                        <div class="min-h-screen flex items-center justify-center">
                            <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-3xl">
                                <h1 class="text-3xl font-bold mb-2">Product Details</h1>
                                <p class="text-gray-500 mb-8">View-only product information</p>

                                <div class="grid grid-cols-2 gap-6">
                                    <div class="col-span-2 flex items-center gap-6">
                                        <?php if ($view_product['image']): ?>
                                            <img src="../img/<?= htmlspecialchars($view_product['image']) ?>" class="w-24 h-24 rounded-xl object-cover shadow">
                                        <?php else: ?>
                                            <div class="w-24 h-24 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400">No Image</div>
                                        <?php endif; ?>
                                        <div>
                                            <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($view_product['product_name']) ?></h2>
                                            <p class="text-gray-500"><?= htmlspecialchars($view_product['brand'] ?? '') ?> · <?= htmlspecialchars($view_product['unit']) ?></p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">SKU</label>
                                        <p class="text-gray-900 font-mono mt-1"><?= htmlspecialchars($view_product['sku']) ?></p>
                                    </div>

                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Category</label>
                                        <p class="mt-1"><span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm"><?= htmlspecialchars($view_product['category_name'] ?? '—') ?></span></p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Supplier</label>
                                        <p class="text-gray-900 mt-1"><?= htmlspecialchars($view_product['supplier_id'] ? 'ID: ' . $view_product['supplier_id'] : '—') ?></p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Purchase Price</label>
                                        <p class="text-gray-900 mt-1"><?= number_format($view_product['purchase_price']) ?> Ks</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Selling Price</label>
                                        <p class="text-green-600 font-bold mt-1"><?= number_format($view_product['selling_price']) ?> Ks</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Stock Quantity</label>
                                        <p class="mt-1">
                                            <span class="<?= $vstockClass ?> px-3 py-1 rounded-full text-sm font-semibold"><?= $vstockLabel ?></span>
                                            <span class="text-gray-500 ml-2">(<?= $vqty ?> <?= htmlspecialchars($view_product['unit']) ?>)</span>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Minimum Stock</label>
                                        <p class="text-gray-900 mt-1"><?= $view_product['minimum_stock'] ?></p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Status</label>
                                        <p class="mt-1">
                                            <?php if ($view_product['status'] === 'Active'): ?>
                                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">Active</span>
                                            <?php else: ?>
                                                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm font-semibold">Inactive</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Created Date</label>
                                        <p class="text-gray-900 mt-1"><?= date('d M Y', strtotime($view_product['created_at'])) ?></p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-500">Description</label>
                                        <p class="text-gray-900 mt-1 whitespace-pre-wrap"><?= htmlspecialchars($view_product['description'] ?? '—') ?></p>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-4 mt-8 border-t pt-6">
                                    <a href="index.php" class="px-6 py-3 bg-gray-200 rounded-lg font-semibold hover:bg-gray-300 transition">Close</a>
                                    <?php if ($is_admin): ?>
                                        <a href="?action=edit&id=<?= $view_product['id'] ?>" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition">Edit Product</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                <?php elseif ($action === 'add' || $action === 'edit'): ?>

                    <?php $is_edit = ($action === 'edit' && $product); ?>
                    <div class="min-h-screen flex items-center justify-center">
                        <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-3xl">
                            <h1 class="text-3xl font-bold mb-2">
                                <?= $is_edit ? 'Edit Product' : 'Add New Product' ?>
                            </h1>
                            <p class="text-gray-500 mb-8">
                                <?= $is_edit ? 'Update product information' : 'Create product information' ?>
                            </p>
                            <form method="POST" enctype="multipart/form-data">

                                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Product Information</h2>
                                <div class="grid grid-cols-2 gap-5">
                                    <div>
                                        <label class="font-semibold">Image</label>
                                        <input type="file" name="image" class="w-full border rounded-lg p-3 mt-2">
                                        <?php if ($is_edit && $product['image']): ?>
                                            <div class="flex gap-3 items-center mt-3">
                                                <img src="../img/<?= $product['image'] ?>" class="w-16 h-16 rounded-lg object-cover">
                                                <p class="text-sm text-gray-400">Leave empty to keep current image</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label class="font-semibold">Product Name</label>
                                        <input type="text" name="product_name" value="<?= $is_edit ? $product['product_name'] : '' ?>" class="w-full border rounded-lg p-3 mt-2" placeholder="Enter product name">
                                    </div>
                                    <div>
                                        <label class="font-semibold">SKU</label>
                                        <input type="text" name="sku" value="<?= $is_edit ? $product['sku'] : '' ?>" class="w-full border rounded-lg p-3 mt-2" placeholder="Example: DRK001">
                                    </div>
                                    <div>
                                        <label class="font-semibold">Barcode</label>
                                        <input type="text" name="barcode" value="<?= $is_edit ? $product['barcode'] : '' ?>" class="w-full border rounded-lg p-3 mt-2" placeholder="Enter barcode">
                                    </div>
                                    <div>
                                        <label class="font-semibold">Category</label>
                                        <select name="category_id" class="w-full border rounded-lg p-3 mt-2">
                                            <option>Select Category</option>
                                            <?php
                                            $cat_sql = "SELECT * FROM categories ORDER BY name ASC";
                                            $categories = mysqli_query($conn, $cat_sql);
                                            while ($cat = mysqli_fetch_assoc($categories)) {
                                                $sel = ($is_edit && $cat['id'] == $product['category_id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $cat['id'] ?>" <?= $sel ?>><?= $cat['name'] ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="font-semibold">Brand</label>
                                        <input type="text" name="brand" value="<?= $is_edit ? $product['brand'] : '' ?>" class="w-full border rounded-lg p-3 mt-2" placeholder="Enter brand">
                                    </div>
                                    <div>
                                        <label class="font-semibold">Unit</label>
                                        <select name="unit" class="w-full border rounded-lg p-3 mt-2">
                                            <?php $units = ['pcs', 'box', 'kg', 'liter'];
                                            foreach ($units as $u) {
                                                $sel = ($is_edit && $product['unit'] == $u) ? 'selected' : '';
                                            ?>
                                                <option <?= $sel ?>><?= $u ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <h2 class="text-lg font-bold text-gray-800 mb-4 mt-8 border-b pb-2">Pricing</h2>
                                <div class="grid grid-cols-3 gap-5">
                                    <div>
                                        <label class="font-semibold">Purchase Price</label>
                                        <input type="number" name="purchase_price" id="purchase_price" value="<?= $is_edit ? $product['purchase_price'] : '' ?>" class="w-full border rounded-lg p-3 mt-2" oninput="calcProfit()">
                                    </div>
                                    <div>
                                        <label class="font-semibold">Selling Price</label>
                                        <input type="number" name="selling_price" id="selling_price" value="<?= $is_edit ? $product['selling_price'] : '' ?>" class="w-full border rounded-lg p-3 mt-2" oninput="calcProfit()">
                                    </div>
                                    <div>
                                        <label class="font-semibold">Profit (Auto)</label>
                                        <input type="text" id="profit" class="w-full border rounded-lg p-3 mt-2 bg-gray-100" readonly>
                                    </div>
                                </div>

                                <h2 class="text-lg font-bold text-gray-800 mb-4 mt-8 border-b pb-2">Inventory</h2>
                                <div class="grid grid-cols-2 gap-5">
                                    <div>
                                        <label class="font-semibold">Supplier</label>
                                        <select name="supplier_id" class="w-full border rounded-lg p-3 mt-2">
                                            <option value="">Select Supplier</option>
                                            <?php
                                            $sup_sql = "SELECT * FROM suppliers ORDER BY supplier_name ASC";
                                            $suppliers = mysqli_query($conn, $sup_sql);
                                            while ($sup = mysqli_fetch_assoc($suppliers)) {
                                                $sel = ($is_edit && $sup['id'] == $product['supplier_id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $sup['id'] ?>" <?= $sel ?>><?= $sup['supplier_name'] ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="font-semibold">Minimum Stock</label>
                                        <input type="number" name="minimum_stock" value="<?= $is_edit ? $product['minimum_stock'] : '' ?>" class="w-full border rounded-lg p-3 mt-2" placeholder="Alert threshold">
                                    </div>
                                    <?php if ($is_edit): ?>
                                        <div>
                                            <label class="font-semibold">Status</label>
                                            <select name="status" class="w-full border rounded-lg p-3 mt-2">
                                                <option value="Active" <?= ($product['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                                                <option value="Inactive" <?= ($product['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <h2 class="text-lg font-bold text-gray-800 mb-4 mt-8 border-b pb-2">Description</h2>
                                <textarea name="description" rows="4" class="w-full border rounded-lg p-3 mt-2" placeholder="Enter product description..."><?= $is_edit ? $product['description'] : '' ?></textarea>

                                <div class="flex justify-end gap-4 mt-8">
                                    <a href="index.php" class="px-6 py-3 bg-gray-200 rounded-lg">Cancel</a>
                                    <button name="<?= $is_edit ? 'update' : 'save' ?>" class="px-6 py-3 bg-indigo-600 text-white rounded-lg">
                                        <?= $is_edit ? 'Update Product' : 'Save Product' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                        function calcProfit() {
                            var purchase = parseFloat(document.getElementById('purchase_price').value) || 0;
                            var selling = parseFloat(document.getElementById('selling_price').value) || 0;
                            document.getElementById('profit').value = (selling - purchase).toFixed(2);
                        }
                        <?php if ($is_edit): ?>
                            calcProfit();
                        <?php endif; ?>
                    </script>

                <?php else: ?>

                    <?php
                    $search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
                    $category = mysqli_real_escape_string($conn, $_GET['category'] ?? '');
                    $status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');

                    $sql = "
SELECT products.*, categories.name
FROM products
INNER JOIN categories ON products.category_id = categories.id
WHERE 1=1
";
                    if ($search != "") {
                        $sql .= " AND (products.product_name LIKE '%$search%' OR products.sku LIKE '%$search%')";
                    }
                    if ($category != "") {
                        $sql .= " AND products.category_id = '$category'";
                    }
                    if ($status != "") {
                        $sql .= " AND products.status = '$status'";
                    }
                    $sql .= " ORDER BY products.id DESC";
                    $result = mysqli_query($conn, $sql);

                    $total_product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products"));
                    $active_product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE status='Active'"));
                    $inactive_product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE status='Inactive'"));
                    $total_quantity = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) AS total FROM products"));
                    ?>

                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-4xl font-bold text-gray-800">Product Management</h1>
                            <p class="text-blue-600 mt-2">Dashboard / Products</p>
                        </div>
                        <?php if ($is_admin) { ?>
                            <a href="?action=add" class="bg-indigo-600 text-white px-6 py-3 rounded-xl">＋ Add Product</a>
                        <?php } ?>
                    </div>

                    <form method="GET" class="bg-white p-5 rounded-2xl shadow mt-8 flex gap-4">
                        <input type="text" name="search" value="<?= $_GET['search'] ?? '' ?>" class="border rounded-xl px-5 py-3 flex-1" placeholder="Search product name, SKU...">
                        <select name="category" class="border rounded-xl px-5 py-3">
                            <option value="">All Categories</option>
                            <?php
                            $cat = mysqli_query($conn, "SELECT * FROM categories");
                            while ($c = mysqli_fetch_assoc($cat)) {
                            ?>
                                <option value="<?= $c['id'] ?>" <?= (isset($_GET['category']) && $_GET['category'] == $c['id']) ? 'selected' : '' ?>><?= $c['name'] ?></option>
                            <?php } ?>
                        </select>
                        <select name="status" class="border rounded-xl px-5 py-3">
                            <option value="">All Status</option>
                            <option value="Active" <?= (($_GET['status'] ?? '') == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= (($_GET['status'] ?? '') == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <button class="bg-indigo-600 text-white px-7 rounded-xl">Search</button>
                        <a href="index.php" class="border px-6 rounded-xl flex items-center">Reset</a>
                    </form>

                    <div class="grid grid-cols-4 gap-6 mt-8">
                        <div class="bg-white p-6 rounded-2xl shadow">
                            <p class="text-gray-500">Total Products</p>
                            <h2 class="text-3xl font-bold mt-2"><?= $total_product['total']; ?></h2>
                            <p class="text-sm text-gray-400">All product items</p>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow">
                            <p class="text-gray-500">Active Products</p>
                            <h2 class="text-3xl font-bold text-green-600 mt-2"><?= $active_product['total']; ?></h2>
                            <p class="text-sm text-gray-400">Currently active</p>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow">
                            <p class="text-gray-500">Inactive Products</p>
                            <h2 class="text-3xl font-bold text-red-500 mt-2"><?= $inactive_product['total']; ?></h2>
                            <p class="text-sm text-gray-400">Currently inactive</p>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow">
                            <p class="text-gray-500">Total Quantity</p>
                            <h2 class="text-3xl font-bold text-blue-600 mt-2"><?= $total_quantity['total'] ?? 0; ?></h2>
                            <p class="text-sm text-gray-400">In stock</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow mt-8 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 border-b text-left text-gray-500 uppercase text-xs tracking-wider">
                                        <th class="px-4 py-3 w-12">#</th>
                                        <th class="px-4 py-3 w-16">Image</th>
                                        <th class="px-4 py-3">Product Name</th>
                                        <th class="px-4 py-3">SKU</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3 text-right">Purchase Price</th>
                                        <th class="px-4 py-3 text-right">Selling Price</th>
                                        <th class="px-4 py-3 text-center">Stock</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                        <th class="px-4 py-3 text-center">Created Date</th>
                                        <th class="px-4 py-3 text-center sticky right-0 bg-gray-50 z-10">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $count = 1;
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $qty = (int)$row['quantity'];
                                        $minStock = (int)$row['minimum_stock'];
                                        if ($qty == 0) {
                                            $stockBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Out of Stock</span>';
                                        } elseif ($qty <= $minStock) {
                                            $stockBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Low Stock</span>';
                                        } else {
                                            $stockBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">In Stock</span>';
                                        }
                                    ?>
                                        <tr class="border-b hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 text-gray-500"><?= $count++ ?></td>
                                            <td class="px-4 py-3">
                                                <?php if ($row['image']): ?>
                                                    <img src="../img/<?= htmlspecialchars($row['image']) ?>" class="w-10 h-10 rounded-lg object-cover" alt="Product">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 text-xs">N/A</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['product_name']) ?></div>
                                                <p class="text-xs text-gray-400"><?= htmlspecialchars($row['unit']) ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600 font-mono text-xs"><?= htmlspecialchars($row['sku']) ?></td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700"><?= htmlspecialchars($row['name']) ?></span>
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700"><?= number_format($row['purchase_price']) ?> Ks</td>
                                            <td class="px-4 py-3 text-right font-bold text-green-600"><?= number_format($row['selling_price']) ?> Ks</td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="flex flex-col items-center gap-1">
                                                    <?= $stockBadge ?>
                                                    <span class="text-xs text-gray-500"><?= $qty ?> <?= htmlspecialchars($row['unit']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($row['status'] === 'Active'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-500 text-xs">
                                                <?= date('d M Y', strtotime($row['created_at'])) ?>
                                            </td>
                                            <td class="px-4 py-3 text-center sticky right-0 bg-white z-10">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <a href="?action=view&id=<?= $row['id'] ?>" title="View" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>
                                                    <?php if ($is_admin): ?>
                                                        <a href="?action=edit&id=<?= $row['id'] ?>" title="Edit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </a>
                                                        <a href="?action=delete&id=<?= $row['id'] ?>" title="Delete" onclick="return confirm('Are you sure you want to delete this product?')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    </div>
    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>