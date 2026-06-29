<?php
include "../config/db.php";

// Get Product ID
if(!isset($_GET['id'])){
    die("No Product ID Found");
}
$id = $_GET['id'];

// Get Product Data
$sql = "
SELECT * FROM products
WHERE id='$id'
";
$result = mysqli_query($conn, $sql);
$product = mysqli_fetch_assoc($result);

// Get Categories
$cat_sql = "SELECT * FROM categories";
$categories = mysqli_query($conn, $cat_sql);


if (isset($_POST['update'])) {
    $category_id = $_POST['category_id'];
    $product_name = $_POST['product_name'];
    $sku = $_POST['sku'];
    $purchase_price = $_POST['purchase_price'];
    $selling_price = $_POST['selling_price'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    $status = $_POST['status'];

    // Old Image
    $image = $product['image'];

    // Check New Image
    if ($_FILES['image']['name'] != "") {
        $new_image = $_FILES['image']['name'];
        $tmp = $_FILES['image']['tmp_name'];
        move_uploaded_file(
            $tmp,
            "../img/" . $new_image

        );
        $image = $new_image;
    }

    $update = "
UPDATE products SET
category_id='$category_id',
product_name='$product_name',
sku='$sku',
purchase_price='$purchase_price',
selling_price='$selling_price',
quantity='$quantity',
unit='$unit',
image='$image',
status='$status'
WHERE id='$id'
";

    $result = mysqli_query($conn, $update);
    if ($result) {
        header("Location:index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-3xl">
            <h1 class="text-3xl font-bold">
                Edit Product
            </h1>
            <p class="text-gray-500 mb-8">
                Update product information
            </p>
            <form method="POST"
                enctype="multipart/form-data">





                <div class="grid grid-cols-2 gap-5">





                    <div>


                        <label>

                            Category

                        </label>


                        <select

                            name="category_id"

                            class="w-full border p-3 rounded-lg">


                            <?php while ($cat = mysqli_fetch_assoc($categories)) { ?>



                                <option

                                    value="<?= $cat['id'] ?>"

                                    <?= ($cat['id'] == $product['category_id']) ? 'selected' : '' ?>>


                                    <?= $cat['name'] ?>


                                </option>



                            <?php } ?>


                        </select>


                    </div>







                    <div>


                        <label>

                            Product Name

                        </label>


                        <input

                            name="product_name"

                            value="<?= $product['product_name'] ?>"

                            class="w-full border p-3 rounded-lg">
                    </div>
                    <div>


                        <label>

                            SKU

                        </label>
                        <input

                            name="sku"

                            value="<?= $product['sku'] ?>"

                            class="w-full border p-3 rounded-lg">


                    </div>








                    <div>


                        <label>

                            Unit

                        </label>


                        <input

                            name="unit"

                            value="<?= $product['unit'] ?>"

                            class="w-full border p-3 rounded-lg">


                    </div>









                    <div>


                        <label>

                            Purchase Price

                        </label>


                        <input

                            name="purchase_price"

                            value="<?= $product['purchase_price'] ?>"

                            class="w-full border p-3 rounded-lg">


                    </div>







                    <div>


                        <label>

                            Selling Price

                        </label>


                        <input

                            name="selling_price"

                            value="<?= $product['selling_price'] ?>"

                            class="w-full border p-3 rounded-lg">


                    </div>








                    <div>


                        <label>

                            Quantity

                        </label>


                        <input

                            name="quantity"

                            value="<?= $product['quantity'] ?>"

                            class="w-full border p-3 rounded-lg">


                    </div>








                    <div>


                        <label>

                            Status

                        </label>


                        <select

                            name="status"

                            class="w-full border p-3 rounded-lg">



                            <option value="Active"

                                <?= ($product['status'] == "Active") ? 'selected' : '' ?>>

                                Active

                            </option>



                            <option value="Inactive"

                                <?= ($product['status'] == "Inactive") ? 'selected' : '' ?>>

                                Inactive

                            </option>



                        </select>


                    </div>






                </div>








                <!-- Image -->


                <div class="mt-5">


                    <label>

                        Product Image

                    </label>



                    <div class="flex gap-5 items-center mt-3">


                        <img

                            src="../img/<?= $product['image'] ?>"

                            class="w-20 h-20 rounded-lg object-cover">





                        <input

                            type="file"

                            name="image"

                            class="border p-3 rounded-lg">


                    </div>



                    <p class="text-sm text-gray-400 mt-2">

                        Choose new image only if you want to change

                    </p>


                </div>









                <div class="flex justify-end gap-4 mt-8">


                    <a href="index.php"

                        class="bg-gray-200 px-6 py-3 rounded-lg">

                        Cancel

                    </a>




                    <button

                        name="update"

                        class="bg-indigo-600 text-white px-6 py-3 rounded-lg">

                        Update Product

                    </button>



                </div>





            </form>




        </div>



    </div>




</body>

</html>