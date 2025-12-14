<?php
session_start();
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit();
}

// Count unread notifications
$query = "SELECT COUNT(*) as count FROM notifications 
          WHERE  is_read = 0";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'count' => $row['count'] ?? 0
]);
?>