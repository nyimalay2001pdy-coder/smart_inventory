<?php

include "../config/db.php";

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$category = mysqli_real_escape_string($conn, $_GET['category'] ?? '');
$status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');

// Product List Query
$sql = "
SELECT 
    products.*,
    categories.name
FROM products
INNER JOIN categories
    ON products.category_id = categories.id
WHERE 1=1
";

// Search product name / SKU

if ($search != "") {

    $sql .= " AND (products.product_name LIKE '%$search%' 
           OR products.sku LIKE '%$search%')";
}



// Category filter

if ($category != "") {

    $sql .= " AND products.category_id = '$category'";
}

// Status filter
if ($status != "") {
    $sql .= " AND products.status = '$status'";
}

$sql .= " ORDER BY products.id DESC";
$result = mysqli_query($conn, $sql);

// Dashboard Count
$total_product = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM products"
    )
);
$active_product = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM products WHERE status='Active'"
    )
);
$inactive_product = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM products WHERE status='Inactive'"
    )
);

$total_quantity = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT SUM(quantity) AS total FROM products"
    )
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-72 bg-white shadow-sm">
            <div class="bg-indigo-600 text-white px-6 py-3">
                <h1 class="text-2xl font-bold">
                    📦 SmartInventory
                </h1>
                <p class="text-sm">
                    Management System
                </p>
            </div>
            <nav class="p-5 space-y-3">
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    🏠 Dashboard
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    🏷 Categories
                </a>
                <a class="block p-3 rounded-lg bg-indigo-600 text-white">
                    📦 Products
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    🚚 Suppliers
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    ⬇ Stock In
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    🛒 Sales
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    📊 Reports
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    👥 Users
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    ⚙ Settings
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800">
                        Product Management
                    </h1>
                    <p class="text-blue-600 mt-2">
                        Dashboard / Products
                    </p>
                </div>
                <a href="add.php"
                    class="bg-indigo-600 text-white px-6 py-3 rounded-xl">
                    ＋ Add Product
                </a>
            </div>

            <!-- Search Area -->
            <form method="GET" class="bg-white p-5 rounded-2xl shadow mt-8 flex gap-4">
                <input
                    type="text"
                    name="search"
                    value="<?= $_GET['search'] ?? '' ?>"
                    class="border rounded-xl px-5 py-3 flex-1"
                    placeholder="Search product name, SKU...">
                <select name="category" class="border rounded-xl px-5 py-3">
                    <option value="">All Categories</option>
                    <?php
                    $cat = mysqli_query($conn, "SELECT * FROM categories");
                    while ($c = mysqli_fetch_assoc($cat)) {
                    ?>
                        <option value="<?= $c['id'] ?>"
                            <?= (isset($_GET['category']) && $_GET['category'] == $c['id']) ? 'selected' : '' ?>>
                            <?= $c['name'] ?>
                        </option>
                    <?php } ?>
                </select>

                <select name="status" class="border rounded-xl px-5 py-3">
                    <option value="">All Status</option>
                    <option value="Active" <?= (($_GET['status'] ?? '') == 'Active') ? 'selected' : '' ?>>
                        Active
                    </option>
                    <option value="Inactive" <?= (($_GET['status'] ?? '') == 'Inactive') ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>
                <button class="bg-indigo-600 text-white px-7 rounded-xl">
                    🔍 Search
                </button>
                <a href="index.php" class="border px-6 rounded-xl flex items-center">
                    Reset
                </a>
            </form>

            <!-- Cards -->
            <div class="grid grid-cols-4 gap-6 mt-8">
                <div class="bg-white p-6 rounded-2xl shadow">
                    <p class="text-gray-500">
                        Total Products
                    </p>
                    <h2 class="text-3xl font-bold mt-2">
                        <?= $total_product['total']; ?>
                    </h2>
                    <p class="text-sm text-gray-400">
                        All product items
                    </p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow">
                    <p class="text-gray-500">
                        Active Products
                    </p>
                    <h2 class="text-3xl font-bold text-green-600 mt-2">
                        <?= $active_product['total']; ?>
                    </h2>
                    <p class="text-sm text-gray-400">
                        Currently active
                    </p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow">
                    <p class="text-gray-500">
                        Inactive Products
                    </p>
                    <h2 class="text-3xl font-bold text-red-500 mt-2">
                        <?= $inactive_product['total']; ?>
                    </h2>
                    <p class="text-sm text-gray-400">
                        Currently inactive
                    </p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow">

                    <p class="text-gray-500">
                        Total Quantity
                    </p>
                    <h2 class="text-3xl font-bold text-blue-600 mt-2">

                        <?= $total_quantity['total'] ?? 0; ?>

                    </h2>
                    <p class="text-sm text-gray-400">
                        In stock
                    </p>
                </div>
            </div>

            <!-- Product Table -->
            <div class="bg-white rounded-2xl shadow mt-8 p-6">
                <table class="w-full">
                    <thead>
                        <tr class="border-b text-gray-500">
                            <th class="4">
                                No
                            </th>
                            <th></th>
                            <th>
                                Product
                            </th>
                            <th>
                                Category
                            </th>
                            <th>
                                SKU
                            </th>
                            <th>
                                Purchase Price
                            </th>
                            <th>
                                Selling Price
                            </th>
                            <th>
                                Quantity
                            </th>
                            <th>
                                Status
                            </th>
                            <th>
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                            <tr class="border-b">
                                <td class="p-4">

                                    <?= $count++ ?>

                                </td>
                                <td>
                                    <div>
                                        <img src="../img/<?= $row['image'] ?>" class=" w-12 h-12 rounded-lg object-cover">
                                    </div>
                                </td>

                                <td>
                                    <div class="font-bold">
                                        <?= $row['product_name'] ?>
                                    </div>
                                    <p class="text-sm text-gray-400">
                                        <?= $row['unit'] ?>
                                    </p>
                                </td>
                                <td>
                                    <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full">
                                        <?= $row['name'] ?>
                                    </span>
                                </td>







                                <td>

                                    <?= $row['sku'] ?>

                                </td>






                                <td>

                                    <?= number_format($row['purchase_price']) ?>

                                    Ks

                                </td>







                                <td class="text-green-600 font-bold">


                                    <?= number_format($row['selling_price']) ?>

                                    Ks


                                </td>







                                <td>


                                    <?= $row['quantity'] ?>


                                    <?= $row['unit'] ?>


                                </td>








                                <td>


                                    <?php if ($row['status'] == "Active") { ?>


                                        <span class="bg-green-100 text-green-600 px-3 py-1 rounded-full">

                                            Active

                                        </span>


                                    <?php } else { ?>


                                        <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full">

                                            Inactive

                                        </span>


                                    <?php } ?>


                                </td>







                                <td>



                                    <a href="edit.php?id=<?= $row['id'] ?>"

                                        class="bg-blue-100 text-blue-600 px-3 py-2 rounded">


                                        ✏

                                    </a>





                                    <a href="delete.php?id=<?= $row['id'] ?>"

                                        onclick="return confirm('Are you sure you want to delete this product?')"

                                        class="bg-red-100 text-red-600 px-3 py-2 rounded-lg">


                                        🗑 Delete


                                    </a>



                                </td>







                            </tr>



                        <?php

                        }

                        ?>


                    </tbody>

                </table>


            </div>






            <!-- Pagination -->


            <div class="flex justify-between mt-5">


                <p class="text-gray-500">

                    Showing 1 to 6 of 28 entries

                </p>



                <div class="space-x-2">


                    <button class="px-3 py-2 border rounded">
                        «
                    </button>


                    <button class="px-4 py-2 bg-indigo-600 text-white rounded">
                        1
                    </button>


                    <button class="px-4 py-2 border rounded">
                        2
                    </button>


                    <button class="px-4 py-2 border rounded">
                        3
                    </button>


                    <button class="px-3 py-2 border rounded">
                        »
                    </button>


                </div>


            </div>






        </main>


    </div>



</body>

</html>