<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$theme = $_POST['theme'] ?? '';
if (!in_array($theme, ['light', 'dark', 'system'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid theme']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
$stmt->bind_param("si", $theme, $user_id);

if ($stmt->execute()) {
    $_SESSION['theme'] = $theme;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

$stmt->close();
$conn->close();
