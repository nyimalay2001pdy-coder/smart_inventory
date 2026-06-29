<?php

include "../config/db.php";

//images
if (
    isset($_FILES['image']) &&
    $_FILES['image']['name'] != ""
) {
    $image = $_FILES["image"]["name"];
    $tmp = $_FILES["image"]["tmp_name"];

    move_uploaded_file($tmp, $image);
}

// Get Categories

$category_sql = "SELECT * FROM categories ORDER BY name ASC";

$categories = mysqli_query($conn, $category_sql);

// Insert Product
if (isset($_POST['save'])) {
    $category_id = $_POST['category_id'];
    $product_name = $_POST['product_name'];
    $sku = $_POST['sku'];
    $purchase_price = $_POST['purchase_price'];
    $selling_price = $_POST['selling_price'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    $image = $_FILES['image']['name'];
    $status = $_POST['status'];





    $sql = "
INSERT INTO products
(
category_id,
product_name,
sku,
purchase_price,
selling_price,
quantity,
unit,
image,
status
)
VALUES
(
'$category_id',
'$product_name',
'$sku',
'$purchase_price',
'$selling_price',
'$quantity',
'$unit',
'$image',
'$status'

)

";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        header("Location:index.php");
        exit;
    }
}
?>

<!DOCTYPE html>

<html>


<head>


    <title>Add Product</title>


    <script src="https://cdn.tailwindcss.com"></script>


</head>




<body class="bg-gray-50">





    <div class="min-h-screen flex items-center justify-center">





        <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-3xl">





            <h1 class="text-3xl font-bold mb-2">

                Add New Product

            </h1>



            <p class="text-gray-500 mb-8">

                Create product information

            </p>






            <form method="POST" enctype="multipart/form-data">





                <div class="grid grid-cols-2 gap-5">





                    <!-- Category -->


                    <div>


                        <label class="font-semibold">

                            Category

                        </label>



                        <select

                            name="category_id"

                            class="w-full border rounded-lg p-3 mt-2">


                            <option>

                                Select Category

                            </option>



                            <?php while ($cat = mysqli_fetch_assoc($categories)) { ?>


                                <option value="<?= $cat['id'] ?>">


                                    <?= $cat['name'] ?>


                                </option>



                            <?php } ?>


                        </select>


                    </div>








                    <!-- Product Name -->


                    <div>


                        <label class="font-semibold">

                            Product Name

                        </label>


                        <input

                            type="text"

                            name="product_name"

                            class="w-full border rounded-lg p-3 mt-2"

                            placeholder="Enter product name">


                    </div>







                    <!-- SKU -->


                    <div>


                        <label class="font-semibold">

                            SKU

                        </label>


                        <input

                            type="text"

                            name="sku"

                            class="w-full border rounded-lg p-3 mt-2"

                            placeholder="Example: DRK001">


                    </div>






                    <!-- Unit -->


                    <div>


                        <label class="font-semibold">

                            Unit

                        </label>


                        <select

                            name="unit"

                            class="w-full border rounded-lg p-3 mt-2">


                            <option>

                                pcs

                            </option>


                            <option>

                                box

                            </option>


                            <option>

                                kg

                            </option>


                            <option>

                                liter

                            </option>


                        </select>


                    </div>








                    <!-- Purchase Price -->


                    <div>


                        <label class="font-semibold">

                            Purchase Price

                        </label>


                        <input

                            type="number"

                            name="purchase_price"

                            class="w-full border rounded-lg p-3 mt-2">


                    </div>








                    <!-- Selling Price -->


                    <div>


                        <label class="font-semibold">

                            Selling Price

                        </label>


                        <input

                            type="number"

                            name="selling_price"

                            class="w-full border rounded-lg p-3 mt-2">


                    </div>







                    <!-- Quantity -->


                    <div>


                        <label class="font-semibold">

                            Quantity

                        </label>


                        <input

                            type="number"

                            name="quantity"

                            class="w-full border rounded-lg p-3 mt-2">


                    </div>







                    <!-- Status -->


                    <div>


                        <label class="font-semibold">

                            Status

                        </label>


                        <select

                            name="status"

                            class="w-full border rounded-lg p-3 mt-2">


                            <option value="Active">

                                Active

                            </option>


                            <option value="Inactive">

                                Inactive

                            </option>



                        </select>


                    </div>

                    <!--Product image-->
                    <div>
                        <label for="font-semibold">
                            Product Image
                        </label>
                        <input type="file"
                            name="image"
                            class="w-full border rounded-lg p-3 mt-2">
                    </div>





                </div>








                <div class="flex justify-end gap-4 mt-8">


                    <a href="index.php"

                        class="px-6 py-3 bg-gray-200 rounded-lg">

                        Cancel

                    </a>



                    <button

                        name="save"

                        class="px-6 py-3 bg-indigo-600 text-white rounded-lg">


                        Save Product

                    </button>



                </div>







            </form>






        </div>



    </div>






</body>

</html>