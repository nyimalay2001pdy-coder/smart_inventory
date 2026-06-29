<?php
include "../config/db.php";

$search = "";
$status = "";
$alert_msg = "";
$show_add = false;
$show_edit = false;
$edit_row = null;


// --- DELETE ---
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $sql_del = "DELETE FROM categories WHERE id='$delete_id'";
    $result_del = mysqli_query($conn, $sql_del);
    header("Location: index.php");
    exit;
}

// --- ADD ---
if (isset($_POST['save'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $add_status = trim($_POST['status']);

    // Check duplicate
    $check_sql = "SELECT * FROM categories WHERE name = '$category_name'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $alert_msg = "This category already exists! Please use a different name.";
        $show_add = true;
    } else {
        $sql_add = "INSERT INTO categories (name, description, status) VALUES ('$category_name', '$description', '$add_status')";
        $result_add = mysqli_query($conn, $sql_add);
        if ($result_add) {
            header("Location: index.php");
            exit;
        } else {
            $alert_msg = "Failed to add category.";
            $show_add = true;
        }
    }
}

// --- UPDATE ---
if (isset($_POST['update'])) {
    $edit_id = $_POST['edit_id'];
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $edit_status = trim($_POST['status']);

    // Check duplicate (exclude current record)
    $check_sql = "SELECT * FROM categories WHERE name = '$category_name' AND id != '$edit_id'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $alert_msg = "This category name already exists! Please use a different name.";
        $show_edit = true;
        // Reload the row for the edit form
        $edit_q = mysqli_query($conn, "SELECT * FROM categories WHERE id='$edit_id'");
        $edit_row = mysqli_fetch_assoc($edit_q);
    } else {
        $sql_update = "UPDATE categories SET name='$category_name', description='$description', status='$edit_status' WHERE id='$edit_id'";
        $result_update = mysqli_query($conn, $sql_update);
        if ($result_update) {
            header("Location: index.php");
            exit;
        } else {
            $alert_msg = "Failed to update category.";
            $show_edit = true;
        }
    }
}

// --- EDIT (load data when clicking Edit button) ---
if (isset($_GET['edit_id'])) {
    $eid = $_GET['edit_id'];
    $edit_q = mysqli_query($conn, "SELECT * FROM categories WHERE id='$eid'");
    $edit_row = mysqli_fetch_assoc($edit_q);
    $show_edit = true;
}

