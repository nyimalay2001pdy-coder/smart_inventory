<?php
session_start();
include "../config/db.php";
// Cart Create
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add Product To Cart
if (isset($_POST['add_cart'])) {
    $product_id = $_POST['product_id'];
    $qty = $_POST['quantity'];
    $price = $_POST['purchase_price'];
    // Product Name 
    $product = mysqli_fetch_assoc(
        mysqli_query(
            $conn,
            "SELECT product_name FROM products WHERE id='$product_id'"
        )
    );
    $_SESSION['cart'][] = [
        "product_id" => $product_id,
        "product_name" => $product['product_name'],
        "quantity" => $qty,
        "price" => $price,
        "subtotal" => $qty * $price
    ];
}
if (isset($_POST['save_purchase'])) {
    $supplier_id = $_POST['supplier_id'];
    $payment_status = $_POST['payment_status'];
    $date = date("Y-m-d");

    // Cart Empty Check
    if (count($_SESSION['cart']) > 0) {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['subtotal'];
        }

        // Insert Stock In Header

        $sql = "INSERT INTO stock_in(
        supplier_id,
        purchase_date,
        total_amount,
        payment_status)
        VALUES(
        '$supplier_id',
        '$date',
        '$total',
        '$payment_status')";
        mysqli_query($conn, $sql);

        // Get Stock ID
        $stock_id = mysqli_insert_id($conn);

        // Insert Details + Update Product
        foreach ($_SESSION['cart'] as $item) {
            $product_id = $item['product_id'];
            $qty = $item['quantity'];
            $price = $item['price'];
            $subtotal = $item['subtotal'];
            // Insert Detail
            mysqli_query($conn, "INSERT INTO stock_in_details
(
stock_in_id,
product_id,
quantity,
purchase_price,
subtotal
)
VALUES
(
'$stock_id',
'$product_id',
'$qty',
'$price',
'$subtotal'
)
");

            // Update Product Stock
            mysqli_query($conn, "UPDATE products SET 
 quantity = quantity + $qty,
purchase_price = '$price'
WHERE id='$product_id");
        }

        // Clear Cart
        unset($_SESSION['cart']);
        header("Location:index.php");
    }
}

// Remove Cart Item
if (isset($_GET['remove'])) {
    $key = $_GET['remove'];
    unset($_SESSION['cart'][$key]);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}

// Supplier List
$suppliers = mysqli_query(
    $conn,
    "SELECT * FROM suppliers"
);

// Product List

$products = mysqli_query(
    $conn,
    "SELECT * FROM products"
);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Add Purchase</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <div class="max-w-5xl mx-auto p-8">
        <h1 class="text-3xl font-bold mb-6">
            Add Purchase
        </h1>

        <!-- Supplier -->
        <div class="bg-white p-6 rounded-xl shadow mb-6">
            <label class="font-semibold">
                Supplier
            </label>
            <select
                name=" supplier_id"
                class="w-full border p-3 rounded-lg mt-2">
                <?php
                $suppliers = mysqli_query(
                    $conn,
                    "SELECT * FROM suppliers"
                );
                while ($s = mysqli_fetch_assoc($suppliers)) { ?>
                    <option value="<?= $s['id'] ?>">
                        <?= $s['supplier_name'] ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <select
            name="payment_status"
            class="w-full border p-3 rounded-lg mt-4">
            <option value="Paid">Paid</option>
            <option value="Unpaid">Unpaid</option>
        </select>

        <!-- Add Product -->


        <div class="bg-white p-6 rounded-xl shadow">


            <h2 class="text-xl font-bold mb-4">

                Add Product

            </h2>




            <form method="POST">



                <select

                    name="product_id"

                    class="border p-3 rounded-lg">


                    <?php while ($p = mysqli_fetch_assoc($products)) { ?>


                        <option value="<?= $p['id'] ?>">


                            <?= $p['product_name'] ?>


                        </option>


                    <?php } ?>


                </select>





                <input

                    type="number"

                    name="quantity"

                    placeholder="Quantity"

                    class="border p-3 rounded-lg">





                <input

                    type="number"

                    name="purchase_price"

                    placeholder="Purchase Price"

                    class="border p-3 rounded-lg">





                <button

                    name="add_cart"

                    class="bg-indigo-600 text-white px-5 py-3 rounded-lg">


                    Add


                </button>



            </form>


        </div>







        <!-- Cart Table -->


        <div class="bg-white p-6 rounded-xl shadow mt-6">


            <h2 class="text-xl font-bold mb-4">

                Purchase Items

            </h2>

            <table class="w-full">


                <tr class="border-b">


                    <th class="p-3 text-left">

                        Product

                    </th>


                    <th>
                        Qty
                    </th>
                    <th>
                        Price
                    </th>
                    <th>
                        Subtotal
                    </th>
                    <th>
                        Action
                    </th>
                </tr>
                <?php
                $total = 0;
                foreach ($_SESSION['cart'] as $key => $item) {
                    $total += $item['subtotal'];
                ?>
                    <tr class="border-b">
                        <td class="p-3">
                            <?= $item['product_name'] ?>
                        </td>
                        <td>
                            <?= $item['quantity'] ?>
                        </td>
                        <td>
                            <?= number_format($item['price']) ?>
                        </td>
                        <td>
                            <?= number_format($item['subtotal']) ?>
                            Ks
                        </td>
                        <td>
                            <a href="?remove=<?= $key ?>"
                                class="text-red-600">
                                Remove
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </table>
            <div class="text-right text-2xl font-bold mt-5">
                Total:
                <?= number_format($total) ?>
                Ks
            </div>
            <form method="POST">
                <div class="text-right mt-6">
                    <button
                        name="save_purchase"
                        class="bg-green-600 text-white px-6 py-3">Save Purchase</button>
                </div>
            </form>

        </div>
    </div>
</body>

</html>