<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['product_id']) || !isset($input['selling_price'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$product_id = (int)$input['product_id'];
$selling_price = (float)$input['selling_price'];

if ($product_id <= 0 || $selling_price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID or selling price']);
    exit;
}

$product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, purchase_price FROM products WHERE id = $product_id AND status = 'Active'"));

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$purchase_price = (float)$product['purchase_price'];
$new_flag = ($purchase_price > 0 && $selling_price <= $purchase_price) ? 1 : 0;

mysqli_query($conn, "UPDATE products SET selling_price = $selling_price, price_update_required = $new_flag WHERE id = $product_id");

if (mysqli_affected_rows($conn) >= 0) {
    echo json_encode(['success' => true, 'message' => 'Selling price updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update selling price']);
}