// --- SEARCH/FILTER ---
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}
if (isset($_GET['status'])) {
    $status = $_GET['status'];
}
$sql = "SELECT * FROM categories WHERE 1";
if ($search !== "") {
    $sql .= " AND name LIKE '%$search%'";
}
if ($status != "") {
    $sql .= " AND status= '$status'";
}
$sql .= " ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
    </style>
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
                    <h1 class="text-3xl font-bold">
                        Category Management
                    </h1>
                    <p class="text-gray-500 mt-2">
                        Manage all product categories
                    </p>
                </div>
                <button onclick="openAddModal()"
                    class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition">
                    + Add New Category
                </button>
            </div>

            <!--Search bar-->
            <form method="GET" class="flex gap-4 mb-6 mt-6">
                <input
                    type="text"
                    name="search"
                    value="<?php echo $search ?>"
                    placeholder="Search Category.."
                    class="border rounded-lg px-5 py-3 w-80">

                <select
                    name="status"
                    class="border rounded-lg px-5 py-3">
                    <option value="">
                        All Status
                    </option>
                    <option value="Active"
                        <?php echo ($status == "Active" ? 'selected' : '') ?>>
                        Active
                    </option>
                    <option value="Inactive"
                        <?php echo ($status == "Inactive" ? 'selected' : '') ?>>
                        Inactive
                    </option>
                </select>
                <button
                    class="bg-indigo-600 text-white px-6 py-3 rounded-lg">Search</button>
                <a href="index.php"
                    class="bg-gray-200 px-6 py-3 rounded-lg">
                    Reset</a>
            </form>

            <!-- Table Card -->
            <div class="bg-white rounded-xl shadow mt-8 p-6">
                <table class="w-full">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="p-4">
                                #
                            </th>
                            <th>
                                Category Name
                            </th>
                            <th>
                                Description
                            </th>
                            <th>
                                Status
                            </th>
                            <th>
                                Created Date
                            </th>
                            <th>
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) > 0) {
                            $count = 1;
                            while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                                <tr class="border-b">
                                    <td class="p-4">
                                        <?= $count++ ?>
                                    </td>
                                    <td class="font-semibold">
                                        <?= $row['name'] ?>
                                    </td>
                                    <td>
                                        <?= $row['description'] ?>
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
                                        <?= $row['created_at'] ?>
                                    </td>
                                    <td>
                                        <a href="index.php?edit_id=<?= $row['id'] ?>"
                                            class="bg-blue-100 text-blue-600 px-3 py-2 rounded cursor-pointer">
                                            ✏ Edit
                                        </a>
                                        <a href="index.php?delete_id=<?= $row['id'] ?>"
                                            onclick="return confirm('Are you sure want to delete?')"
                                            class="bg-red-100 text-red-600 px-3 py-2 rounded ml-2 cursor-pointer">
                                            🗑 Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="6"
                                    class="text-center py-10 text-gray-500">
                                    No Category Found
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ========== ADD MODAL ========== -->
    <div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay <?php echo $show_add ? '' : 'hidden'; ?>">
        <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-xl relative">
            <button onclick="closeAddModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
            <h1 class="text-3xl font-bold mb-2">
                Add New Category
            </h1>
            <p class="text-gray-500 mb-8">
                Create a new product category
            </p>
            <form method="POST" action="index.php">
                <!-- Category Name -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold">
                        Category Name
                    </label>
                    <input
                        type="text"
                        name="category_name"
                        placeholder="Enter category name"
                        required
                        class="w-full border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <!-- Description -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold">
                        Description
                    </label>
                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Enter description"
                        class="w-full border rounded-lg px-4 py-3"></textarea>
                </div>
                <!-- Status -->
                <div class="mb-6">
                    <label class="block mb-2 font-semibold">
                        Status
                    </label>
                    <select
                        name="status"
                        class="w-full border rounded-lg px-4 py-3">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex justify-between">
                    <button type="button" onclick="closeAddModal()"
                        class="px-6 py-3 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        name="save"
                        class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== EDIT MODAL ========== -->
    <?php if ($show_edit && $edit_row) { ?>
        <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay">
            <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-xl relative">
                <a href="index.php" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-2xl">&times;</a>
                <h1 class="text-3xl font-bold mb-2">
                    Edit Category
                </h1>
                <p class="text-gray-500 mb-8">
                    Update category information
                </p>
                <form method="POST" action="index.php">
                    <input type="hidden" name="edit_id" value="<?= $edit_row['id'] ?>">
                    <!-- Category Name -->
                    <div class="mb-5">
                        <label class="block mb-2 font-semibold">
                            Category Name
                        </label>
                        <input
                            type="text"
                            name="category_name"
                            value="<?= $edit_row['name'] ?>"
                            required
                            class="w-full border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <!-- Description -->
                    <div class="mb-5">
                        <label class="block mb-2 font-semibold">
                            Description
                        </label>
                        <textarea
                            name="description"
                            rows="4"
                            class="w-full border rounded-lg px-4 py-3"><?= $edit_row['description'] ?></textarea>
                    </div>
                    <!-- Status -->
                    <div class="mb-6">
                        <label class="block mb-2 font-semibold">
                            Status
                        </label>
                        <select
                            name="status"
                            class="w-full border rounded-lg px-4 py-3">
                            <option value="Active" <?= ($edit_row['status'] == "Active") ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($edit_row['status'] == "Inactive") ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="flex justify-between">
                        <a href="index.php"
                            class="px-6 py-3 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                            Cancel
                        </a>
                        <button
                            type="submit"
                            name="update"
                            class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                            Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>

    <!-- Alert -->
    <?php if ($alert_msg !== "") { ?>
        <script>
            alert("<?= $alert_msg ?>");
        </script>
    <?php } ?>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }
    </script>
</body>

</html>