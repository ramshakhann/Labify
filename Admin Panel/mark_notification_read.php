<?php
session_start();
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['id'] ?? null;

if (!$user_id || !$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Mark notification as read
$query = "UPDATE notifications SET is_read = 1 
          WHERE id = ? AND user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $notification_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}
?>