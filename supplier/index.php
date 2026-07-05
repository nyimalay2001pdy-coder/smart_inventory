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
    <link rel="stylesheet" href="../assets/css/style.css">

</head>


<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>
        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>
            <main class="p-6">
            <div class="flex justify-between items-center">



                <div>
                    <p class="text-gray-500">

                        Manage your suppliers

                    </p>


                </div>




                <button

                    onclick="openAdd()"

                    class="bg-indigo-600 text-white px-6 py-3 rounded-xl">


                    + Add Supplier


                </button>



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


                        <tr class="border-b text-gray-500">


                            <th class="p-4 text-left">
                                #
                            </th>


                            <th>
                                Supplier
                            </th>


                            <th>
                                Phone
                            </th>


                            <th>
                                Email
                            </th>


                            <th>
                                Address
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




                                <td class="font-semibold">

                                    🚚 <?= $row['supplier_name'] ?>

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


                                    <button

                                        onclick="openEdit(

'<?= $row['id'] ?>',

'<?= $row['supplier_name'] ?>',

'<?= $row['phone'] ?>',

'<?= $row['email'] ?>',

'<?= $row['address'] ?>',

'<?= $row['status'] ?>'

)"

                                        class="bg-blue-100 text-blue-600 px-3 py-2 rounded">


                                        ✏

                                    </button>





                                    <a href="?delete=<?= $row['id'] ?>"

                                        onclick="return confirm('Delete supplier?')"

                                        class="bg-red-100 text-red-600 px-3 py-2 rounded ml-2">


                                        🗑


                                    </a>




                                </td>




                            </tr>



                        <?php } ?>



                    </tbody>



                </table>



            </div>

            <!-- Add Supplier Modal -->

            <div id="addModal"

                class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center">


                <div class="bg-white w-full max-w-lg p-8 rounded-2xl shadow-xl">


                    <h2 class="text-2xl font-bold mb-6">

                        Add Supplier

                    </h2>



                    <form method="POST">


                        <input

                            type="text"

                            name="supplier_name"

                            placeholder="Supplier Name"

                            class="w-full border p-3 rounded-lg mb-4"

                            required>




                        <input

                            type="text"

                            name="phone"

                            placeholder="Phone Number"

                            class="w-full border p-3 rounded-lg mb-4">





                        <input

                            type="email"

                            name="email"

                            placeholder="Email"

                            class="w-full border p-3 rounded-lg mb-4">





                        <textarea

                            name="address"

                            placeholder="Address"

                            class="w-full border p-3 rounded-lg mb-4"></textarea>





                        <select

                            name="status"

                            class="w-full border p-3 rounded-lg mb-5">


                            <option value="Active">

                                Active

                            </option>


                            <option value="Inactive">

                                Inactive

                            </option>


                        </select>






                        <div class="flex justify-end gap-3">


                            <button

                                type="button"

                                onclick="closeAdd()"

                                class="bg-gray-200 px-5 py-3 rounded-lg">

                                Cancel

                            </button>




                            <button

                                name="add_supplier"

                                class="bg-indigo-600 text-white px-5 py-3 rounded-lg">

                                Save

                            </button>



                        </div>



                    </form>



                </div>


            </div>
            <!-- Edit Supplier Modal -->


            <div id="editModal"

                class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center">


                <div class="bg-white w-full max-w-lg p-8 rounded-2xl">



                    <h2 class="text-2xl font-bold mb-6">

                        Edit Supplier

                    </h2>





                    <form method="POST">



                        <input type="hidden"

                            id="edit_id"

                            name="id">





                        <input

                            id="edit_name"

                            name="supplier_name"

                            class="w-full border p-3 rounded-lg mb-4">






                        <input

                            id="edit_phone"

                            name="phone"

                            class="w-full border p-3 rounded-lg mb-4">






                        <input

                            id="edit_email"

                            name="email"

                            class="w-full border p-3 rounded-lg mb-4">





                        <textarea

                            id="edit_address"

                            name="address"

                            class="w-full border p-3 rounded-lg mb-4"></textarea>






                        <select

                            id="edit_status"

                            name="status"

                            class="w-full border p-3 rounded-lg mb-5">


                            <option value="Active">

                                Active

                            </option>


                            <option value="Inactive">

                                Inactive

                            </option>


                        </select>







                        <div class="flex justify-end gap-3">


                            <button

                                type="button"

                                onclick="closeEdit()"

                                class="bg-gray-200 px-5 py-3 rounded-lg">

                                Cancel

                            </button>




                            <button

                                name="update_supplier"

                                class="bg-indigo-600 text-white px-5 py-3 rounded-lg">

                                Update

                            </button>



                        </div>




                    </form>


                </div>


            </div>




        </main>
        <script>
            function openAdd() {

                document.getElementById("addModal")
                    .classList.remove("hidden");

            }



            function closeAdd() {

                document.getElementById("addModal")
                    .classList.add("hidden");

            }






            function openEdit(id, name, phone, email, address, status) {


                document.getElementById("editModal")
                    .classList.remove("hidden");



                document.getElementById("edit_id").value = id;


                document.getElementById("edit_name").value = name;


                document.getElementById("edit_phone").value = phone;


                document.getElementById("edit_email").value = email;


                document.getElementById("edit_address").value = address;


                document.getElementById("edit_status").value = status;



            }






            function closeEdit() {


                document.getElementById("editModal")
                    .classList.add("hidden");


            }
        </script>


    </div>
</div>
<?php include "../includes/toast.php"; ?>
<?php include "../includes/modal.php"; ?>
<?php include "../includes/footer.php"; ?>
</body>

</html>