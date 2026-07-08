<?php
include "../includes/auth_check.php";
requireAdmin();
include "../config/database.php";
$page_title = "Suppliers";


// ADD SUPPLIER

if (isset($_POST['add_supplier'])) {


    $name = $_POST['supplier_name'];

    $phone = $_POST['phone'];

    $email = $_POST['email'];

    $address = $_POST['address'];

    $status = $_POST['status'];



    $sql = "

INSERT INTO suppliers

(
supplier_name,
phone,
email,
address,
status
)

VALUES

(
'$name',
'$phone',
'$email',
'$address',
'$status'
)

";


    mysqli_query($conn, $sql);


    header("Location:index.php");
}





// DELETE SUPPLIER


if (isset($_GET['delete'])) {


    $id = $_GET['delete'];


    mysqli_query(

        $conn,

        "DELETE FROM suppliers WHERE id='$id'"



    );



    header("Location:index.php");
}








// UPDATE SUPPLIER


if (isset($_POST['update_supplier'])) {


    $id = $_POST['id'];

    $name = $_POST['supplier_name'];

    $phone = $_POST['phone'];

    $email = $_POST['email'];

    $address = $_POST['address'];

    $status = $_POST['status'];



    $sql = "

UPDATE suppliers SET

supplier_name='$name',

phone='$phone',

email='$email',

address='$address',

status='$status'


WHERE id='$id'

";



    mysqli_query($conn, $sql);



    header("Location:index.php");
}








// SEARCH FILTER


$search = "";

$status = "";



if (isset($_GET['search'])) {

    $search = $_GET['search'];
}


if (isset($_GET['status'])) {

    $status = $_GET['status'];
}



$sql = "

SELECT * FROM suppliers

WHERE 1

";



if ($search != "") {


    $sql .= "

AND (

supplier_name LIKE '%$search%'

OR phone LIKE '%$search%'

OR email LIKE '%$search%'

)

";
}



if ($status != "") {


    $sql .= " AND status='$status'";
}



$sql .= " ORDER BY id DESC";




$result = mysqli_query($conn, $sql);



?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Supplier Management</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "../includes/theme-init.php"; ?>
    <link rel="stylesheet" href="../assets/css/style.css">

</head>


<body class="bg-gray-50 dark:bg-slate-900">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-6">
                <div class="flex justify-between items-center">
                    <a href="add.php"
                        class="bg-indigo-600 text-white px-6 py-3 rounded-xl">
                        + Add Supplier
                    </a>
                </div>

                <!-- Search -->
                <form method="GET"
                    class="bg-white p-5 rounded-2xl shadow mt-8 flex gap-4">
                    <input
                        name="search"
                        value="<?= $search ?>"
                        placeholder="Search supplier..."
                        class="flex-1 border rounded-xl p-3">
                    <select
                        name="status"
                        class="border rounded-xl px-5">
                        <option value="">
                            All Status
                        </option>
                        <option value="Active">
                            Active
                        </option>
                        <option value="Inactive">
                            Inactive
                        </option>
                    </select>
                    <button
                        class="bg-indigo-600 text-white px-6 rounded-xl">
                        Search
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
                            <tr class="border-b text-gray-500 dark:text-gray-400">
                                <th class="p-4 text-left">
                                    #
                                </th>
                                <th class="p-4 text-left">
                                    Supplier
                                </th>
                                <th class="text-left">
                                    Contact Person
                                </th>
                                <th class="p-4 text-left">
                                    Phone
                                </th>
                                <th class="p-4 text-left">
                                    Email
                                </th>
                                <th class=" text-left">
                                    Address
                                </th>
                                <th class="p-2 text-left">
                                    Status
                                </th>
                                <th class="p-8 text-left">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 1;
                            while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                <tr class=" border-b">
                                    <td class="p-4">
                                        <?= $count++ ?>
                                    </td>
                                    <td class="font-semibold">
                                        🚚 <?= $row['supplier_name'] ?>
                                    </td>




                                    <td>

                                        <?= $row['contact_person'] ?? '' ?>

                                    </td>




                                    <td>

                                        <?= $row['phone'] ?>

                                    </td>




                                    <td>

                                        <?= $row['email'] ?>

                                    </td>




                                    <td>

                                        <?= $row['address'] ?>

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


                                            ✏️Edit


                                        </a>




                                        <a href="?delete=<?= $row['id'] ?>"

                                            onclick="return confirm('Delete supplier?')"

                                            class="bg-red-100 text-red-600 px-3 py-2 rounded ml-2">


                                            🗑 Delete


                                        </a>



                                    </td>



                                </tr>



                            <?php } ?>



                        </tbody>



                    </table>



                </div>



            </main>

        </div>
    </div>
    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>

</html>