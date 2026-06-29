<?php
include "../config/db.php";
$search = "";
$status = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}
if (isset($_GET['status'])) {
    $status = $_GET['status'];
}
$sql = "
SELECT 
stock_in.*,
suppliers.supplier_name
FROM stock_in
INNER JOIN suppliers
ON stock_in.supplier_id = suppliers.id
WHERE 1
";
if ($search != "") {
    $sql .= "
AND suppliers.supplier_name
LIKE '%$search%'
";
}
if ($status != "") {
    $sql .= "
AND payment_status='$status'
";
}
$sql .= "
ORDER BY stock_in.id DESC
";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>
        Stock In Management
    </title>
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
            <nav class="p-5 space-y-3 ">
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
                    🏠 Dashboard
                </a>
                <a class="block p-3 rounded-lg bg-indigo-600 text-white">
                    🏷 Categories
                </a>
                <a class="block p-3 rounded-lg hover:bg-indigo-600 hover:text-white">
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

        <!-- Main -->
        <main class="flex-1 p-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold">
                        Stock In Management
                    </h1>
                    <p class="text-gray-500">
                        Purchase History
                    </p>
                </div>
                <a href="add.php"
                    class="bg-indigo-600 text-white px-6 py-3 rounded-xl">
                    ＋ Add Purchase
                </a>
            </div>

            <!-- Search -->
            <form method="GET"
                class="bg-white mt-8 p-5 rounded-2xl shadow flex gap-4">
                <input
                    name="search"
                    value="<?= $search ?>"
                    placeholder="Search supplier..."
                    class="flex-1 border p-3 rounded-xl">
                <select
                    name="status"
                    class="border rounded-xl px-5">
                    <option value="">
                        All Payment Status
                    </option>
                    <option value="Paid">
                        Paid
                    </option>
                    <option value="Unpaid">
                        Unpaid
                    </option>
                </select>
                <button
                    class="bg-indigo-600 text-white px-6 rounded-xl">
                    🔍 Search
                </button>
                <a href="index.php"

                    class="border px-6 py-3 rounded-xl">


                    Reset


                </a>



            </form>









            <!-- Table -->



            <div class="bg-white rounded-2xl shadow mt-8 p-6 overflow-x-auto">


                <table class="w-full">



                    <thead>


                        <tr class="border-b text-gray-500">


                            <th class="p-4">
                                #
                            </th>


                            <th>
                                Date
                            </th>


                            <th>
                                Supplier
                            </th>


                            <th>
                                Total Amount
                            </th>


                            <th>
                                Payment
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

                                    <?= $row['purchase_date'] ?>

                                </td>





                                <td class="font-semibold">

                                    🚚 <?= $row['supplier_name'] ?>

                                </td>





                                <td class="text-green-600 font-bold">

                                    <?= number_format($row['total_amount']) ?>

                                    Ks

                                </td>





                                <td>


                                    <?php if ($row['payment_status'] == "Paid") { ?>


                                        <span class="bg-green-100 text-green-600 px-3 py-1 rounded-full">

                                            Paid

                                        </span>


                                    <?php } else { ?>


                                        <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full">

                                            Unpaid

                                        </span>


                                    <?php } ?>


                                </td>






                                <td>


                                    <a href="view.php?id=<?= $row['id'] ?>"

                                        class="bg-blue-100 text-blue-600 px-3 py-2 rounded">


                                        👁 View


                                    </a>





                                    <a href="delete.php?id=<?= $row['id'] ?>"

                                        onclick="return confirm('Delete Purchase?')"

                                        class="bg-red-100 text-red-600 px-3 py-2 rounded ml-2">


                                        🗑

                                    </a>



                                </td>



                            </tr>



                        <?php } ?>



                    </tbody>


                </table>


            </div>






        </main>


    </div>


</body>

</html>