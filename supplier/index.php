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
                <div class="flex justify-end items-center">
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
                        class="flex-1 border rounded-xl px-4 py-3">
                    <select
                        name="status"
                        class="border rounded-xl px-4 py-3">
                        <option value="">All Status</option>
                        <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <button class="bg-indigo-600 text-white px-6 py-3 rounded-xl">Search</button>
                    <a href="index.php" class="border px-6 py-3 rounded-xl">Reset</a>
                </form>
                <!-- Table -->
                <div class="bg-white rounded-2xl shadow mt-8 p-6">
                    <div class="table-wrap">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Supplier</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th class="center">Status</th>
                                <th class="center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 1;
                            while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                <tr>
                                    <td><?= $count++ ?></td>
                                    <td class="font-semibold"><?= htmlspecialchars($row['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($row['contact_person'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="text-sm text-gray-500 max-w-xs truncate"><?= htmlspecialchars($row['address']) ?></td>
                                    <td class="center">
                                        <?php if ($row['status'] == "Active") { ?>
                                            <span class="badge badge-success"><span class="badge-dot"></span> Active</span>
                                        <?php } else { ?>
                                            <span class="badge badge-danger"><span class="badge-dot"></span> Inactive</span>
                                        <?php } ?>
                                    </td>
                                    <td class="center">
                                        <div class="actions">
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm bg-blue-100 text-blue-600 hover:bg-blue-200 rounded-lg">Edit</a>
                                            <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete supplier?')" class="btn btn-sm bg-red-100 text-red-600 hover:bg-red-200 rounded-lg">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    </div>

                </div>



            </main>

        </div>
    </div>
    <?php include "../includes/toast.php"; ?>
    <?php include "../includes/modal.php"; ?>
    <?php include "../includes/footer.php"; ?>
</body>

</html>