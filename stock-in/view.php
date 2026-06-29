<?php

include "../config/db.php";

$id = $_GET['id'];

// Stock In Data
$stock = mysqli_fetch_assoc(

    mysqli_query(

        $conn,

        "

SELECT 

stock_in.*,

suppliers.supplier_name,

suppliers.phone,

suppliers.address


FROM stock_in


INNER JOIN suppliers

ON stock_in.supplier_id = suppliers.id


WHERE stock_in.id='$id'


"

    )

);





// Product Details


$details = mysqli_query(

    $conn,

    "

SELECT

stock_in_details.*,

products.product_name


FROM stock_in_details


INNER JOIN products

ON stock_in_details.product_id = products.id



WHERE stock_in_details.stock_in_id='$id'


"

);



?>





<!DOCTYPE html>

<html>


<head>


    <title>

        Purchase Detail

    </title>


    <script src="https://cdn.tailwindcss.com"></script>


</head>



<body class="bg-gray-50">





    <div class="max-w-5xl mx-auto p-8">






        <div class="bg-white rounded-2xl shadow p-8">





            <div class="flex justify-between">


                <div>


                    <h1 class="text-3xl font-bold">

                        Purchase Invoice

                    </h1>


                    <p class="text-gray-500">

                        Invoice ID : #<?= $stock['id'] ?>

                    </p>


                </div>




                <a href="index.php"

                    class="bg-gray-200 px-5 py-3 rounded-lg">


                    ← Back


                </a>



            </div>







            <hr class="my-6">








            <!-- Supplier Info -->


            <div class="grid grid-cols-2 gap-5">



                <div>


                    <h3 class="font-bold text-lg">

                        Supplier Information

                    </h3>



                    <p>

                        Name:

                        <?= $stock['supplier_name'] ?>

                    </p>



                    <p>

                        Phone:

                        <?= $stock['phone'] ?>

                    </p>



                    <p>

                        Address:

                        <?= $stock['address'] ?>

                    </p>



                </div>







                <div>


                    <h3 class="font-bold text-lg">

                        Purchase Information

                    </h3>


                    <p>

                        Date:

                        <?= $stock['purchase_date'] ?>

                    </p>


                    <p>

                        Payment:

                        <?= $stock['payment_status'] ?>

                    </p>


                </div>




            </div>







            <!-- Product Table -->



            <div class="mt-8">


                <h2 class="text-xl font-bold mb-4">

                    Product Details

                </h2>




                <table class="w-full">



                    <tr class="border-b text-gray-500">


                        <th class="p-3 text-left">

                            Product

                        </th>


                        <th>

                            Quantity

                        </th>


                        <th>

                            Purchase Price

                        </th>


                        <th>

                            Subtotal

                        </th>



                    </tr>






                    <?php while ($row = mysqli_fetch_assoc($details)) { ?>



                        <tr class="border-b">


                            <td class="p-3">

                                <?= $row['product_name'] ?>

                            </td>



                            <td class="text-center">

                                <?= $row['quantity'] ?>

                            </td>




                            <td class="text-center">

                                <?= number_format($row['purchase_price']) ?>

                                Ks

                            </td>





                            <td class="text-center">

                                <?= number_format($row['subtotal']) ?>

                                Ks

                            </td>



                        </tr>




                    <?php } ?>



                </table>



            </div>







            <!-- Total -->


            <div class="text-right mt-8">


                <h2 class="text-3xl font-bold text-indigo-600">


                    Total :

                    <?= number_format($stock['total_amount']) ?>

                    Ks


                </h2>


            </div>







        </div>



    </div>




</body>

</html>