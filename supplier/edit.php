<?php
include "../includes/auth_check.php";
requireAdmin();
include "../config/database.php";
include "../config/helpers.php";

$page_title = "Edit Supplier";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$supplier = fetchOne($conn, "SELECT * FROM suppliers WHERE id = ?", [$id], "i");

if (!$supplier) {
    header("Location: index.php?error=" . urlencode("Supplier not found."));
    exit;
}

$supplier_name = $supplier['supplier_name'];
$contact_person = $supplier['contact_person'] ?? '';
$phone = $supplier['phone'];
$email = $supplier['email'];
$address = $supplier['address'];
$status = $supplier['status'];
$errors = [];

if (isset($_POST['update'])) {
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    if ($supplier_name === '') {
        $errors['supplier_name'] = 'Supplier name is required.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    }

    if (empty($errors)) {
        $sql = "UPDATE suppliers SET supplier_name = ?, contact_person = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?";
        if (executeQuery($conn, $sql, [$supplier_name, $contact_person, $phone, $email, $address, $status, $id], "ssssssi")) {
            header("Location: index.php?success=" . urlencode("Supplier has been updated successfully."));
            exit;
        } else {
            $errors['general'] = 'Failed to update supplier. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Smart Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "../includes/sidebar.php"; ?>

        <div class="flex-1 flex flex-col">
            <?php include "../includes/header.php"; ?>

            <main class="p-6">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h1 class="text-lg font-semibold text-gray-800 mb-5">Edit Supplier</h1>
                        <form method="POST" novalidate onsubmit="return handleSave()">

                            <div class="mb-5">
                                <label for="supplier_name" class="form-label">Supplier Name <span class="text-red-500">*</span></label>
                                <input type="text" id="supplier_name" name="supplier_name" value="<?= htmlspecialchars($supplier_name) ?>"
                                    placeholder="Enter supplier name"
                                    class="form-input <?= isset($errors['supplier_name']) ? 'error' : '' ?>">
                                <?php if (isset($errors['supplier_name'])): ?>
                                    <p class="form-error"><?= $errors['supplier_name'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-5">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($contact_person) ?>"
                                    placeholder="Enter contact person name"
                                    class="form-input">
                            </div>

                            <div class="mb-5">
                                <label for="phone" class="form-label">Phone Number <span class="text-red-500">*</span></label>
                                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>"
                                    placeholder="Enter phone number"
                                    class="form-input <?= isset($errors['phone']) ? 'error' : '' ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <p class="form-error"><?= $errors['phone'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-5">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>"
                                    placeholder="Enter email address"
                                    class="form-input">
                            </div>

                            <div class="mb-5">
                                <label for="address" class="form-label">Address</label>
                                <textarea id="address" name="address" rows="4"
                                    placeholder="Enter address"
                                    class="form-input"><?= htmlspecialchars($address) ?></textarea>
                            </div>

                            <div class="mb-6">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-input">
                                    <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" name="update" class=" btn-primary p-2 rounded-lg text-sm">
                                    Update Supplier
                                </button>
                                <a href="index.php" class="btn-secondary p-2 rounded-lg text-sm" onclick="return confirmCancel()">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        let formChanged = false;
        document.querySelectorAll('input, textarea, select').forEach(function(el) {
            el.addEventListener('input', function() {
                formChanged = true;
            });
            el.addEventListener('change', function() {
                formChanged = true;
            });
        });

        function confirmCancel() {
            if (formChanged) {
                return confirm('You have unsaved changes. Are you sure you want to leave?');
            }
            return true;
        }

        function handleSave() {
            formChanged = false;
            return true;
        }

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>

    <?php include "../includes/footer.php"; ?>
</body>

</html>