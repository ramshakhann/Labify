<?php
session_start();
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get POST data
$title = $_POST['title'] ?? 'Test Notification';
$message = $_POST['message'] ?? 'This is a test notification';
$type = $_POST['type'] ?? 'info';

// Create notification
$query = "INSERT INTO notifications (user_id, title, message, type) 
          VALUES (?, ?, ?, ?)";

$stmt = $conn->prepare($query);
$stmt->bind_param("isss", $user_id, $title, $message, $type);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Notification created successfully',
        'id' => $stmt->insert_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create notification']);
}
?>